<script lang="ts">
// Ported from metal-fx by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/metal-fx
// Vue port of src/MetalFx.tsx. Wraps any element with an animated metallic
// ring driven by a single shared WebGL renderer: every visible instance
// composites a cropped/scaled copy of one offscreen GL canvas onto its own 2D
// canvas with a rounded hole punched through the centre.
//
// This plain <script> block holds the module-scope work upstream does at the
// top of MetalFx.tsx — it must run once per module, not once per instance.

import type { MetalFxInstance } from "./engine/renderer/core";
import { injectGlow, updateGlow } from "./engine/glow/glow";
import { setGlowCallback } from "./engine/renderer/loop";
import { ensureStylesInjected } from "./styles";

// Runs at module scope so styles exist before the first component render,
// even in SSR-hydration scenarios where effects haven't fired yet.
ensureStylesInjected();

type GlowHandles = ReturnType<typeof injectGlow>;
type ThemeRef = { current: "dark" | "light" };

// Maps each live instance to its SVG glow handles and a theme ref.
// Keyed by instance (not component) because the same component can be
// remounted with a new instance after shape/glowEnabled changes.
export const glowHandlesMap = new Map<
  MetalFxInstance,
  { handles: GlowHandles; themeRef: ThemeRef }
>();

// Bridge between the shared animation loop and per-instance glow SVGs.
// The loop module doesn't import glow directly — it invokes this callback
// for one queued instance per frame (round-robin), keeping render work
// proportional to frame budget regardless of instance count.
setGlowCallback((inst, nowMs) => {
  const entry = glowHandlesMap.get(inst);
  if (!entry) return;
  updateGlow(
    entry.handles,
    inst,
    nowMs,
    inst.opacityMul,
    entry.themeRef.current,
  );
});
</script>

<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { CSSProperties, HTMLAttributes, Ref } from "vue";
import {
  computed,
  onMounted,
  onScopeDispose,
  ref,
  shallowRef,
  watch,
} from "vue";
import {
  addReflectionTarget,
  removeReflectionTarget,
} from "./engine/reflection/paint";
import { scheduleReflectionPaint } from "./engine/reflection/reflectionScheduler";
import {
  createInstance,
  destroyInstance,
  registerGlowInstance,
  setInstanceVisible,
  setSharedPreset,
  unregisterGlowInstance,
  updateInstance,
} from "./engine/renderer/loop";
import type { MetalFxPreset, MetalFxTheme, MetalFxVariant } from "./types";
import { useResolvedMetalTheme } from "./useMetalTheme";

const props = withDefaults(
  defineProps<{
    /** Shader sampling scale + ring width preset. */
    variant?: MetalFxVariant;
    /** Color preset. Each ships both dark and light tunings. */
    preset?: MetalFxPreset;
    /** Theme mode; auto follows the host project's theme. */
    theme?: MetalFxTheme;
    /** Effect strength (0..1). Scales the composited alpha, never the child. */
    strength?: number;
    /** Freeze this instance on its last painted frame. */
    paused?: boolean;
    /** Explicit corner radius in CSS px. Read from the child when omitted. */
    borderRadius?: number;
    /** Normalize the host element's border / outline / box-shadow. */
    normalizeHostStyles?: boolean;
    /** Neighbour elements that receive a soft proximity reflection (dark only). */
    reflectionTargets?: ReadonlyArray<Ref<HTMLElement | null | undefined>>;
    /** Disable the wandering halo overlay. The ring still renders. */
    disableGlow?: boolean;
    /** Override the shader sampling scale. */
    shaderScale?: number;
    /** Override the ring thickness in CSS px. */
    ringCssPx?: number;
    /** Master multiplier for every absolute-pixel constant the engine uses. */
    scale?: number;
    class?: HTMLAttributes["class"];
  }>(),
  {
    variant: "button",
    preset: "chromatic",
    theme: "auto",
    strength: 1,
    paused: false,
    normalizeHostStyles: true,
    disableGlow: false,
    scale: 1,
  },
);

defineOptions({ inheritAttrs: false });

// DOM refs — root/canvas/glowHost/content are for direct DOM access.
// instance/glowHandles hold engine objects and use shallowRef so Vue never
// proxies the renderer's internals. themeRef lets the glow callback read the
// current theme without closing over a stale value.
const rootRef = ref<HTMLDivElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const glowHostRef = ref<HTMLDivElement | null>(null);
const contentRef = ref<HTMLDivElement | null>(null);
const instanceRef = shallowRef<MetalFxInstance | null>(null);
const glowHandlesRef = shallowRef<GlowHandles | null>(null);
const themeRef: ThemeRef = { current: "dark" };
let initialWrapperRadius = 0;

const ready = ref(false);
const resolvedTheme = useResolvedMetalTheme(() => props.theme, rootRef);

const shape = computed<"pill" | "circle">(() =>
  props.variant === "circle" ? "circle" : "pill",
);
const glowEnabled = computed(() => !props.disableGlow);

// Kept in sync eagerly so the glow callback always sees the current theme.
watch(resolvedTheme, (value) => (themeRef.current = value), {
  immediate: true,
});

