<script setup lang="ts">
import Spinner from "@/components/ui/spinner/Spinner.vue";
import { cn } from "@/lib/utils";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { computed } from "vue";

type ButtonVariant =
  | "default"
  | "destructive"
  | "destructive-outline"
  | "outline"
  | "secondary"
  | "ghost"
  | "link";

type ButtonSize =
  | "xs"
  | "sm"
  | "default"
  | "lg"
  | "xl"
  | "icon"
  | "icon-xs"
  | "icon-sm"
  | "icon-lg"
  | "icon-xl"
  | "inherit";

interface Props extends PrimitiveProps {
  class?: HTMLAttributes["class"];
  disabled?: boolean;
  loading?: boolean;
  variant?: ButtonVariant;
  size?: ButtonSize;
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
  disabled: false,
  loading: false,
  variant: "default",
  size: "default",
});

const emit = defineEmits<{
  click: [event: MouseEvent];
}>();

const sizeClass = computed(() =>
  props.size === "inherit" ? "" : `glass-button-size-${props.size}`
);
const variantClass = computed(() => `glass-button-variant-${props.variant}`);

function handleClick(event: MouseEvent) {
  if (!props.disabled && !props.loading) {
    emit("click", event);
  }
}
</script>

<template>
  <div :class="cn('glass-button-wrap', sizeClass, variantClass)">
    <Primitive
      :as="as"
      :as-child="asChild"
      :disabled="disabled || loading"
      :class="cn('glass-button', props.class)"
      @click="handleClick"
    >
      <span v-if="loading" class="glass-button-text glass-button-loading">
        <Spinner class="size-4 shrink-0" />
      </span>
      <span v-else class="glass-button-text">
        <slot />
      </span>
    </Primitive>
    <div class="glass-button-shadow"></div>
  </div>
</template>

<style scoped>
/* CSS Custom Properties for animations */
@property --angle-1 {
  syntax: "<angle>";
  inherits: false;
  initial-value: -75deg;
}

@property --angle-2 {
  syntax: "<angle>";
  inherits: false;
  initial-value: -45deg;
}

/* Button Wrap Container */
.glass-button-wrap {
  --anim-hover-time: 400ms;
  --anim-hover-ease: cubic-bezier(0.25, 1, 0.5, 1);
  --border-width: clamp(1px, 0.0625em, 4px);

  position: relative;
  z-index: 2;
  border-radius: 999vw;
  background: transparent;
  pointer-events: none;
  font-size: var(--global-size, 1rem);
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
}

/* Size Variants */
.glass-button-size-xs {
  --global-size: 0.625rem;
}

.glass-button-size-sm {
  --global-size: 0.75rem;
}

.glass-button-size-default {
  --global-size: 0.875rem;
}

.glass-button-size-lg {
  --global-size: 1rem;
}

.glass-button-size-xl {
  --global-size: 1.125rem;
}

/* Icon sizes - square buttons */
.glass-button-size-icon .glass-button-text,
.glass-button-size-icon-xs .glass-button-text,
.glass-button-size-icon-sm .glass-button-text,
.glass-button-size-icon-lg .glass-button-text,
.glass-button-size-icon-xl .glass-button-text {
  padding: 0.75em;
}

.glass-button-size-icon {
  --global-size: 0.875rem;
}

.glass-button-size-icon-xs {
  --global-size: 0.625rem;
}

.glass-button-size-icon-sm {
  --global-size: 0.75rem;
}

.glass-button-size-icon-lg {
  --global-size: 1rem;
}

.glass-button-size-icon-xl {
  --global-size: 1.125rem;
}

/* Button Shadow Container - Hidden by default for most variants */
.glass-button-shadow {
  display: none;
}

