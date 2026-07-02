<script lang="ts" setup>
import { computed, nextTick, watch } from "vue";
import { Toaster as Sonner, useVueSonner, type ToasterProps } from "vue-sonner";

const props = withDefaults(defineProps<ToasterProps & { progressBar?: boolean }>(), {
  progressBar: true,
});

const showProgress = computed(() => props.progressBar && !props.expand);

const forwardedProps = computed(() => {
  const { progressBar: _progressBar, class: _class, style: _style, ...rest } = props;
  return rest;
});

/**
 * vue-sonner manages each toast's auto-dismiss with a JS timer and never exposes
 * its duration to the DOM. We read the live toast list and stamp the resolved
 * duration onto each <li> as --progress-duration, so the fill matches per-toast
 * custom durations (toast.x(msg, { duration })) — not just the global default.
 * The animation is gated behind [data-progress-ready] so it only starts once the
 * correct duration is set, avoiding a mid-animation jump. Infinite / loading
 * toasts never get the marker, so they show no bar.
 */
const { activeToasts } = useVueSonner();

const syncDurations = async (): Promise<void> => {
  if (!showProgress.value) {
    return;
  }
  await nextTick();
  const root = document.querySelector("[data-sonner-toaster].sonner-progress");
  if (!root) {
    return;
  }
  const toasts = activeToasts.value.filter((toast) => !toast.delete);
  const nodes = [...root.querySelectorAll<HTMLElement>("[data-sonner-toast]")].filter(
    (node) => node.getAttribute("data-removed") !== "true"
  );
  nodes.forEach((node, index) => {
    const toast = toasts[index];
    if (!toast) {
      return;
    }
    const duration = toast.duration ?? props.duration ?? 4000;
    const hasAutoDismiss = Number.isFinite(duration) && toast.type !== "loading";
    if (!hasAutoDismiss) {
      node.removeAttribute("data-progress-ready");
      return;
    }
    node.style.setProperty("--progress-duration", `${duration}ms`);
    node.setAttribute("data-progress-ready", "");
  });
};

watch(activeToasts, syncDurations, { deep: true, flush: "post" });
</script>

<template>
  <Sonner
    v-bind="forwardedProps"
    :class="['toaster group tracking-tight', props.class, { 'sonner-progress': showProgress }]"
    :toast-options="{ classes: { toast: 'cn-toast' } }"
    :style="[
      {
        '--normal-bg': 'var(--popover)',
        '--normal-text': 'var(--popover-foreground)',
        '--normal-border': 'var(--border)',
        '--progress-duration': `${props.duration ?? 4000}ms`,
      },
      props.style,
    ]"
  >
    <template #info-icon>
      <slot name="info-icon">
        <Icon name="lucide:info" />
      </slot>
    </template>

    <template #success-icon>
      <slot name="success-icon">
        <Icon name="lucide:circle-check-big" />
      </slot>
    </template>

    <template #warning-icon>
      <slot name="warning-icon">
        <Icon name="lucide:triangle-alert" />
      </slot>
    </template>

    <template #error-icon>
      <slot name="error-icon">
        <Icon name="lucide:octagon-x" />
      </slot>
    </template>

    <template #loading-icon>
      <Spinner class="text-background size-4 shrink-0" />
    </template>
  </Sonner>
</template>

<style>
/* Auto-dismiss progress fill. Global (not scoped): the toast <li> is rendered by
   vue-sonner's internal component and never receives this SFC's data-v scope id,
   and the Toaster uses inheritAttrs:false. Everything is scoped under
   [data-sonner-toaster].sonner-progress so the blast radius stays contained.
   The fill grows via transform: scaleX() (GPU-composited — smooth and cheap);
   --progress-duration is stamped per-toast from the script above. */

/* Clip the square fill to the toast's rounded corners so it never pokes out.
   overflow:clip respects border-radius but leaves the toast's box-shadow intact. */
[data-sonner-toaster].sonner-progress [data-sonner-toast] {
  overflow: clip;
}

[data-sonner-toaster].sonner-progress [data-sonner-toast]::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  transform: scaleX(0);
  transform-origin: left center;
  will-change: transform;
  /* currentColor = the toast's own text color, so the fill always contrasts with
     the toast surface regardless of theme (light/dark) or rich-color variant. */
  background-color: var(
    --sonner-progress-color,
    color-mix(in oklab, currentColor 10%, transparent)
  );
}

/* Only animate once the per-toast duration has been stamped (no first-frame jump). */
[data-sonner-toaster].sonner-progress [data-sonner-toast][data-progress-ready]::before {
  animation: sonner-progress var(--progress-duration, 4000ms) linear forwards;
}

@keyframes sonner-progress {
  from {
    transform: scaleX(0);
  }
  to {
    transform: scaleX(1);
  }
}

/* Keep toast content above the fill ([data-icon] is already position:relative). */
[data-sonner-toaster].sonner-progress [data-sonner-toast] [data-icon],
[data-sonner-toaster].sonner-progress [data-sonner-toast] [data-content],
[data-sonner-toaster].sonner-progress [data-sonner-toast] [data-button] {
  position: relative;
  z-index: 1;
}

/* Pause in sync with vue-sonner's timer on hover (data-expanded) and swipe. */
[data-sonner-toaster].sonner-progress [data-sonner-toast][data-expanded="true"]::before,
[data-sonner-toaster].sonner-progress [data-sonner-toast][data-swiping="true"]::before {
  animation-play-state: paused;
}

/* Loading toasts have no auto-dismiss, so no progress. */
[data-sonner-toaster].sonner-progress [data-sonner-toast][data-type="loading"]::before {
  display: none;
}

@media (prefers-reduced-motion: reduce) {
  [data-sonner-toaster].sonner-progress [data-sonner-toast]::before {
    animation: none;
    transform: scaleX(0);
    will-change: auto;
  }
}
</style>
