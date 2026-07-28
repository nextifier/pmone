<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import type { ResultContext, ResultMediaVariants, ResultStatus, ResultVariants } from ".";
import { computed, provide, useSlots } from "vue";
import { cn } from "@/lib/utils";
import { RESULT_CONTEXT, resultVariants } from ".";
import ResultDescription from "./ResultDescription.vue";
import ResultHeader from "./ResultHeader.vue";
import ResultMedia from "./ResultMedia.vue";
import ResultTitle from "./ResultTitle.vue";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    status?: ResultStatus;
    variant?: ResultMediaVariants["variant"];
    size?: ResultVariants["size"];
    /** Shorthand: renders ResultTitle for you. Omit and compose by hand instead. */
    title?: string;
    /** Shorthand: renders ResultDescription for you. */
    description?: string;
    /** Overrides the status glyph. Also opts out of the drawn checkmark. */
    icon?: string;
    /** Heading level for the shorthand title. Outcome pages usually want "h1". */
    titleAs?: string;
    /** Entrance animation. Turning it off is one attribute, read by CSS below. */
    animate?: boolean;
    /** Wraps the header in role="status" so a screen reader announces the outcome. */
    announce?: boolean;
  }>(),
  {
    status: "success",
    variant: "soft",
    size: "default",
    titleAs: "h2",
    animate: true,
    announce: true,
  },
);

const slots = useSlots();

/**
 * The root renders a shorthand header, then always renders the default slot
 * untouched. It never re-parents slot content, so adding `title` to an existing
 * Result cannot change the layout of children you did not touch.
 */
const hasHeader = computed(
  () =>
    Boolean(props.title || props.description || props.icon) ||
    Boolean(slots.media || slots.title || slots.description),
);

provide<ResultContext>(RESULT_CONTEXT, {
  get status() {
    return props.status;
  },
  get variant() {
    return props.variant ?? "soft";
  },
  get size() {
    return props.size ?? "default";
  },
  get announce() {
    return props.announce;
  },
});
</script>

<template>
  <div
    data-slot="result"
    :data-status="status"
    :data-result-animate="animate ? '' : undefined"
    :class="cn(resultVariants({ size }), props.class)"
  >
    <ResultHeader v-if="hasHeader">
      <slot name="media">
        <ResultMedia :icon="icon" />
      </slot>
      <slot name="title">
        <ResultTitle v-if="title" :as="titleAs">{{ title }}</ResultTitle>
      </slot>
      <slot name="description">
        <ResultDescription v-if="description">{{ description }}</ResultDescription>
      </slot>
    </ResultHeader>

    <slot />
  </div>
</template>

<style scoped>
/* Texts reveal (transitions.dev 18), same tokens as layers/base/app/error.vue.
   Implemented as an auto-playing keyframe rather than a JS `.is-shown` toggle so
   the outcome is readable without JS and during SSR - a success screen must never
   depend on hydration to become visible.

   TWO RULES, do not break them:
   1. `@keyframes result-reveal` and the `animation:` declaration must stay in
      this same scoped block. Vue's SFC compiler renames the keyframe and rewrites
      the matching `animation` shorthand together; split them and the name no
      longer resolves.
   2. Never reference `result-reveal` from a Tailwind `animate-[...]` utility in a
      template. That class string is emitted into the global stylesheet and never
      sees the rename. (See apps/ui .../main.css for the same warning.) */
[data-slot="result"] {
  /* Texts reveal (transitions.dev 18) */
  --result-dur: 500ms;
  --result-distance: 12px;
  --result-stagger: 40ms;
  --result-blur: 3px;
  --result-ease: var(--panel-ease, cubic-bezier(0.22, 1, 0.36, 1));

  /* Success check (transitions.dev 10) */
  --result-check-dur: 500ms;
  --result-check-rotate-from: 80deg;
  --result-check-y: 40px;
  --result-check-blur-from: 10px;
  --result-check-draw-dur: 500ms;
  --result-check-draw-delay: 80ms;
  --result-check-len: 21;
  --result-check-ease: var(--panel-ease, cubic-bezier(0.22, 1, 0.36, 1));
  --result-check-ease-bob: cubic-bezier(0.34, 1.35, 0.64, 1);
}

/* Base state for the drawn checkmark, applied whether or not it animates: a 21
   dasharray on a 20.524 path is one solid dash, so with dashoffset 0 the check
   just reads as a normal stroke. Only the keyframe below moves the offset. */
[data-slot="result"] :deep([data-result-draw]) {
  stroke-dasharray: var(--result-check-len);
}

/* Scoped + :deep() compiles to `[data-v-x] [data-result-reveal]`, and the hash
   lands on this root - so it reaches slotted parts too, which is what full
   composition needs. Parts carry their own `--result-i`; a consumer can override
   one with an inline style because fallthrough style merges after the template's. */
[data-result-animate] :deep([data-result-reveal]) {
  opacity: 0;
  animation: result-reveal var(--result-dur) var(--result-ease) both;
  animation-delay: calc(var(--result-stagger) * var(--result-i, 0));
  will-change: transform, opacity, filter;
}

@keyframes result-reveal {
  from {
    opacity: 0;
    transform: translateY(var(--result-distance));
    filter: blur(var(--result-blur));
  }
  to {
    opacity: 1;
    transform: translateY(0);
    filter: blur(0);
  }
}

/* Success check (transitions.dev 10). The media box gets a richer entrance than
   the text lines - four parallel tracks, so the bob can overshoot on its own
   curve while the fade stays linear-ish. `transform` carries the rotation and
   `translate` carries the bob precisely so the two never fight over one property. */
[data-result-animate] :deep([data-result-check]) {
  animation:
    result-check-fade var(--result-check-dur) var(--result-check-ease) both,
    result-check-rotate var(--result-check-dur) var(--result-check-ease) both,
    result-check-blur var(--result-check-dur) var(--result-check-ease) both,
    result-check-bob var(--result-check-dur) var(--result-check-ease-bob) both;
  will-change: transform, translate, opacity, filter;
}

[data-result-animate] :deep([data-result-check] [data-result-draw]) {
  animation: result-check-draw var(--result-check-draw-dur) var(--result-check-ease)
    var(--result-check-draw-delay) both;
}

@keyframes result-check-fade {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes result-check-rotate {
  from {
    transform: rotate(var(--result-check-rotate-from));
  }
  to {
    transform: rotate(0deg);
  }
}

@keyframes result-check-blur {
  from {
    filter: blur(var(--result-check-blur-from));
  }
  to {
    filter: blur(0);
  }
}

@keyframes result-check-bob {
  from {
    translate: 0 var(--result-check-y);
  }
  to {
    translate: 0 0;
  }
}

@keyframes result-check-draw {
  from {
    stroke-dashoffset: var(--result-check-len);
  }
  to {
    stroke-dashoffset: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  [data-result-animate] :deep([data-result-reveal]),
  [data-result-animate] :deep([data-result-check]),
  [data-result-animate] :deep([data-result-check] [data-result-draw]) {
    animation: none;
    opacity: 1;
  }
}
</style>