/* Shadow styling when enabled (for outline variant in light mode) */
.glass-button-shadow-enabled {
  --shadow-cutoff-fix: 2em;
  display: block;
  position: absolute;
  width: calc(100% + var(--shadow-cutoff-fix));
  height: calc(100% + var(--shadow-cutoff-fix));
  top: calc(0% - var(--shadow-cutoff-fix) / 2);
  left: calc(0% - var(--shadow-cutoff-fix) / 2);
  filter: blur(clamp(2px, 0.125em, 12px));
  overflow: visible;
  pointer-events: none;
}

.glass-button-shadow-enabled::after {
  content: "";
  position: absolute;
  z-index: 0;
  inset: 0;
  border-radius: 999vw;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1));
  width: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  height: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  left: calc(var(--shadow-cutoff-fix) - 0.875em);
  padding: 0.125em;
  box-sizing: border-box;
  mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  mask-composite: exclude;
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
  overflow: visible;
  opacity: 1;
}

/* Main Button - Light Mode: Dark/Black glass */
.glass-button {
  all: unset;
  cursor: pointer;
  position: relative;
  -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
  pointer-events: auto;
  z-index: 3;
  background: linear-gradient(
    -75deg,
    rgba(0, 0, 0, 0.85),
    rgba(0, 0, 0, 0.95),
    rgba(0, 0, 0, 0.85)
  );
  border-radius: 999vw;
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.1),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.3),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(clamp(1px, 0.125em, 4px));
  -webkit-backdrop-filter: blur(clamp(1px, 0.125em, 4px));
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
}

.glass-button:hover {
  transform: scale(0.975);
  backdrop-filter: blur(0.01em);
  -webkit-backdrop-filter: blur(0.01em);
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.15),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.4),
    inset 0 0 0.05em 0.1em rgba(255, 255, 255, 0.08);
}

.glass-button:disabled {
  pointer-events: none;
  opacity: 0.5;
}

/* Button Text - Light Mode: White text on dark button */
.glass-button-text {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5em;
  user-select: none;
  font-family: inherit;
  letter-spacing: -0.02em;
  font-weight: 600;
  font-size: 1em;
  color: var(--color-white, #fff);
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
  padding-inline: 1.5em;
  padding-block: 0.875em;
}

.glass-button:hover .glass-button-text {
  text-shadow: 0.025em 0.025em 0.025em rgba(255, 255, 255, 0.2);
}

/* Shine effect on text span - Light Mode: subtle white shine on dark button */
.glass-button-text::after {
  content: "";
  display: block;
  position: absolute;
  z-index: 3;
  width: calc(100% - var(--border-width));
  height: calc(100% - var(--border-width));
  top: calc(0% + var(--border-width) / 2);
  left: calc(0% + var(--border-width) / 2);
  box-sizing: border-box;
  border-radius: 999vw;
  overflow: clip;
  background: linear-gradient(
    var(--angle-2),
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.15) 40% 50%,
    rgba(255, 255, 255, 0) 55%
  );
  mix-blend-mode: screen;
  pointer-events: none;
  background-size: 200% 200%;
  background-position: 0% 50%;
  background-repeat: no-repeat;
  transition:
    background-position calc(var(--anim-hover-time) * 1.25) var(--anim-hover-ease),
    --angle-2 calc(var(--anim-hover-time) * 1.25) var(--anim-hover-ease);
}

.glass-button:hover .glass-button-text::after {
  background-position: 25% 50%;
}

.glass-button:active .glass-button-text::after {
  background-position: 50% 15%;
  --angle-2: -15deg;
}

