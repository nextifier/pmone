<script setup lang="ts">
import { cn } from "@/lib/utils";
import { inject, onBeforeUnmount, onMounted, ref, type HTMLAttributes } from "vue";
import { TABS_CONTEXT } from "./context";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();

const ctx = inject(TABS_CONTEXT, null);
const carouselRef = ref<HTMLElement | null>(null);

let rootEl: HTMLElement | null = null;
let observer: MutationObserver | null = null;
let scrollTimeout: ReturnType<typeof setTimeout> | undefined;
let settleTimeout: ReturnType<typeof setTimeout> | undefined;

// Guards to avoid a scroll <-> activate feedback loop.
let isProgrammaticScroll = false;
let isProgrammaticActivate = false;

function getTriggers(): HTMLElement[] {
  if (!rootEl) return [];
  return Array.from(
    rootEl.querySelectorAll<HTMLElement>('[role="tab"]:not([disabled])'),
  ).filter((t) => t.closest("[data-slot='tabs']") === rootEl);
}

function getActiveIndex(triggers: HTMLElement[]): number {
  return triggers.findIndex((t) => t.dataset.state === "active");
}

// scroll -> activate the matching tab via reka-ui's own API.
function onScrollEnd(): void {
  const el = carouselRef.value;
  if (!el) return;

  if (isProgrammaticScroll) {
    isProgrammaticScroll = false;
    return;
  }

  const width = el.clientWidth;
  if (width === 0) return;

  const idx = Math.round(el.scrollLeft / width);
  const triggers = getTriggers();
  const next = triggers[idx];
  if (!next) return;
  if (idx === getActiveIndex(triggers)) return;

  isProgrammaticActivate = true;
  // reka-ui TabsTrigger switches on @mousedown.left; click after so consumer
  // @click handlers still fire. Mirrors the existing swipe path in Tabs.vue.
  next.dispatchEvent(
    new MouseEvent("mousedown", { bubbles: true, cancelable: true, button: 0 }),
  );
  next.click();
}

// active -> scroll the matching panel into view.
function onActiveChange(): void {
  if (isProgrammaticActivate) {
    // Activation originated from a scroll gesture: already in position.
    isProgrammaticActivate = false;
    return;
  }

  const el = carouselRef.value;
  if (!el) return;

  const triggers = getTriggers();
  const activeIdx = getActiveIndex(triggers);
  if (activeIdx === -1) return;

  const panel = el.children[activeIdx] as HTMLElement | undefined;
  if (!panel) return;

  if (Math.abs(el.scrollLeft - panel.offsetLeft) <= 1) return;

  isProgrammaticScroll = true;
  el.scrollTo({ left: panel.offsetLeft, behavior: "smooth" });

  // Safety net: clear the guard if scrollend never fires (e.g. no movement).
  clearTimeout(settleTimeout);
  settleTimeout = setTimeout(() => {
    isProgrammaticScroll = false;
  }, 700);
}

function onScroll(): void {
  clearTimeout(scrollTimeout);
  scrollTimeout = setTimeout(onScrollEnd, 300);
}

onMounted(() => {
  const el = carouselRef.value;
  if (!el || !ctx?.swipeable.value) return;
  rootEl = el.closest<HTMLElement>("[data-slot='tabs']");

  if ("onscrollend" in window) {
    el.addEventListener("scrollend", onScrollEnd);
  } else {
    // Polyfill for browsers without scrollend (e.g. Safari).
    el.addEventListener("scroll", onScroll, { passive: true });
  }

  if (rootEl) {
    observer = new MutationObserver(onActiveChange);
    observer.observe(rootEl, {
      attributes: true,
      attributeFilter: ["data-state"],
      subtree: true,
    });
  }
});

onBeforeUnmount(() => {
  const el = carouselRef.value;
  el?.removeEventListener("scrollend", onScrollEnd);
  el?.removeEventListener("scroll", onScroll);
  observer?.disconnect();
  clearTimeout(scrollTimeout);
  clearTimeout(settleTimeout);
});
</script>

<template>
  <div
    ref="carouselRef"
    data-slot="tabs-carousel"
    :class="
      cn(
        'relative flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
