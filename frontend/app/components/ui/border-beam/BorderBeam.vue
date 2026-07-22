<script setup lang="ts">
// Ported from border-beam by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/border-beam
// Vue port of src/BorderBeam.tsx. The wrapper overlays the animated beam on
// whatever it wraps: `::after` paints the stroke, `::before` the inner glow
// and [data-beam-bloom] the outer bloom, all driven by per-instance CSS that
// styles.ts generates and scopes to [data-beam="<id>"].
//
// One deliberate departure from upstream: `theme` defaults to "auto" and
// resolves from the host project's data-theme/.dark class before falling back
// to prefers-color-scheme (see useBeamTheme.ts).

import { cn } from "@/lib/utils";
import { useId } from "reka-ui";
import type { HTMLAttributes } from "vue";
import {
  computed,
  onMounted,
  ref,
  watch,
  watchEffect,
  watchPostEffect,
} from "vue";
import { registerPulseInstance } from "./pulseDriver";
import {
  generateBeamCSS,
  getPulseDriverConfig,
  sizePresets,
  sizeThemePresets,
} from "./styles";
import type {
  BorderBeamColorVariant,
  BorderBeamSize,
  BorderBeamTheme,
} from "./types";
import { useResolvedBeamTheme } from "./useBeamTheme";

const props = withDefaults(
  defineProps<{
    /** Size/type preset. Rotate family: md, sm, line. Pulse family: pulse-inner, pulse-outside. */
    size?: BorderBeamSize;
    /** Color palette for the beam. */
    colorVariant?: BorderBeamColorVariant;
    /** Background adaptation; auto follows the host project's theme. */
    theme?: BorderBeamTheme;
    /** Disable the hue-shift animation for static colors. */
    staticColors?: boolean;
    /** Animation cycle duration in seconds. */
    duration?: number;
    /** Whether the animation is playing. Toggling fades in or out. */
    active?: boolean;
    /** Custom border radius in px. Auto-detected from the first child when omitted. */
    borderRadius?: number;
    /** Glow brightness multiplier. */
    brightness?: number;
    /** Glow saturation multiplier. */
    saturation?: number;
    /** Hue rotation range in degrees. */
    hueRange?: number;
    /** Overall strength of the effect (0-1). Never affects the wrapped content. */
    strength?: number;
    class?: HTMLAttributes["class"];
  }>(),
  {
    size: "md",
    colorVariant: "colorful",
    theme: "auto",
    staticColors: false,
    active: true,
    hueRange: 30,
    strength: 1,
  },
);

const emit = defineEmits<{
  /** Fade-in finished. */
  activate: [];
  /** Fade-out finished. */
  deactivate: [];
}>();

defineOptions({ inheritAttrs: false });

const id = `beam-${useId().replace(/:/g, "")}`;

const el = ref<HTMLDivElement | null>(null);
const resolvedTheme = useResolvedBeamTheme(() => props.theme, el);

const isActive = ref(props.active);
const isFading = ref(false);
const isVisible = ref(true);
const detectedRadius = ref<number | null>(null);
const pulseGlowScale = ref<{ x: number; y: number }>({ x: 1, y: 1 });

const isPulse = computed(
  () => props.size === "pulse-inner" || props.size === "pulse-outside",
);
const sizeConfig = computed(() => sizePresets[props.size]);
const themeConfig = computed(
  () => sizeThemePresets[props.size][resolvedTheme.value],
);

const finalBorderRadius = computed(
  () =>
    props.borderRadius ?? detectedRadius.value ?? sizeConfig.value.borderRadius,
);
const finalDuration = computed(
  () =>
    props.duration ??
    (props.size === "line" ? 3.1 : isPulse.value ? 2.3 : 1.96),
);
const finalSaturation = computed(
  () => props.saturation ?? themeConfig.value.saturation,
);
const finalBrightness = computed(
  () => props.brightness ?? themeConfig.value.brightness ?? 1.3,
);
const finalHueRange = computed(() =>
  props.size === "line" ? Math.min(props.hueRange, 13) : props.hueRange,
);
const finalStaticColors = computed(() =>
  props.colorVariant === "mono" ? true : props.staticColors,
);

const cssStyles = computed(() =>
  generateBeamCSS({
    id,
    borderRadius: finalBorderRadius.value,
    borderWidth: sizeConfig.value.borderWidth,
    duration: finalDuration.value,
    strokeOpacity: themeConfig.value.strokeOpacity,
    innerOpacity: themeConfig.value.innerOpacity,
    bloomOpacity: themeConfig.value.bloomOpacity,
    innerShadow: themeConfig.value.innerShadow,
    size: props.size,
    colorVariant: props.colorVariant,
    staticColors: finalStaticColors.value,
    brightness: finalBrightness.value,
    saturation: finalSaturation.value,
    hueRange: finalHueRange.value,
    theme: resolvedTheme.value,
    hairlineOpacity: themeConfig.value.hairlineOpacity,
  }),
);

// The per-instance CSS goes to <head> rather than an inline <style> in the
// template. Rendering it inline is not an option here: interpolation escapes
// the `"` and `<` the generated CSS needs (and <style> is a raw-text element,
// so the parser never decodes them back), a nested <style> tag is rejected by
// the client template compiler, and v-html on a dynamic element is dropped
// during SSR. useHead emits it raw on both sides and removes it on unmount.
useHead(
  computed(() => ({
    style: [{ key: id, innerHTML: cssStyles.value }],
  })),
);

