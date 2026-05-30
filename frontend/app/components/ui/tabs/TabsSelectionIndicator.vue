<script setup lang="ts">
import { cn } from "@/lib/utils";
import {
  inject,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
  type HTMLAttributes,
} from "vue";
import { TABS_CONTEXT } from "./context";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();

const indicatorRef = ref<HTMLElement | null>(null);
const ctx = inject(TABS_CONTEXT, null);

let animation: Animation | null = null;
let textAnimations: Animation[] = [];
let scrollTriggers: HTMLElement[] = [];
let resizeObserver: ResizeObserver | null = null;

// Drive the pill with a scroll-driven animation tied to the carousel. We
// animate transform only (translateX + scaleX) because compositor-driven
// transforms are the reliable path for scroll timelines; layout properties
// (width, inset, anchor()) do not commit consistently under a ScrollTimeline.
function buildAnimation(): void {
  const el = indicatorRef.value;
  if (!el || !ctx?.swipeable.value) return;
  if (typeof window === "undefined" || !("ScrollTimeline" in window)) return;

  const list = el.parentElement;
  const root = el.closest<HTMLElement>("[data-slot='tabs']");
  if (!list || !root) return;
  const carousel = root.querySelector<HTMLElement>("[data-slot='tabs-carousel']");
  if (!carousel) return;

  const triggers = Array.from(
    list.querySelectorAll<HTMLElement>('[role="tab"]'),
  ).filter((t) => t.closest("[data-slot='tabs']") === root);
  if (triggers.length === 0) return;

  const maxWidth = Math.max(...triggers.map((t) => t.offsetWidth)) || 1;
  el.style.width = `${maxWidth}px`;

  const n = triggers.length;
  const keyframes = triggers.map((trigger, i) => ({
    offset: n > 1 ? i / (n - 1) : 0,
    transform: `translateX(${trigger.offsetLeft}px) scaleX(${trigger.offsetWidth / maxWidth})`,
  }));

  animation?.cancel();
  const timeline = new (window as unknown as {
    ScrollTimeline: new (opts: { source: Element; axis: string }) => AnimationTimeline;
  }).ScrollTimeline({ source: carousel, axis: "x" });
  animation = el.animate(keyframes, {
    timeline,
    fill: "both",
    easing: "linear",
  });

  // Drive the trigger text colour on the same timeline so the muted->foreground
  // crossfade tracks the scroll position in lockstep with the pill, instead of
  // snapping discretely on scrollend. CSS resolves `--tab-active` to a colour
  // via color-mix (see main.css), keeping it theme-proof.
  textAnimations.forEach((a) => a.cancel());
  textAnimations = [];

  const inactive =
    ctx?.variant.value === "pill"
      ? "var(--muted-foreground)"
      : "color-mix(in oklab, var(--muted-foreground) 80%, transparent)";

  scrollTriggers = triggers;
  triggers.forEach((trigger, j) => {
    trigger.setAttribute("data-tab-scroll", "");
    trigger.style.setProperty("--tab-inactive", inactive);
    const colorKeyframes = triggers.map((_, i) => ({
      offset: n > 1 ? i / (n - 1) : 0,
      "--tab-active": i === j ? 1 : 0,
    }));
    textAnimations.push(
      trigger.animate(colorKeyframes, {
        timeline,
        fill: "both",
        easing: "linear",
      }),
    );
  });
}

onMounted(async () => {
  await nextTick();
  buildAnimation();

  const list = indicatorRef.value?.parentElement;
  if (list && typeof ResizeObserver !== "undefined") {
    resizeObserver = new ResizeObserver(() => buildAnimation());
    resizeObserver.observe(list);
  }
});

watch(
  () => ctx?.swipeable.value,
  () => buildAnimation(),
);

onBeforeUnmount(() => {
  animation?.cancel();
  animation = null;
  textAnimations.forEach((a) => a.cancel());
  textAnimations = [];
  scrollTriggers.forEach((trigger) => {
    trigger.removeAttribute("data-tab-scroll");
    trigger.style.removeProperty("--tab-inactive");
  });
  scrollTriggers = [];
  resizeObserver?.disconnect();
});
</script>

<template>
  <div
    ref="indicatorRef"
    aria-hidden="true"
    data-slot="tabs-selection-indicator"
    :class="
      cn(
        'pointer-events-none absolute inset-y-0.5 left-0 z-0 hidden origin-left rounded-full bg-background shadow-sm dark:border dark:border-white/10 dark:bg-border/70 supports-[animation-timeline:--x]:block',
        props.class,
      )
    "
  />
</template>