/* Button border/outline - Light Mode: subtle light border on dark button */
.glass-button::after {
  content: "";
  position: absolute;
  z-index: 1;
  inset: 0;
  border-radius: 999vw;
  width: calc(100% + var(--border-width));
  height: calc(100% + var(--border-width));
  top: calc(0% - var(--border-width) / 2);
  left: calc(0% - var(--border-width) / 2);
  padding: var(--border-width);
  box-sizing: border-box;
  background:
    conic-gradient(
      from var(--angle-1) at 50% 50%,
      rgba(255, 255, 255, 0.3),
      rgba(255, 255, 255, 0) 5% 40%,
      rgba(255, 255, 255, 0.3) 50%,
      rgba(255, 255, 255, 0) 60% 95%,
      rgba(255, 255, 255, 0.3)
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.05));
  mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  mask-composite: exclude;
  transition:
    all var(--anim-hover-time) var(--anim-hover-ease),
    --angle-1 500ms ease;
  box-shadow: inset 0 0 0 calc(var(--border-width) / 2) rgba(255, 255, 255, 0.1);
}

.glass-button:hover::after {
  --angle-1: -125deg;
}

.glass-button:active::after {
  --angle-1: -75deg;
}

/* Shadow hover effects */
.glass-button-wrap:has(.glass-button:hover) .glass-button-shadow {
  filter: blur(clamp(2px, 0.0625em, 6px));
  transition: filter var(--anim-hover-time) var(--anim-hover-ease);
}

.glass-button-wrap:has(.glass-button:hover) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.875em);
  opacity: 1;
}

/* Active/pressed state */
.glass-button-wrap:has(.glass-button:active) {
  transform: rotate3d(1, 0, 0, 25deg);
}

.glass-button-wrap:has(.glass-button:active) .glass-button {
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.05),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.5),
    0 0.125em 0.125em -0.125em rgba(0, 0, 0, 0.3),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.03),
    0 0.225em 0.05em 0 rgba(0, 0, 0, 0.15),
    0 0.25em 0 0 rgba(0, 0, 0, 0.2),
    inset 0 0.25em 0.05em 0 rgba(0, 0, 0, 0.25);
}

.glass-button-wrap:has(.glass-button:active) .glass-button-shadow {
  filter: blur(clamp(2px, 0.125em, 12px));
}

.glass-button-wrap:has(.glass-button:active) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  opacity: 0.75;
}

.glass-button-wrap:has(.glass-button:active) .glass-button-text {
  text-shadow: 0.025em 0.25em 0.05em rgba(0, 0, 0, 0.12);
}

/* =========================
   VARIANT STYLES
   ========================= */

/* Default variant - standard glass effect (no changes needed, uses base styles) */
.glass-button-variant-default {
  /* Uses base glass styles */
}

/* Destructive variant - same glass effect as outline but with destructive background */
.glass-button-variant-destructive .glass-button {
  background: var(--color-destructive);
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.15),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.2),
    0 0.25em 0.125em -0.125em rgba(0, 0, 0, 0.3),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(clamp(1px, 0.125em, 4px));
  -webkit-backdrop-filter: blur(clamp(1px, 0.125em, 4px));
}

.glass-button-variant-destructive .glass-button:hover {
  transform: scale(0.975);
  backdrop-filter: blur(0.01em);
  -webkit-backdrop-filter: blur(0.01em);
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.2),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.25),
    0 0.2em 0.1em -0.1em rgba(0, 0, 0, 0.35),
    inset 0 0 0.05em 0.1em rgba(255, 255, 255, 0.15);
}