// Runtime config for the JS breathing driver (null for non-pulse sizes).
const driverConfig = computed(() =>
  isPulse.value
    ? getPulseDriverConfig(
        props.size,
        resolvedTheme.value,
        finalDuration.value,
        finalHueRange.value,
        finalStaticColors.value,
        id,
      )
    : null,
);

const wrapperStyle = computed(() => ({
  "--beam-strength": Math.max(0, Math.min(1, props.strength)),
  ...(props.size === "pulse-outside"
    ? {
        "--pulse-glow-sx": pulseGlowScale.value.x,
        "--pulse-glow-sy": pulseGlowScale.value.y,
      }
    : {}),
}));

// Mirrors the upstream active <-> fading state machine: turning `active` off
// starts the fade-out, and the animationend handler retires the instance.
watch([() => props.active, isActive, isFading], () => {
  if (props.active && !isActive.value && !isFading.value) {
    isActive.value = true;
  } else if (!props.active && isActive.value && !isFading.value) {
    isFading.value = true;
  }
});

function onAnimationEnd(event: AnimationEvent) {
  if (event.animationName.includes("fade-out")) {
    isActive.value = false;
    isFading.value = false;
    emit("deactivate");
  } else if (event.animationName.includes("fade-in")) {
    emit("activate");
  }
}

onMounted(() => {
  // Auto-detect child border radius when no explicit value is provided.
  // Post-flush + an explicit read of `size` so a consumer that restyles the
  // wrapped element alongside the preset re-detects against the settled DOM.
  // React re-ran this on every `children` identity change; Vue slots are
  // stable, so the MutationObserver below carries the rest.
  watchPostEffect((onCleanup) => {
    if (props.borderRadius != null) return;
    void props.size;
    const host = el.value;
    if (!host) return;

    const detect = () => {
      const child = host.firstElementChild as HTMLElement | null;
      if (!child) return;
      const raw = parseFloat(getComputedStyle(child).borderTopLeftRadius);
      if (!isNaN(raw) && raw > 0) detectedRadius.value = raw;
    };

    detect();

    // Re-detect if the child is swapped out or restyled (e.g. CSS loaded late)
    const observer = new MutationObserver(detect);
    observer.observe(host, { childList: true, subtree: false });
    const child = host.firstElementChild;
    if (child) {
      observer.observe(child, {
        attributes: true,
        attributeFilter: ["class", "style"],
      });
    }
    onCleanup(() => observer.disconnect());
  });

  // Pause the (paint-heavy) animations while the element is scrolled offscreen.
  // This stops per-frame painting entirely for hidden instances without
  // changing their logical active/fading state, so it never emits
  // activate/deactivate.
  watchEffect((onCleanup) => {
    const host = el.value;
    if (!host || typeof IntersectionObserver === "undefined") return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) isVisible.value = entry.isIntersecting;
      },
      // Start animating slightly before the element scrolls into view.
      { rootMargin: "256px" },
    );

    observer.observe(host);
    onCleanup(() => observer.disconnect());
  });

  // Pulse Outside glow geometry is authored in fixed pixels for a reference
  // element (~350x140). Measure the actual wrapped element and scale the glow
  // per-axis so the halo grows/shrinks to fit any component it's applied to.
  watchEffect((onCleanup) => {
    if (props.size !== "pulse-outside") {
      pulseGlowScale.value = { x: 1, y: 1 };
      return;
    }

    const host = el.value;
    if (!host) return;

    const REF_WIDTH = 350;
    const REF_HEIGHT = 140;
    // Allow the glow to both shrink (small buttons) and grow (large cards),
    // with generous bounds to avoid degenerate geometry at the extremes.
    const MIN_SCALE = 0.35;
    const MAX_SCALE = 4;
    const clamp = (value: number) =>
      Math.max(MIN_SCALE, Math.min(MAX_SCALE, value));

    const measure = () => {
      const child = host.firstElementChild as HTMLElement | null;
      if (!child) return;
      const rect = child.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      const x = +clamp(rect.width / REF_WIDTH).toFixed(3);
      const y = +clamp(rect.height / REF_HEIGHT).toFixed(3);
      const prev = pulseGlowScale.value;
      if (prev.x !== x || prev.y !== y) pulseGlowScale.value = { x, y };
    };

    measure();
    if (typeof ResizeObserver === "undefined") return;

    const child = host.firstElementChild as HTMLElement | null;
    if (!child) return;

    const observer = new ResizeObserver(measure);
    observer.observe(child);
    onCleanup(() => observer.disconnect());
  });

  // Drive the Pulse breathing from the shared, fps-capped rAF loop while the
  // instance is on, onscreen, and the user hasn't requested reduced motion.
  watchEffect((onCleanup) => {
    const config = driverConfig.value;
    if (!config) return;
    if (!(isActive.value || isFading.value) || !isVisible.value) return;

    const host = el.value;
    if (!host) return;

    if (
      typeof matchMedia !== "undefined" &&
      matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      return;
    }

    onCleanup(registerPulseInstance(host, config));
  });
});
</script>

<template>
  <div
    v-bind="$attrs"
    ref="el"
    data-slot="border-beam"
    :data-beam="id"
    :data-active="isActive && !isFading ? '' : undefined"
    :data-fading="isFading ? '' : undefined"
    :data-paused="isActive && !isFading && !isVisible ? '' : undefined"
    :class="cn(props.class)"
    :style="wrapperStyle"
    @animationend="onAnimationEnd"
  >
    <slot />
    <div data-beam-bloom />
  </div>
</template>