function resolveRadius(w: number, h: number) {
  // variant='circle' is the caller's explicit promise that the wrapped element
  // renders as a circle. Always pick min(w,h)/2 so the engine produces a true
  // circle even when the child's CSS border-radius is read in a different
  // coordinate space than the bounding rect (the exact failure mode under CSS
  // `zoom: 2`, where getComputedStyle returns source pixels but
  // getBoundingClientRect returns zoomed ones).
  if (shape.value === "circle") return Math.min(w, h) / 2;

  const raw =
    typeof props.borderRadius === "number"
      ? props.borderRadius
      : (() => {
          const childEl = contentRef.value
            ?.firstElementChild as HTMLElement | null;
          if (childEl) {
            const parsed = parseFloat(
              getComputedStyle(childEl).borderTopLeftRadius,
            );
            if (Number.isFinite(parsed) && parsed > 0) return parsed;
          }
          return initialWrapperRadius;
        })();
  return Math.min(raw, Math.min(w, h) / 2);
}

// --- lifecycle: one instance per shape -------------------------------------
let ro: ResizeObserver | null = null;
let io: IntersectionObserver | null = null;
let resizeRaf = 0;

function mountInstance() {
  const canvas = canvasRef.value;
  const root = rootRef.value;
  const glowHost = glowHostRef.value;
  if (!canvas || !root) return;

  {
    const parsed = parseFloat(getComputedStyle(root).borderTopLeftRadius);
    initialWrapperRadius = Number.isFinite(parsed) ? parsed : 0;
  }

  const measure = () => {
    const rect = root.getBoundingClientRect();
    const cssWidth = Math.max(1, Math.round(rect.width));
    const cssHeight = Math.max(1, Math.round(rect.height));
    return {
      cssWidth,
      cssHeight,
      cornerRadius: resolveRadius(cssWidth, cssHeight),
    };
  };

  const initial = measure();
  instanceRef.value = createInstance({
    hostCanvas: canvas,
    cssWidth: initial.cssWidth,
    cssHeight: initial.cssHeight,
    cornerRadius: initial.cornerRadius,
    kind: shape.value,
    paused: props.paused,
    shaderScale: props.shaderScale,
    ringCssPx: props.ringCssPx,
    scale: props.scale,
    onFirstCopy: () => (ready.value = true),
  });
  root.style.setProperty("--mfx-radius", `${initial.cornerRadius}px`);
  root.style.borderRadius = `${initial.cornerRadius}px`;

  if (glowHost) {
    glowHandlesRef.value = injectGlow(glowHost, {
      width: initial.cssWidth,
      height: initial.cssHeight,
      cornerRadius: initial.cornerRadius,
      kind: shape.value,
      scale: props.scale,
    });
  }

  ro = new ResizeObserver(() => {
    if (resizeRaf !== 0) return;
    // RAF-debounce: coalesce multiple resize events within the same frame and
    // skip any that fire while a frame is already queued.
    resizeRaf = requestAnimationFrame(() => {
      resizeRaf = 0;
      const next = measure();
      const inst = instanceRef.value;
      if (!inst) return;
      updateInstance(inst, {
        cssWidth: next.cssWidth,
        cssHeight: next.cssHeight,
        cornerRadius: next.cornerRadius,
      });
      root.style.setProperty("--mfx-radius", `${next.cornerRadius}px`);
      root.style.borderRadius = `${next.cornerRadius}px`;
      if (glowHost) {
        glowHost.innerHTML = "";
        glowHandlesRef.value = injectGlow(glowHost, {
          width: next.cssWidth,
          height: next.cssHeight,
          cornerRadius: next.cornerRadius,
          kind: shape.value,
          scale: props.scale,
        });
        if (glowHandlesRef.value) {
          glowHandlesMap.set(inst, {
            handles: glowHandlesRef.value,
            themeRef,
          });
        }
      }
    });
  });
  ro.observe(root);

  // Skip GL compositing for off-screen instances — the loop checks
  // inst.visible before copyShaderToInstance, so hidden instances cost nothing
  // per frame. rootMargin: 64px starts rendering slightly before the element
  // scrolls into view.
  if (typeof IntersectionObserver !== "undefined") {
    io = new IntersectionObserver(
      (entries) => {
        const inst = instanceRef.value;
        if (!inst) return;
        for (const e of entries) setInstanceVisible(inst, e.isIntersecting);
      },
      { rootMargin: "64px" },
    );
    io.observe(root);
  }

  if (instanceRef.value && glowHandlesRef.value) {
    glowHandlesMap.set(instanceRef.value, {
      handles: glowHandlesRef.value,
      themeRef,
    });
    registerGlowInstance(instanceRef.value);
  }
}

function unmountInstance() {
  ro?.disconnect();
  ro = null;
  io?.disconnect();
  io = null;
  if (resizeRaf !== 0) {
    cancelAnimationFrame(resizeRaf);
    resizeRaf = 0;
  }
  const inst = instanceRef.value;
  if (inst) {
    glowHandlesMap.delete(inst);
    unregisterGlowInstance(inst);
    destroyInstance(inst);
  }
  instanceRef.value = null;
  glowHandlesRef.value = null;
  if (glowHostRef.value) glowHostRef.value.innerHTML = "";
}