.glass-button-variant-destructive .glass-button-text {
  color: var(--color-white, #fff);
}

.glass-button-variant-destructive .glass-button:hover .glass-button-text {
  text-shadow: 0.025em 0.025em 0.025em rgba(0, 0, 0, 0.2);
}

.glass-button-variant-destructive .glass-button::after {
  background:
    conic-gradient(
      from var(--angle-1) at 50% 50%,
      rgba(255, 255, 255, 0.3),
      rgba(255, 255, 255, 0) 5% 40%,
      rgba(255, 255, 255, 0.3) 50%,
      rgba(255, 255, 255, 0) 60% 95%,
      rgba(255, 255, 255, 0.3)
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
  box-shadow: inset 0 0 0 calc(var(--border-width) / 2) rgba(255, 255, 255, 0.2);
}

.glass-button-variant-destructive .glass-button-text::after {
  display: none;
}

/* Enable shadow for destructive variant */
.glass-button-variant-destructive .glass-button-shadow {
  --shadow-cutoff-fix: 2em;
  display: block;
  position: absolute;
  width: calc(100% + var(--shadow-cutoff-fix));
  height: calc(100% + var(--shadow-cutoff-fix));
  top: calc(0% - var(--shadow-cutoff-fix) / 2);
  left: calc(0% - var(--shadow-cutoff-fix) / 2);
  filter: blur(clamp(2px, 0.125em, 12px));
  overflow: visible;
  pointer-events: none;
}

.glass-button-variant-destructive .glass-button-shadow::after {
  content: "";
  position: absolute;
  z-index: 0;
  inset: 0;
  border-radius: 999vw;
  background: linear-gradient(180deg, rgba(220, 38, 38, 0.3), rgba(220, 38, 38, 0.15));
  width: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  height: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  left: calc(var(--shadow-cutoff-fix) - 0.875em);
  padding: 0.125em;
  box-sizing: border-box;
  mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  mask-composite: exclude;
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
  overflow: visible;
  opacity: 1;
}

/* Shadow hover effects for destructive variant */
.glass-button-variant-destructive:has(.glass-button:hover) .glass-button-shadow {
  filter: blur(clamp(2px, 0.0625em, 6px));
}

.glass-button-variant-destructive:has(.glass-button:hover) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.875em);
  opacity: 1;
}

.glass-button-variant-destructive:has(.glass-button:active) .glass-button-shadow {
  filter: blur(clamp(2px, 0.125em, 12px));
}

.glass-button-variant-destructive:has(.glass-button:active) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  opacity: 0.75;
}

/* Destructive Outline variant - same as outline but with destructive text color */
.glass-button-variant-destructive-outline .glass-button {
  background: linear-gradient(
    -75deg,
    rgba(255, 255, 255, 0.05),
    rgba(255, 255, 255, 0.2),
    rgba(255, 255, 255, 0.05)
  );
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
    0 0.25em 0.125em -0.125em rgba(0, 0, 0, 0.2),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(clamp(1px, 0.125em, 4px));
  -webkit-backdrop-filter: blur(clamp(1px, 0.125em, 4px));
}

.glass-button-variant-destructive-outline .glass-button:hover {
  transform: scale(0.975);
  backdrop-filter: blur(0.01em);
  -webkit-backdrop-filter: blur(0.01em);
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.08),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.6),
    0 0.2em 0.1em -0.1em rgba(0, 0, 0, 0.25),
    inset 0 0 0.05em 0.1em rgba(255, 255, 255, 0.25);
}

.glass-button-variant-destructive-outline .glass-button-text {
  color: var(--color-destructive-foreground);
}

.glass-button-variant-destructive-outline .glass-button:hover .glass-button-text {
  text-shadow: 0.025em 0.025em 0.025em rgba(0, 0, 0, 0.1);
}

.glass-button-variant-destructive-outline .glass-button::after {
  background:
    conic-gradient(
      from var(--angle-1) at 50% 50%,
      rgba(0, 0, 0, 0.5),
      rgba(0, 0, 0, 0) 5% 40%,
      rgba(0, 0, 0, 0.5) 50%,
      rgba(0, 0, 0, 0) 60% 95%,
      rgba(0, 0, 0, 0.5)
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5));
  box-shadow: inset 0 0 0 calc(var(--border-width) / 2) rgba(255, 255, 255, 0.5);
}

.glass-button-variant-destructive-outline .glass-button-text::after {
  background: linear-gradient(
    var(--angle-2),
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.5) 40% 50%,
    rgba(255, 255, 255, 0) 55%
  );
  display: block;
}

