/**
 * Headless scroll engine for MessageScroller, ported from the React
 * `@shadcn/react/message-scroller` primitive to framework-agnostic TypeScript.
 *
 * It owns the behaviour that is easy to get wrong in a streaming chat: pinning
 * to the bottom while a reply streams (only while the reader is already at the
 * bottom), turn anchoring (settle a new turn near the top with a peek of the
 * previous one), preserving scroll position when history is prepended, and
 * tracking which messages are visible. The Vue components drive it by setting
 * element handles on mount and forwarding scroll/resize/mutation events.
 */

export type MessageScrollerDefaultScrollPosition = "start" | "end" | "last-anchor";
export type MessageScrollerButtonDirection = "start" | "end";
export type MessageScrollerScrollAlign = "start" | "center" | "end" | "nearest";

export type MessageScrollerScrollOptions = {
  align?: MessageScrollerScrollAlign;
  behavior?: ScrollBehavior;
  scrollMargin?: number;
};

export type MessageScrollerScrollable = {
  start: boolean;
  end: boolean;
};

export type MessageScrollerVisibilityState = {
  currentAnchorId: string | null;
  visibleMessageIds: string[];
};

export type MessageScrollerEngineOptions = {
  autoScroll?: boolean;
  defaultScrollPosition?: MessageScrollerDefaultScrollPosition;
  scrollEdgeThreshold?: number;
  scrollPreviousItemPeek?: number;
  scrollMargin?: number;
};

type ScrollMode =
  | "following-bottom"
  | "free-scrolling"
  | "anchored-to-message"
  | "settling-jump";

const DEFAULT_SCROLL_EDGE_THRESHOLD = 8;
const DEFAULT_SCROLL_PREVIOUS_ITEM_PEEK = 64;
const DEFAULT_SCROLL_MARGIN = 0;
const SCROLL_EPSILON = 0.5;
const AUTOSCROLLING_TIMEOUT = 180;

const SCROLL_INTENT_KEYS = new Set([
  "ArrowDown",
  "ArrowUp",
  "End",
  "Home",
  "PageDown",
  "PageUp",
  " ",
]);

const DEFAULT_SCROLLABLE: MessageScrollerScrollable = { start: false, end: false };
const DEFAULT_VISIBILITY: MessageScrollerVisibilityState = {
  currentAnchorId: null,
  visibleMessageIds: [],
};

/* -------------------------------------------------------------------------- */
/*                              external store                                */
/* -------------------------------------------------------------------------- */

export type Store<T> = {
  getSnapshot: () => T;
  hasListeners: () => boolean;
  setSnapshot: (next: T) => void;
  subscribe: (cb: () => void, onFirst?: () => void, onLast?: () => void) => () => void;
};

function createStore<T>(initial: T, equals: (a: T, b: T) => boolean): Store<T> {
  let snapshot = initial;
  const listeners = new Set<() => void>();
  return {
    getSnapshot: () => snapshot,
    hasListeners: () => listeners.size > 0,
    setSnapshot: (next) => {
      if (!equals(snapshot, next)) {
        snapshot = next;
        listeners.forEach((l) => l());
      }
    },
    subscribe: (cb, onFirst, onLast) => {
      const wasEmpty = listeners.size === 0;
      listeners.add(cb);
      if (wasEmpty) {
        onFirst?.();
      }
      return () => {
        listeners.delete(cb);
        if (listeners.size === 0) {
          onLast?.();
        }
      };
    },
  };
}

function scrollableEquals(
  a: MessageScrollerScrollable,
  b: MessageScrollerScrollable
): boolean {
  return a.start === b.start && a.end === b.end;
}

function visibilityEquals(
  a: MessageScrollerVisibilityState,
  b: MessageScrollerVisibilityState
): boolean {
  if (a.currentAnchorId !== b.currentAnchorId) {
    return false;
  }
  if (a.visibleMessageIds.length !== b.visibleMessageIds.length) {
    return false;
  }
  return a.visibleMessageIds.every((id, i) => id === b.visibleMessageIds[i]);
}

/* -------------------------------------------------------------------------- */
/*                              dom measurement                               */
/* -------------------------------------------------------------------------- */