onMounted(() => {
  mountInstance();

  watch(shape, () => {
    unmountInstance();
    ready.value = false;
    mountInstance();
  });

  watch(
    [() => props.preset, resolvedTheme],
    () => setSharedPreset(props.preset, resolvedTheme.value),
    { immediate: true },
  );

  // `paused` is per-instance: it freezes only this instance's 2D canvas while
  // the shared GL loop keeps running for any other unpaused instance.
  watch(
    () => props.paused,
    (paused) => {
      const inst = instanceRef.value;
      if (inst) updateInstance(inst, { paused });
    },
  );

  // Re-sync optional shader/ring/scale overrides if they change at runtime.
  watch(
    [() => props.shaderScale, () => props.ringCssPx, () => props.scale],
    () => {
      const inst = instanceRef.value;
      if (!inst) return;
      const patch: Partial<Parameters<typeof updateInstance>[1]> = {};
      if (props.shaderScale !== undefined)
        patch.shaderScale = props.shaderScale;
      if (props.ringCssPx !== undefined) patch.ringCssPx = props.ringCssPx;
      if (props.scale !== undefined) patch.scale = props.scale;
      if (Object.keys(patch).length > 0) updateInstance(inst, patch);
    },
  );

  // strength=1 maps directly to a full-opacity composite (opacityMul=1) for
  // every variant. Per-preset toning lives in `shaderOpacity` inside each
  // PresetMode, not here, so buttons and circles share the same headroom.
  watch(
    [() => props.strength, () => props.variant],
    () => {
      const inst = instanceRef.value;
      if (!inst) return;
      updateInstance(inst, {
        opacityMul: Math.max(0, Math.min(1, props.strength)),
      });
    },
    { immediate: true },
  );

  // onAfterFrame is wired here rather than at createInstance time so instances
  // without reflectionTargets never schedule the reflection RAF.
  // Reflections are dark-mode only — no DOM work in light mode.
  watch(
    [() => props.reflectionTargets, resolvedTheme],
    (_value, _old, onCleanup) => {
      const inst = instanceRef.value;
      const root = rootRef.value;
      if (
        !inst ||
        !root ||
        !props.reflectionTargets ||
        resolvedTheme.value !== "dark"
      ) {
        return;
      }
      inst.onAfterFrame = scheduleReflectionPaint;
      const live = props.reflectionTargets.flatMap((r) =>
        r.value ? [r.value] : [],
      );
      for (const el of live) addReflectionTarget(el, inst, root);
      onCleanup(() => {
        inst.onAfterFrame = undefined;
        for (const el of live) removeReflectionTarget(el);
      });
    },
    { immediate: true, flush: "post" },
  );

  // Separate from the main lifecycle so borderRadius / variant / theme changes
  // re-sync the radius without destroying and recreating the instance.
  watch([() => props.borderRadius, resolvedTheme, () => props.variant], () => {
    const root = rootRef.value;
    const inst = instanceRef.value;
    if (!root || !inst) return;
    const cornerRadius = resolveRadius(inst.cssWidth, inst.cssHeight);
    updateInstance(inst, { cornerRadius });
    root.style.setProperty("--mfx-radius", `${cornerRadius}px`);
    root.style.borderRadius = `${cornerRadius}px`;
  });
});

onScopeDispose(unmountInstance);

// --mfx-strength is consumed by downstream CSS (e.g. content opacity rules).
const wrapperStyle = computed<CSSProperties>(() => ({
  "--mfx-strength": String(Math.min(1, Math.max(0, props.strength))),
  opacity: ready.value ? 1 : 0,
  visibility: ready.value ? "visible" : "hidden",
  transition: ready.value ? "opacity 0.15s ease-out" : "none",
}));

defineExpose({ el: rootRef });
</script>

<template>
  <div
    v-bind="$attrs"
    ref="rootRef"
    data-slot="metal-fx"
    :class="cn('metal-fx-root', props.class)"
    :data-variant="variant"
    :data-shape="shape"
    :data-theme="resolvedTheme"
    :data-paused="paused ? 'true' : undefined"
    :data-normalize="normalizeHostStyles ? 'true' : 'false'"
    :style="wrapperStyle"
  >
    <canvas
      ref="canvasRef"
      class="metal-fx-canvas"
      style="position: absolute; inset: 0; width: 100%; height: 100%"
    />
    <div
      class="metal-fx-inner"
      aria-hidden="true"
      style="position: absolute; inset: 3px"
    />
    <div
      ref="glowHostRef"
      aria-hidden="true"
      :style="{
        position: 'absolute',
        inset: 0,
        pointerEvents: 'none',
        zIndex: 3,
        borderRadius: 'inherit',
        display: glowEnabled ? undefined : 'none',
      }"
    />
    <div ref="contentRef" class="metal-fx-content"><slot /></div>
  </div>
</template>