/* Enable shadow for destructive-outline variant */
.glass-button-variant-destructive-outline .glass-button-shadow {
  --shadow-cutoff-fix: 2em;
  display: block;
  position: absolute;
  width: calc(100% + var(--shadow-cutoff-fix));
  height: calc(100% + var(--shadow-cutoff-fix));
  top: calc(0% - var(--shadow-cutoff-fix) / 2);
  left: calc(0% - var(--shadow-cutoff-fix) / 2);
  filter: blur(clamp(2px, 0.125em, 12px));
  overflow: visible;
  pointer-events: none;
}

.glass-button-variant-destructive-outline .glass-button-shadow::after {
  content: "";
  position: absolute;
  z-index: 0;
  inset: 0;
  border-radius: 999vw;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1));
  width: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  height: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  left: calc(var(--shadow-cutoff-fix) - 0.875em);
  padding: 0.125em;
  box-sizing: border-box;
  mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  mask-composite: exclude;
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
  overflow: visible;
  opacity: 1;
}

/* Shadow hover effects for destructive-outline variant */
.glass-button-variant-destructive-outline:has(.glass-button:hover) .glass-button-shadow {
  filter: blur(clamp(2px, 0.0625em, 6px));
}

.glass-button-variant-destructive-outline:has(.glass-button:hover) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.875em);
  opacity: 1;
}

.glass-button-variant-destructive-outline:has(.glass-button:active) .glass-button-shadow {
  filter: blur(clamp(2px, 0.125em, 12px));
}

.glass-button-variant-destructive-outline:has(.glass-button:active) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  opacity: 0.75;
}

/* Outline variant - Light Mode: Exact match with CodePen reference */
.glass-button-variant-outline .glass-button {
  background: linear-gradient(
    -75deg,
    rgba(255, 255, 255, 0.05),
    rgba(255, 255, 255, 0.2),
    rgba(255, 255, 255, 0.05)
  );
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
    0 0.25em 0.125em -0.125em rgba(0, 0, 0, 0.2),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.2),
    0 0 0 0 rgba(255, 255, 255, 1);
  backdrop-filter: blur(clamp(1px, 0.125em, 4px));
  -webkit-backdrop-filter: blur(clamp(1px, 0.125em, 4px));
}

.glass-button-variant-outline .glass-button:hover {
  transform: scale(0.975);
  backdrop-filter: blur(0.01em);
  -webkit-backdrop-filter: blur(0.01em);
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
    0 0.15em 0.05em -0.1em rgba(0, 0, 0, 0.25),
    inset 0 0 0.05em 0.1em rgba(255, 255, 255, 0.5),
    0 0 0 0 rgba(255, 255, 255, 1);
}

.glass-button-variant-outline .glass-button::after {
  background:
    conic-gradient(
      from var(--angle-1) at 50% 50%,
      rgba(0, 0, 0, 0.5),
      rgba(0, 0, 0, 0) 5% 40%,
      rgba(0, 0, 0, 0.5) 50%,
      rgba(0, 0, 0, 0) 60% 95%,
      rgba(0, 0, 0, 0.5)
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5));
  box-shadow: inset 0 0 0 calc(var(--border-width) / 2) rgba(255, 255, 255, 0.5);
}

.glass-button-variant-outline .glass-button:hover::after {
  --angle-1: -125deg;
}

.glass-button-variant-outline .glass-button:active::after {
  --angle-1: -75deg;
}

.glass-button-variant-outline .glass-button-text {
  color: var(--color-foreground);
  font-weight: 500;
  letter-spacing: -0.05em;
  text-shadow: 0em 0.25em 0.05em rgba(0, 0, 0, 0.1);
}

.glass-button-variant-outline .glass-button:hover .glass-button-text {
  text-shadow: 0em 0.2em 0.04em rgba(0, 0, 0, 0.12);
}

/* Active state for outline variant */
.glass-button-variant-outline:has(.glass-button:active) {
  transform: rotate3d(1, 0, 0, 25deg);
}

