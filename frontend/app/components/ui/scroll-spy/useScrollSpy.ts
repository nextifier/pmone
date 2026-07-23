import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  toValue,
  watch,
} from "vue";
import type { InjectionKey, MaybeRefOrGetter, Ref } from "vue";

/**
 * Set by `ScrollSpyScrollArea` so `ScrollSpyItems` can keep the active link in
 * view without reaching for the DOM.
 */
export const SCROLL_SPY_VIEWPORT: InjectionKey<Ref<HTMLElement | null>> =
  Symbol("scroll-spy-viewport");

export interface ScrollSpyHeading {
  id: string;
  text: string;
  /** Heading level as a number: `h2` -> 2, `h3` -> 3, ... */
  depth: number;
}

interface ObservedItem {
  id: string;
  active: boolean;
  /** True when the item was force-activated because nothing else intersected. */
  fallback: boolean;
  /** Timestamp of the last active/inactive flip, used to order simultaneous hits. */
  t: number;
}

/**
 * Horizontal indent of the link text, per heading depth.
 * Mirrors the reference TOC (godui.design / fumadocs "clerk" style).
 */
export function getItemOffset(depth: number): number {
  if (depth <= 2) return 20;
  if (depth === 3) return 32;
  return 44;
}

/** Horizontal position of the rail line, per heading depth. */
export function getLineOffset(depth: number): number {
  if (depth <= 2) return 8;
  if (depth === 3) return 16;
  return 24;
}

/**
 * Slug used as the heading's DOM id. Kept byte-for-byte compatible with the
 * previous implementation so links shared before this change still resolve.
 */
export function createUniqueId(text: string, index: number): string {
  const slug = text
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^\w-]+/g, "");

  return `${slug}-${index}`;
}

/**
 * A selector like `#2024-recap` is not a valid CSS id selector, so ids that
 * start with a digit are rewritten to the attribute form.
 */
function normalizeSelector(selector: string): string {
  if (selector.startsWith("#") && /\d/.test(selector.charAt(1))) {
    return `[id="${selector.substring(1)}"]`;
  }
  return selector;
}

export function useScrollSpy(options: {
  contentSelector: MaybeRefOrGetter<string>;
  excludeSelector?: MaybeRefOrGetter<string | undefined>;
  onHeadings?: (headings: ScrollSpyHeading[]) => void;
}) {
  const headings = ref<ScrollSpyHeading[]>([]);
  const items = ref<ObservedItem[]>([]);
  let observer: IntersectionObserver | null = null;

  function scan(): ScrollSpyHeading[] {
    const selector = toValue(options.contentSelector);
    if (!selector) return [];

    const container = document.querySelector(normalizeSelector(selector));
    if (!container) return [];

    const exclude = toValue(options.excludeSelector);
    const found: ScrollSpyHeading[] = [];

    // The index counts every heading node, including excluded ones, so ids stay
    // stable no matter which ones are filtered out.
    container
      .querySelectorAll<HTMLElement>("h2, h3, h4, h5, h6")
      .forEach((node, index) => {
        if (exclude && node.closest(exclude)) return;

        const id = createUniqueId(node.innerText, index);
        node.id = id;
        found.push({
          id,
          text: node.innerText.trim(),
          depth: Number(node.tagName.slice(1)),
        });
      });

    return found;
  }

  function handleEntries(entries: IntersectionObserverEntry[]) {
    if (entries.length === 0) return;

    const now = Date.now();
    let hasActive = false;

    const next = items.value.map((item) => {
      const entry = entries.find(
        (candidate) => candidate.target.id === item.id,
      );
      // Items without an entry in this batch keep their state, unless they were
      // only active as a fallback — those get re-evaluated below.
      const active = entry
        ? entry.isIntersecting
        : item.active && !item.fallback;

      if (active) hasActive = true;
      if (item.active === active) return item;
      return { ...item, active, fallback: false, t: now };
    });

    // Nothing on screen (e.g. mid-way through a long code block): light up the
    // heading closest to the top of the viewport so the rail never goes blank.
    if (!hasActive && entries[0]!.rootBounds) {
      const viewTop = entries[0]!.rootBounds!.top;
      let min = Number.MAX_VALUE;
      let fallbackIdx = -1;

      for (let i = 0; i < next.length; i++) {
        const element = document.getElementById(next[i]!.id);
        if (!element) continue;

        const distance = Math.abs(
          viewTop - element.getBoundingClientRect().top,
        );
        if (distance < min) {
          fallbackIdx = i;
          min = distance;
        }
      }

      if (fallbackIdx !== -1) {
        next[fallbackIdx] = {
          ...next[fallbackIdx]!,
          active: true,
          fallback: true,
          t: now,
        };
      }
    }

    items.value = next;
  }

  function teardown() {
    observer?.disconnect();
    observer = null;
  }

  function setup() {
    teardown();

    const found = scan();
    headings.value = found;
    items.value = found.map((heading) => ({
      id: heading.id,
      active: false,
      fallback: false,
      t: 0,
    }));
    options.onHeadings?.(found);

    if (found.length === 0) return;

    // A heading counts as visible once ~all of it is on screen; several can be
    // visible at the same time, which is what lets the rail span a range.
    observer = new IntersectionObserver(handleEntries, { threshold: 0.9 });
    for (const heading of found) {
      const element = document.getElementById(heading.id);
      if (element) observer.observe(element);
    }
  }

  onMounted(async () => {
    await nextTick();
    setup();
  });

  onBeforeUnmount(teardown);

  watch(
    () => [toValue(options.contentSelector), toValue(options.excludeSelector)],
    async () => {
      await nextTick();
      setup();
    },
  );

  const activeIds = computed(() =>
    items.value.filter((item) => item.active).map((item) => item.id),
  );

  const firstActiveIndex = computed(() =>
    items.value.findIndex((item) => item.active),
  );
  const lastActiveIndex = computed(() =>
    items.value.findLastIndex((item) => item.active),
  );

  /** Heading shown as the label of the mobile trigger. */
  const currentHeading = computed(() =>
    firstActiveIndex.value === -1
      ? null
      : (headings.value[firstActiveIndex.value] ?? null),
  );

  /**
   * The heading that went active most recently. Several can be visible at once,
   * so "most recent" — not "last in the list" — is what the reader just reached.
   */
  const activeAnchorId = computed(() => {
    let latest: ObservedItem | undefined;
    for (const item of items.value) {
      if (!item.active) continue;
      if (!latest || item.t > latest.t) latest = item;
    }
    return latest?.id ?? null;
  });

  /** 0..1 — how far down the page the reader is, by heading count. */
  const progress = computed(
    () => (lastActiveIndex.value + 1) / Math.max(1, items.value.length),
  );

  function scrollToHeading(id: string) {
    document
      .getElementById(id)
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  return {
    headings,
    activeIds,
    activeAnchorId,
    currentHeading,
    firstActiveIndex,
    lastActiveIndex,
    progress,
    scrollToHeading,
    refresh: setup,
  };
}