function parseLength(value: string | null | undefined): number {
  if (!value) {
    return 0;
  }
  const parsed = Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function getPadding(el: HTMLElement): { start: number; end: number } {
  const style = window.getComputedStyle(el);
  return {
    end: parseLength(style.paddingBlockEnd || style.paddingBottom),
    start: parseLength(style.paddingBlockStart || style.paddingTop),
  };
}

function getSpacerParentPadding(
  spacer: HTMLElement | null
): { start: number; end: number } {
  const parent = spacer?.parentElement;
  return parent ? getPadding(parent) : { start: 0, end: 0 };
}

function getRowGap(el: HTMLElement | null): number {
  if (!el) {
    return 0;
  }
  const style = window.getComputedStyle(el);
  const gap = style.rowGap === "normal" ? style.gap : style.rowGap;
  return parseLength(gap);
}

function getChildren(content: HTMLElement, spacer: HTMLElement | null): HTMLElement[] {
  return Array.from(content.children).filter(
    (child): child is HTMLElement =>
      child instanceof HTMLElement && child !== spacer
  );
}

function getOffsetTop(el: HTMLElement, viewport: HTMLElement): number {
  const r = el.getBoundingClientRect();
  const v = viewport.getBoundingClientRect();
  return r.top - v.top + viewport.scrollTop;
}

function getRelativeTop(el: HTMLElement, viewport: HTMLElement): number {
  return el.getBoundingClientRect().top - viewport.getBoundingClientRect().top;
}

function getMaxScroll(viewport: HTMLElement): number {
  return Math.max(0, viewport.scrollHeight - viewport.clientHeight);
}

function getContentExtent(
  content: HTMLElement,
  spacer: HTMLElement | null,
  viewport: HTMLElement
): number {
  const children = getChildren(content, spacer);
  const padding = getPadding(content);
  const viewportTop = viewport.getBoundingClientRect().top;
  const scrollTop = viewport.scrollTop;
  let extent = padding.start + padding.end;
  for (const child of children) {
    const rect = child.getBoundingClientRect();
    extent = Math.max(extent, rect.bottom - viewportTop + scrollTop + padding.end);
  }
  return extent;
}

function computeScrollable(
  content: HTMLElement | null,
  spacer: HTMLElement | null,
  viewport: HTMLElement | null,
  scrollEdgeThreshold: number
): MessageScrollerScrollable {
  if (!viewport || !content) {
    return DEFAULT_SCROLLABLE;
  }
  const extent = getContentExtent(content, spacer, viewport);
  return {
    start: viewport.scrollTop > scrollEdgeThreshold,
    end: extent - viewport.scrollTop - viewport.clientHeight > scrollEdgeThreshold,
  };
}

function computeVisibility(
  content: HTMLElement | null,
  spacer: HTMLElement | null,
  viewport: HTMLElement | null,
  scrollMargin: number,
  scrollPreviousItemPeek: number,
  visibleMessageIds: Set<string>
): MessageScrollerVisibilityState {
  if (!content || !viewport) {
    return DEFAULT_VISIBILITY;
  }
  const viewportRect = viewport.getBoundingClientRect();
  const anchorLine = viewportRect.top + scrollMargin + scrollPreviousItemPeek;
  const noIntersectionObserver = typeof IntersectionObserver === "undefined";
  const visible: string[] = [];
  let currentAnchorId: string | null = null;

  for (const child of getChildren(content, spacer)) {
    const id = child.dataset.messageId;
    if (!id) {
      continue;
    }
    const isAnchor = child.dataset.scrollAnchor === "true";
    const rect =
      isAnchor || noIntersectionObserver ? child.getBoundingClientRect() : null;

    const isVisible =
      noIntersectionObserver && rect
        ? rect.bottom > anchorLine && rect.top < viewportRect.bottom
        : visibleMessageIds.has(id);
    if (isVisible) {
      visible.push(id);
    }
    if (isAnchor && rect && rect.top <= anchorLine + SCROLL_EPSILON) {
      currentAnchorId = id;
    }
  }

  if (visible.length === 0 && currentAnchorId === null) {
    return DEFAULT_VISIBILITY;
  }
  return { currentAnchorId, visibleMessageIds: visible };
}

function nextAnchorFrom(
  children: HTMLElement[],
  fromIndex: number
): HTMLElement | null {
  for (let i = fromIndex; i < children.length; i++) {
    if (children[i]?.dataset.scrollAnchor === "true") {
      return children[i];
    }
  }
  return null;
}

function firstUnhandledAnchor(
  children: HTMLElement[],
  handled: WeakSet<HTMLElement>
): HTMLElement | null {
  for (const child of children) {
    if (child.dataset.scrollAnchor === "true" && !handled.has(child)) {
      return child;
    }
  }
  return null;
}

function hasMultipleAnchorsFrom(
  children: HTMLElement[],
  fromIndex: number
): boolean {
  let count = 0;
  for (let i = fromIndex; i < children.length; i++) {
    if (children[i]?.dataset.scrollAnchor === "true") {
      count += 1;
      if (count > 1) {
        return true;
      }
    }
  }
  return false;
}

function lastAnchor(children: HTMLElement[]): HTMLElement | null {
  for (let i = children.length - 1; i >= 0; i--) {
    if (children[i]?.dataset.scrollAnchor === "true") {
      return children[i];
    }
  }
  return null;
}

function firstVisibleChild(
  content: HTMLElement,
  spacer: HTMLElement | null,
  viewport: HTMLElement
): HTMLElement | null {
  const viewportRect = viewport.getBoundingClientRect();
  for (const child of getChildren(content, spacer)) {
    if (!child.dataset.messageId) {
      continue;
    }
    const rect = child.getBoundingClientRect();
    if (rect.bottom > viewportRect.top && rect.top < viewportRect.bottom) {
      return child;
    }
  }
  return null;
}

function targetScrollForElement(
  align: MessageScrollerScrollAlign,
  element: HTMLElement,
  scrollMargin: number,
  spacer: HTMLElement | null,
  viewport: HTMLElement
): number {
  const offsetTop = getOffsetTop(element, viewport);
  const height = element.getBoundingClientRect().height;
  const padding = getSpacerParentPadding(spacer);

  if (align === "center") {
    const available = Math.max(0, viewport.clientHeight - padding.start - padding.end);
    return offsetTop - padding.start - (available - height) / 2 - scrollMargin;
  }
  if (align === "end") {
    return offsetTop - viewport.clientHeight + height + padding.end + scrollMargin;
  }
  if (align === "nearest") {
    const bottom = offsetTop + height;
    const visibleTop = viewport.scrollTop + padding.start;
    const visibleBottom = viewport.scrollTop + viewport.clientHeight - padding.end;
    if (offsetTop >= visibleTop && bottom <= visibleBottom) {
      return viewport.scrollTop;
    }
    return offsetTop < visibleTop
      ? offsetTop - padding.start - scrollMargin
      : bottom - viewport.clientHeight + padding.end + scrollMargin;
  }
  return offsetTop - padding.start - scrollMargin;
}

function spacerHeightForScrollTop(
  content: HTMLElement,
  spacer: HTMLElement | null,
  viewport: HTMLElement,
  scrollTop: number
): number {
  const extent = getContentExtent(content, spacer, viewport);
  return scrollTop + viewport.clientHeight - extent;
}

/* -------------------------------------------------------------------------- */
/*                                 the engine                                 */
/* -------------------------------------------------------------------------- */

export type MessageScrollerEngine = ReturnType<typeof createMessageScrollerEngine>;

export function createMessageScrollerEngine(options: MessageScrollerEngineOptions) {
  const autoScroll = options.autoScroll ?? false;
  const defaultScrollPosition = options.defaultScrollPosition ?? "end";
  const scrollEdgeThreshold = options.scrollEdgeThreshold ?? DEFAULT_SCROLL_EDGE_THRESHOLD;
  const scrollPreviousItemPeek =
    options.scrollPreviousItemPeek ?? DEFAULT_SCROLL_PREVIOUS_ITEM_PEEK;
  const scrollMargin = options.scrollMargin ?? DEFAULT_SCROLL_MARGIN;

  // Element handles.
  let rootEl: HTMLElement | null = null;
  let viewportEl: HTMLElement | null = null;
  let contentEl: HTMLElement | null = null;
  let spacerEl: HTMLElement | null = null;

  // Mutable engine state.
  let mode: ScrollMode = autoScroll ? "following-bottom" : "free-scrolling";
  let autoscrolling = false;
  let autoscrollingTimeout: number | null = null;
  let streamingTurn: HTMLElement | null = null;
  let defaultScrollPositionApplied = false;
  let itemCount = 0;
  let firstItem: HTMLElement | null = null;
  let spacerGap = 0;
  let spacerHeight = 0;
  let preserveScrollOnPrepend = true;
  let prependRestore: { element: HTMLElement; viewportTop: number } | null = null;
  let pendingScrollToMessage:
    | { messageId: string; options: MessageScrollerScrollOptions }
    | null = null;

  const messageElements = new Map<string, HTMLElement>();
  const visibleMessageIds = new Set<string>();
  const handledScrollAnchors = new WeakSet<HTMLElement>();

  let visibilityObserver: IntersectionObserver | null = null;
  let stateFrame: number | null = null;
  let visibilityFrame: number | null = null;
  let pendingScrollFrame: number | null = null;

  const stateStore = createStore(DEFAULT_SCROLLABLE, scrollableEquals);
  const visibilityStore = createStore(DEFAULT_VISIBILITY, visibilityEquals);

  /* ----------------------------- attributes ----------------------------- */

  function applyScrollableAttributes(scrollable: MessageScrollerScrollable): void {
    const value = [scrollable.start && "start", scrollable.end && "end"]
      .filter(Boolean)
      .join(" ");
    for (const el of [rootEl, viewportEl]) {
      if (!el) {
        continue;
      }
      if (value) {
        el.setAttribute("data-scrollable", value);
      } else {
        el.removeAttribute("data-scrollable");
      }
      el.toggleAttribute("data-autoscrolling", autoscrolling);
    }
  }

  function updateModeFromScrollable(scrollable: MessageScrollerScrollable): void {
    if (autoScroll && !scrollable.end && mode !== "settling-jump") {
      mode = "following-bottom";
    } else if (mode === "following-bottom" && scrollable.end && !autoscrolling) {
      mode = "free-scrolling";
    }
  }

  function commitScrollState(): void {
    const scrollable = computeScrollable(
      contentEl,
      spacerEl,
      viewportEl,
      scrollEdgeThreshold
    );
    updateModeFromScrollable(scrollable);
    applyScrollableAttributes(scrollable);
    stateStore.setSnapshot(scrollable);
  }

  function scheduleStateCommit(): void {
    if (stateFrame === null) {
      stateFrame = window.requestAnimationFrame(() => {
        stateFrame = null;
        commitScrollState();
      });
    }
  }

  function scheduleVisibilitySync(): void {
    if (!visibilityStore.hasListeners() || visibilityFrame !== null) {
      return;
    }
    visibilityFrame = window.requestAnimationFrame(() => {
      visibilityFrame = null;
      if (visibilityStore.hasListeners()) {
        visibilityStore.setSnapshot(
          computeVisibility(
            contentEl,
            spacerEl,
            viewportEl,
            scrollMargin,
            scrollPreviousItemPeek,
            visibleMessageIds
          )
        );
      }
    });
  }

  /* ------------------------------- scrolling ------------------------------ */

  function setAutoscrolling(value: boolean): void {
    if (autoscrollingTimeout !== null) {
      window.clearTimeout(autoscrollingTimeout);
      autoscrollingTimeout = null;
    }
    if (autoscrolling !== value) {
      autoscrolling = value;
      commitScrollState();
    }
    if (value) {
      autoscrollingTimeout = window.setTimeout(() => {
        autoscrollingTimeout = null;
        autoscrolling = false;
        commitScrollState();
      }, AUTOSCROLLING_TIMEOUT);
    }
  }

  function setSpacerHeight(next: number): void {
    if (!spacerEl) {
      return;
    }
    const value = Math.max(0, Math.ceil(next));
    if (spacerHeight !== value) {
      spacerHeight = value;
      spacerEl.hidden = value === 0;
      spacerEl.style.height = `${value}px`;
      spacerEl.style.marginTop = value > 0 ? `${-spacerGap}px` : "";
    }
  }

  function scrollTo(
    top: number,
    { behavior = "auto", autoscroll = false }: { behavior?: ScrollBehavior; autoscroll?: boolean } = {}
  ): void {
    if (!viewportEl) {
      return;
    }
    const target = Math.max(0, top);
    if (Math.abs(viewportEl.scrollTop - target) <= SCROLL_EPSILON) {
      viewportEl.scrollTop = target;
      commitScrollState();
      return;
    }
    if (autoscroll) {
      setAutoscrolling(true);
    }
    viewportEl.scrollTo({ top: target, behavior });
    scheduleStateCommit();
  }

  function scrollToStart({ behavior = "auto" }: { behavior?: ScrollBehavior } = {}): boolean {
    if (!viewportEl) {
      return false;
    }
    setSpacerHeight(0);
    streamingTurn = null;
    mode = "free-scrolling";
    scrollTo(0, { behavior });
    scheduleVisibilitySync();
    return true;
  }

  function scrollToEnd({ behavior = "auto" }: { behavior?: ScrollBehavior } = {}): boolean {
    if (!viewportEl) {
      return false;
    }
    setSpacerHeight(0);
    streamingTurn = null;
    mode = autoScroll ? "following-bottom" : "free-scrolling";
    scrollTo(getMaxScroll(viewportEl), { autoscroll: true, behavior });
    scheduleVisibilitySync();
    return true;
  }

  function scrollToElement(
    element: HTMLElement,
    {
      align = "start",
      behavior = "auto",
      scrollMargin: margin = scrollMargin,
    }: MessageScrollerScrollOptions = {},
    { keepPreviousPeek = false }: { keepPreviousPeek?: boolean } = {}
  ): boolean {
    if (!contentEl || !viewportEl || !contentEl.contains(element)) {
      return false;
    }
    const target = targetScrollForElement(
      align,
      element,
      keepPreviousPeek ? margin + scrollPreviousItemPeek : margin,
      spacerEl,
      viewportEl
    );
    const requiredSpacer = spacerHeightForScrollTop(
      contentEl,
      spacerEl,
      viewportEl,
      target
    );
    setSpacerHeight(requiredSpacer);
    prependRestore = { element, viewportTop: getRelativeTop(element, viewportEl) };
    mode = keepPreviousPeek ? "anchored-to-message" : "settling-jump";
    streamingTurn = keepPreviousPeek ? element : null;
    scrollTo(target, { behavior });
    scheduleVisibilitySync();
    return true;
  }

  function reanchorToAnchoredMessage(): boolean {
    const turn = streamingTurn;
    if (!turn || !turn.isConnected || mode !== "anchored-to-message") {
      return false;
    }
    return scrollToElement(turn, { align: "start" }, { keepPreviousPeek: true });
  }

  function scrollToMessage(
    messageId: string,
    options?: MessageScrollerScrollOptions
  ): boolean {
    const element = messageElements.get(messageId);
    if (element) {
      defaultScrollPositionApplied = true;
      if (scrollToElement(element, options)) {
        pendingScrollToMessage = null;
        return true;
      }
      pendingScrollToMessage = { messageId, options: options ?? {} };
      return true;
    }
    if (itemCount === 0) {
      pendingScrollToMessage = { messageId, options: options ?? {} };
      defaultScrollPositionApplied = true;
      return true;
    }
    return false;
  }

  function flushPendingScrollToMessage(): boolean {
    const pending = pendingScrollToMessage;
    if (!pending) {
      return false;
    }
    const element = messageElements.get(pending.messageId);
    if (!element || !scrollToElement(element, pending.options)) {
      return false;
    }
    pendingScrollToMessage = null;
    defaultScrollPositionApplied = true;
    return true;
  }

  /* -------------------------- prepend preservation ------------------------ */

  function restorePrepend(): boolean {
    const restore = prependRestore;
    if (!restore || !viewportEl || !restore.element.isConnected) {
      return false;
    }
    const delta = getRelativeTop(restore.element, viewportEl) - restore.viewportTop;
    if (Math.abs(delta) <= SCROLL_EPSILON) {
      return false;
    }
    viewportEl.scrollTop += delta;
    restore.viewportTop = getRelativeTop(restore.element, viewportEl);
    scheduleStateCommit();
    scheduleVisibilitySync();
    return true;
  }

  function capturePrependAnchor(): void {
    if (!contentEl || !viewportEl) {
      prependRestore = null;
      return;
    }
    const element = firstVisibleChild(contentEl, spacerEl, viewportEl);
    prependRestore = element
      ? { element, viewportTop: getRelativeTop(element, viewportEl) }
      : null;
  }

  function schedulePendingScrollToMessageFlush(): void {
    if (pendingScrollFrame !== null) {
      return;
    }
    pendingScrollFrame = window.requestAnimationFrame(() => {
      pendingScrollFrame = null;
      if (flushPendingScrollToMessage()) {
        capturePrependAnchor();
      }
    });
  }

  /* ----------------------- default scroll position ------------------------ */

  function applyDefaultScrollPosition(): boolean {
    if (!defaultScrollPosition || defaultScrollPositionApplied || itemCount === 0) {
      return false;
    }
    let applied = false;
    if (defaultScrollPosition === "last-anchor") {
      const children =
        contentEl && viewportEl ? getChildren(contentEl, spacerEl) : null;
      const anchor = children ? lastAnchor(children) : null;
      if (!contentEl || !viewportEl || !anchor) {
        applied = scrollToEnd({ behavior: "auto" });
      } else {
        const anchorOffset = getOffsetTop(anchor, viewportEl);
        const extent = getContentExtent(contentEl, spacerEl, viewportEl);
        applied =
          extent - anchorOffset <= viewportEl.clientHeight
            ? scrollToEnd({ behavior: "auto" })
            : scrollToElement(anchor, { align: "start" }, { keepPreviousPeek: true });
      }
    } else if (defaultScrollPosition === "end") {
      applied = scrollToEnd({ behavior: "auto" });
    } else {
      applied = scrollToStart({ behavior: "auto" });
    }
    if (applied) {
      defaultScrollPositionApplied = true;
      return true;
    }
    return false;
  }

  /* ----------------------------- content change --------------------------- */

  function handleContentChange(): void {
    if (!contentEl) {
      return;
    }
    const children = getChildren(contentEl, spacerEl);
    const previousCount = itemCount;
    const previousFirst = firstItem;
    itemCount = children.length;
    firstItem = children[0] ?? null;

    const run = (): void => {
      if (flushPendingScrollToMessage()) {
        return;
      }
      if (previousCount === 0) {
        if (
          applyDefaultScrollPosition() ||
          (children.length > 0 && autoScroll && scrollToEnd({ behavior: "auto" }))
        ) {
          return;
        }
        commitScrollState();
        scheduleVisibilitySync();
        return;
      }
      const previousFirstIndex = previousFirst
        ? children.indexOf(previousFirst)
        : -1;
      if (preserveScrollOnPrepend && previousFirstIndex > 0) {
        restorePrepend();
        return;
      }
      if (children.length > previousCount) {
        const anchor = nextAnchorFrom(children, previousCount);
        if (anchor) {
          if (
            autoScroll &&
            mode === "following-bottom" &&
            hasMultipleAnchorsFrom(children, previousCount)
          ) {
            scrollToEnd({ behavior: "auto" });
            return;
          }
          scrollToElement(anchor, { align: "start" }, { keepPreviousPeek: true });
          handledScrollAnchors.add(anchor);
          return;
        }
      }
      if (children.length === previousCount) {
        const anchor = firstUnhandledAnchor(children, handledScrollAnchors);
        if (anchor) {
          scrollToElement(anchor, { align: "start" }, { keepPreviousPeek: true });
          handledScrollAnchors.add(anchor);
          return;
        }
      }
      if (mode === "following-bottom" && autoScroll) {
        scrollToEnd({ behavior: "auto" });
      } else {
        commitScrollState();
        scheduleVisibilitySync();
      }
    };

    run();
    capturePrependAnchor();
  }

  function handleResize(): void {
    if (mode === "following-bottom" && autoScroll) {
      scrollToEnd({ behavior: "auto" });
      return;
    }
    if (!reanchorToAnchoredMessage()) {
      scheduleStateCommit();
      scheduleVisibilitySync();
    }
  }

  /* ------------------------------ visibility ------------------------------ */

  function observeVisibility(): void {
    if (!viewportEl || !visibilityStore.hasListeners()) {
      return;
    }
    if (typeof IntersectionObserver === "undefined") {
      scheduleVisibilitySync();
      return;
    }
    if (!visibilityObserver) {
      visibilityObserver = new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            const id = (entry.target as HTMLElement).dataset.messageId;
            if (!id) {
              continue;
            }
            if (entry.isIntersecting) {
              visibleMessageIds.add(id);
            } else {
              visibleMessageIds.delete(id);
            }
          }
          scheduleVisibilitySync();
        },
        {
          root: viewportEl,
          rootMargin: `${-(scrollMargin + scrollPreviousItemPeek)}px 0px 0px 0px`,
          threshold: [0, 0.01, 0.5, 1],
        }
      );
    }
    messageElements.forEach((el) => visibilityObserver?.observe(el));
    scheduleVisibilitySync();
  }

  function unobserveVisibility(): void {
    if (visibilityFrame !== null) {
      window.cancelAnimationFrame(visibilityFrame);
      visibilityFrame = null;
    }
    visibilityObserver?.disconnect();
    visibilityObserver = null;
    visibleMessageIds.clear();
    visibilityStore.setSnapshot(DEFAULT_VISIBILITY);
  }

  function registerMessage(
    messageId: string,
    element: HTMLElement | null,
    previousElement: HTMLElement | null
  ): void {
    if (element) {
      messageElements.set(messageId, element);
      visibilityObserver?.observe(element);
      scheduleVisibilitySync();
      if (pendingScrollToMessage?.messageId === messageId) {
        schedulePendingScrollToMessageFlush();
      }
      return;
    }
    if (previousElement && messageElements.get(messageId) === previousElement) {
      messageElements.delete(messageId);
      visibleMessageIds.delete(messageId);
      visibilityObserver?.unobserve(previousElement);
      scheduleVisibilitySync();
    }
  }

  /* ------------------------------- intents -------------------------------- */

  function userScrollIntent(): void {
    if (
      mode === "following-bottom" ||
      mode === "anchored-to-message" ||
      mode === "settling-jump"
    ) {
      streamingTurn = null;
      mode = "free-scrolling";
    }
  }

  function syncAfterScroll(): void {
    commitScrollState();
    scheduleVisibilitySync();
    capturePrependAnchor();
  }

  function refreshAttributes(): void {
    applyScrollableAttributes(stateStore.getSnapshot());
  }

  function isIntentKey(key: string): boolean {
    return SCROLL_INTENT_KEYS.has(key);
  }

  /* ------------------------------ element setters ------------------------- */

  function setRootElement(el: HTMLElement | null): void {
    rootEl = el;
    if (el) {
      refreshAttributes();
    }
  }
  function setViewportElement(el: HTMLElement | null): void {
    viewportEl = el;
    if (el) {
      refreshAttributes();
    }
  }
  function setContentElement(el: HTMLElement | null): void {
    contentEl = el;
  }
  function setSpacerElement(el: HTMLElement | null): void {
    spacerEl = el;
    spacerGap = getRowGap(el?.parentElement ?? null);
  }
  function setPreserveScrollOnPrepend(value: boolean): void {
    preserveScrollOnPrepend = value;
  }

  /* ------------------------------ lifecycle ------------------------------- */

  function initialize(): void {
    if (!applyDefaultScrollPosition()) {
      commitScrollState();
    }
    if (autoScroll && mode === "following-bottom" && itemCount > 0) {
      scrollToEnd({ behavior: "auto" });
    }
  }

  function destroy(): void {
    for (const frame of [stateFrame, visibilityFrame, pendingScrollFrame]) {
      if (frame !== null) {
        window.cancelAnimationFrame(frame);
      }
    }
    stateFrame = null;
    visibilityFrame = null;
    pendingScrollFrame = null;
    if (autoscrollingTimeout !== null) {
      window.clearTimeout(autoscrollingTimeout);
      autoscrollingTimeout = null;
    }
    visibilityObserver?.disconnect();
    visibilityObserver = null;
  }

  return {
    stateStore,
    visibilityStore,
    setRootElement,
    setViewportElement,
    setContentElement,
    setSpacerElement,
    setPreserveScrollOnPrepend,
    handleContentChange,
    handleResize,
    syncAfterScroll,
    userScrollIntent,
    observeVisibility,
    unobserveVisibility,
    registerMessage,
    scrollToEnd,
    scrollToStart,
    scrollToMessage,
    isIntentKey,
    initialize,
    destroy,
  };
}