.glass-button-variant-outline:has(.glass-button:active) .glass-button {
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
    0 0.125em 0.125em -0.125em rgba(0, 0, 0, 0.2),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.2),
    0 0.225em 0.05em 0 rgba(0, 0, 0, 0.05),
    0 0.25em 0 0 rgba(255, 255, 255, 0.75),
    inset 0 0.25em 0.05em 0 rgba(0, 0, 0, 0.15);
}

.glass-button-variant-outline:has(.glass-button:active) .glass-button-text {
  text-shadow: 0.025em 0.25em 0.05em rgba(0, 0, 0, 0.12);
}

.glass-button-variant-outline .glass-button-text::after {
  background: linear-gradient(
    var(--angle-2),
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.5) 40% 50%,
    rgba(255, 255, 255, 0) 55%
  );
  display: block;
  mix-blend-mode: overlay;
  background-size: 200% 200%;
  background-position: 0% 50%;
}

.glass-button-variant-outline .glass-button:hover .glass-button-text::after {
  background-position: 25% 50%;
}

.glass-button-variant-outline .glass-button:active .glass-button-text::after {
  background-position: 50% 15%;
  --angle-2: -15deg;
}

/* Enable shadow for outline variant in light mode */
.glass-button-variant-outline .glass-button-shadow {
  --shadow-cutoff-fix: 2em;
  display: block;
  position: absolute;
  width: calc(100% + var(--shadow-cutoff-fix));
  height: calc(100% + var(--shadow-cutoff-fix));
  top: calc(0% - var(--shadow-cutoff-fix) / 2);
  left: calc(0% - var(--shadow-cutoff-fix) / 2);
  filter: blur(clamp(2px, 0.125em, 12px));
  overflow: visible;
  pointer-events: none;
}

.glass-button-variant-outline .glass-button-shadow::after {
  content: "";
  position: absolute;
  z-index: 0;
  inset: 0;
  border-radius: 999vw;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1));
  width: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  height: calc(100% - var(--shadow-cutoff-fix) - 0.25em);
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  left: calc(var(--shadow-cutoff-fix) - 0.875em);
  padding: 0.125em;
  box-sizing: border-box;
  mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  mask-composite: exclude;
  transition: all var(--anim-hover-time) var(--anim-hover-ease);
  overflow: visible;
  opacity: 1;
}

/* Shadow hover effects for outline variant */
.glass-button-variant-outline:has(.glass-button:hover) .glass-button-shadow {
  filter: blur(clamp(2px, 0.0625em, 6px));
}

.glass-button-variant-outline:has(.glass-button:hover) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.875em);
  opacity: 1;
}

.glass-button-variant-outline:has(.glass-button:active) .glass-button-shadow {
  filter: blur(clamp(2px, 0.125em, 12px));
}

.glass-button-variant-outline:has(.glass-button:active) .glass-button-shadow::after {
  top: calc(var(--shadow-cutoff-fix) - 0.5em);
  opacity: 0.75;
}

/* Secondary variant - muted glass */
.glass-button-variant-secondary .glass-button {
  background: linear-gradient(
    -75deg,
    rgba(0, 0, 0, 0.03),
    rgba(0, 0, 0, 0.08),
    rgba(0, 0, 0, 0.03)
  );
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.03),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.3),
    0 0.15em 0.1em -0.1em rgba(0, 0, 0, 0.1),
    inset 0 0 0.1em 0.15em rgba(255, 255, 255, 0.1);
}

.glass-button-variant-secondary .glass-button-text {
  color: var(--color-muted-foreground);
}

.glass-button-variant-secondary .glass-button::after {
  background:
    conic-gradient(
      from var(--angle-1) at 50% 50%,
      rgba(0, 0, 0, 0.3),
      rgba(0, 0, 0, 0) 5% 40%,
      rgba(0, 0, 0, 0.3) 50%,
      rgba(0, 0, 0, 0) 60% 95%,
      rgba(0, 0, 0, 0.3)
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.3));
  box-shadow: inset 0 0 0 calc(var(--border-width) / 2) rgba(255, 255, 255, 0.3);
}

