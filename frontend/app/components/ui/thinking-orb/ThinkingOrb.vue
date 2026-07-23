<script setup lang="ts">
// Ported from thinking-orbs by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/thinking-orbs
// Vue port of src/ThinkingOrb.tsx. One shared clock (performance.now)
// keeps every mounted orb in phase; each instance runs its own rAF loop
// but pauses automatically while offscreen (IntersectionObserver) or
// when the tab is hidden (visibilitychange). Reduced-motion users get a
// static representative frame that still follows the live theme.

import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";
import { computed, onMounted, ref, watchEffect } from "vue";
import { MODE_DRAWS } from "./engine/registry";
import { resolvePreset } from "./presets";
import type { OrbSize, OrbState, OrbTheme } from "./types";
import { useReducedMotion, useResolvedDark } from "./useOrbTheme";

const LABELS: Record<OrbState, string> = {
  working: "Working…",
  searching: "Searching…",
  solving: "Solving…",
  listening: "Listening…",
  composing: "Composing…",
  shaping: "Shaping…",
};

const props = withDefaults(
  defineProps<{
    /** Which animation to show. */
    state?: OrbState;
    /**
     * Rendered size in CSS px — any positive number. 64 and 20 are the
     * hand-tuned anchors; sizes in between blend their tuning, sizes
     * outside hold the nearest anchor's.
     */
    size?: OrbSize;
    /** Theme mode; `auto` detects from the host project. */
    theme?: OrbTheme;
    /** Multiplier on top of the preset's baked speed. */
    speed?: number;
    /** Freeze the animation on the current frame. */
    paused?: boolean;
    /** Overrides the per-state default label. */
    ariaLabel?: string;
    class?: HTMLAttributes["class"];
  }>(),
  {
    state: "working",
    size: 64,
    theme: "auto",
    speed: 1,
    paused: false,
  },
);

const canvasRef = ref<HTMLCanvasElement | null>(null);
const dark = useResolvedDark(() => props.theme, canvasRef);
const reduced = useReducedMotion();

const label = computed(() => props.ariaLabel ?? LABELS[props.state]);

// One sanitised size feeds both the canvas and its CSS box, so the two can
// never disagree. Anything non-finite or non-positive falls back to 64.
const px = computed(() =>
  Number.isFinite(props.size) && props.size > 0 ? props.size : 64,
);

onMounted(() => {
  // Scoped to the component: stops and runs its cleanup on unmount, and
  // re-runs whenever state / size / speed / paused / dark / reduced change.
  watchEffect((onCleanup) => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const size = px.value;
    const isDark = dark.value;
    const dpr = Math.min(
      2,
      (typeof devicePixelRatio !== "undefined" && devicePixelRatio) || 1,
    );
    canvas.width = Math.round(size * dpr);
    canvas.height = Math.round(size * dpr);
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const { mode, speed: baseSpeed, opts } = resolvePreset(props.state, size);
    const draw = MODE_DRAWS[mode];
    const effSpeed = baseSpeed * props.speed;

    const frame = (tSec: number) => {
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0, 0, size, size);
      draw(ctx, size, tSec, isDark, opts);
    };

    // reduced motion → one static, deterministic frame
    if (reduced.value) {
      frame(0.6);
      return;
    }

    const paused = props.paused;
    let raf = 0;
    let running = false;
    const loop = () => {
      frame((performance.now() / 1000) * effSpeed);
      if (running) raf = requestAnimationFrame(loop);
    };
    const start = () => {
      if (running || paused) return;
      running = true;
      raf = requestAnimationFrame(loop);
    };
    const stop = () => {
      running = false;
      cancelAnimationFrame(raf);
    };

    // draw at least one frame even when paused/offscreen
    frame((performance.now() / 1000) * effSpeed);

    // pause offscreen + on hidden tabs — free when not visible
    let visible = true;
    const io =
      typeof IntersectionObserver !== "undefined"
        ? new IntersectionObserver(([entry]) => {
            visible = !!entry?.isIntersecting;
            if (visible && document.visibilityState !== "hidden") start();
            else stop();
          })
        : null;
    io?.observe(canvas);
    const onVis = () => {
      if (document.visibilityState === "hidden") stop();
      else if (visible) start();
    };
    document.addEventListener("visibilitychange", onVis);
    if (!io) start();

    onCleanup(() => {
      stop();
      io?.disconnect();
      document.removeEventListener("visibilitychange", onVis);
    });
  });
});
</script>

<template>
  <!-- shrink-0: the canvas carries an intrinsic square size, so letting a
       flex parent squeeze it would only ever distort the drawing. -->
  <canvas
    ref="canvasRef"
    data-slot="thinking-orb"
    role="img"
    :aria-label="label"
    :class="cn('block shrink-0', props.class)"
    :style="{ width: `${px}px`, height: `${px}px` }"
  />
</template>