.glass-button-variant-secondary .glass-button-shadow::after {
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.05));
}

/* Ghost variant - minimal glass */
.glass-button-variant-ghost .glass-button {
  background: transparent;
  box-shadow: none;
  backdrop-filter: none;
}

.glass-button-variant-ghost .glass-button::after {
  display: none;
}

.glass-button-variant-ghost .glass-button-text::after {
  display: none;
}

.glass-button-variant-ghost .glass-button-shadow {
  display: none;
}

.glass-button-variant-ghost .glass-button:hover {
  background: rgba(0, 0, 0, 0.05);
  backdrop-filter: blur(4px);
}

.glass-button-variant-ghost .glass-button-text {
  color: var(--color-muted-foreground);
}

/* Loading State */
.glass-button-loading {
  display: flex;
  align-items: center;
  justify-content: center;
}

.glass-button-variant-ghost .glass-button:hover .glass-button-text {
  color: var(--color-foreground);
}

/* Link variant - text only */
.glass-button-variant-link .glass-button {
  background: transparent;
  box-shadow: none;
  backdrop-filter: none;
}

.glass-button-variant-link .glass-button::after {
  display: none;
}

.glass-button-variant-link .glass-button-text::after {
  display: none;
}

.glass-button-variant-link .glass-button-shadow {
  display: none;
}

.glass-button-variant-link .glass-button-text {
  color: var(--color-primary);
  text-decoration: underline;
  text-underline-offset: 0.25em;
}

.glass-button-variant-link .glass-button:hover .glass-button-text {
  text-decoration-thickness: 2px;
}

/* =========================
   DARK MODE VARIANT OVERRIDES
   ========================= */

/* Dark mode - Destructive: Same red background as light mode */
:global(.dark .glass-button-variant-destructive .glass-button) {
  background: var(--color-destructive);
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.15),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.2),
    0 0.25em 0.125em -0.125em rgba(0, 0, 0, 0.3),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.1);
}

:global(.dark .glass-button-variant-destructive .glass-button:hover) {
  transform: scale(0.975);
  box-shadow:
    inset 0 0.125em 0.125em rgba(255, 255, 255, 0.2),
    inset 0 -0.125em 0.125em rgba(0, 0, 0, 0.25),
    0 0.2em 0.1em -0.1em rgba(0, 0, 0, 0.35),
    inset 0 0 0.05em 0.1em rgba(255, 255, 255, 0.15);
}

:global(.dark .glass-button-variant-destructive .glass-button-text) {
  color: var(--color-white, #fff);
}

/* Dark mode - Destructive Outline: Simple transparent with white border */
:global(.dark .glass-button-variant-destructive-outline .glass-button) {
  background: transparent;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
}

:global(.dark .glass-button-variant-destructive-outline .glass-button:hover) {
  transform: scale(0.975);
  background: rgba(255, 255, 255, 0.05);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
}

:global(.dark .glass-button-variant-destructive-outline .glass-button-text) {
  color: var(--color-destructive-foreground);
}

:global(.dark .glass-button-variant-destructive-outline .glass-button::after) {
  display: none;
}

:global(.dark .glass-button-variant-destructive-outline .glass-button-text::after) {
  display: none;
}

:global(.dark .glass-button-variant-destructive-outline .glass-button-shadow) {
  display: none;
}

/* Dark mode - Outline: Simple transparent with white border (matches pmone.id dark mode) */
:global(.dark .glass-button-variant-outline .glass-button) {
  background: transparent;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
}

:global(.dark .glass-button-variant-outline .glass-button:hover) {
  transform: scale(0.975);
  background: rgba(255, 255, 255, 0.05);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
}

:global(.dark .glass-button-variant-outline .glass-button::after) {
  display: none;
}

:global(.dark .glass-button-variant-outline .glass-button-text) {
  color: var(--color-foreground);
}

:global(.dark .glass-button-variant-outline .glass-button-text::after) {
  display: none;
}

:global(.dark .glass-button-variant-outline .glass-button-shadow) {
  display: none;
}

/* Dark mode - Secondary */
:global(.dark .glass-button-variant-secondary .glass-button) {
  background: rgba(255, 255, 255, 0.08);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.08),
    0 1px 3px rgba(0, 0, 0, 0.2);
}

:global(.dark .glass-button-variant-secondary .glass-button::after) {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02));
}

/* Dark mode - Ghost */
:global(.dark .glass-button-variant-ghost .glass-button:hover) {
  background: rgba(255, 255, 255, 0.08);
}

:global(.dark .glass-button-variant-ghost .glass-button-text) {
  color: var(--color-muted-foreground);
}

:global(.dark .glass-button-variant-ghost .glass-button:hover .glass-button-text) {
  color: var(--color-foreground);
}

/* Dark mode - Link */
:global(.dark .glass-button-variant-link .glass-button-text) {
  color: var(--color-primary);
}

/* Dark mode - Default variant: Opaque white/light glass effect (same visual as light mode outline) */
:global(.dark .glass-button-variant-default .glass-button) {
  background: linear-gradient(-75deg, rgb(240, 240, 240), rgb(250, 250, 250), rgb(240, 240, 240));
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.05),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.5),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(clamp(1px, 0.125em, 4px));
  -webkit-backdrop-filter: blur(clamp(1px, 0.125em, 4px));
}

:global(.dark .glass-button-variant-default .glass-button:hover) {
  transform: scale(0.975);
  background: linear-gradient(-75deg, rgb(230, 230, 230), rgb(245, 245, 245), rgb(230, 230, 230));
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.08),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.6),
    inset 0 0 0.05em 0.1em rgba(255, 255, 255, 0.2);
}

:global(.dark .glass-button-variant-default .glass-button::after) {
  background:
    conic-gradient(
      from var(--angle-1) at 50% 50%,
      rgba(0, 0, 0, 0.15),
      rgba(0, 0, 0, 0) 5% 40%,
      rgba(0, 0, 0, 0.15) 50%,
      rgba(0, 0, 0, 0) 60% 95%,
      rgba(0, 0, 0, 0.15)
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.2));
  box-shadow: inset 0 0 0 calc(var(--border-width) / 2) rgba(255, 255, 255, 0.4);
  opacity: 1;
}

:global(.dark .glass-button-variant-default .glass-button-text) {
  color: rgb(30, 30, 30);
}

:global(.dark .glass-button-variant-default .glass-button:hover .glass-button-text) {
  text-shadow: 0.025em 0.025em 0.025em rgba(0, 0, 0, 0.1);
}

:global(.dark .glass-button-variant-default .glass-button-text::after) {
  background: linear-gradient(
    var(--angle-2),
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.5) 40% 50%,
    rgba(255, 255, 255, 0) 55%
  );
  display: block;
}

:global(.dark .glass-button-variant-default .glass-button-shadow::after) {
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1));
}

:global(.dark .glass-button-variant-default:has(.glass-button:active) .glass-button) {
  background: linear-gradient(-75deg, rgb(195, 195, 195), rgb(210, 210, 210), rgb(195, 195, 195));
  box-shadow:
    inset 0 0.125em 0.125em rgba(0, 0, 0, 0.1),
    inset 0 -0.125em 0.125em rgba(255, 255, 255, 0.4),
    0 0.125em 0.125em -0.125em rgba(0, 0, 0, 0.15),
    inset 0 0 0.1em 0.25em rgba(255, 255, 255, 0.1),
    0 0.225em 0.05em 0 rgba(0, 0, 0, 0.08),
    0 0.25em 0 0 rgba(200, 200, 200, 0.5),
    inset 0 0.25em 0.05em 0 rgba(0, 0, 0, 0.08);
}
</style>
