<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

export interface CardConcaveProps {
  class?: HTMLAttributes["class"]
  /** Classes for the card body area */
  bodyClass?: HTMLAttributes["class"]
  /** Position of the protruding element */
  position?: "top-left" | "top-center" | "top-right"
  /** Diameter of the protruding element (CSS value) */
  size?: string
  /** How much protrudes outside the card body (CSS value). Defaults to half of size. */
  overflow?: string
  /** Distance from the card edge to the protrusion (CSS value) */
  offset?: string
  /** Gap between the protrusion circle and the card body edge (CSS value) */
  gap?: string
  /** Card body border radius (CSS value) */
  radius?: string
  /** SVG filter multiplier. Higher = sharper shape edges. */
  multiplier?: number
  /** SVG filter blur radius. Higher = smoother/larger concave corners. */
  blurRadius?: number
  /** Show border + shadow around the concave shape (default: true) */
  bordered?: boolean
  /** Border color (CSS value) */
  borderColor?: string
}

const props = withDefaults(defineProps<CardConcaveProps>(), {
  position: "top-left",
  size: "3.5rem",
  offset: "1.5rem",
  gap: "3px",
  radius: "0.75rem",
  multiplier: 10,
  blurRadius: 5,
  bordered: true,
  borderColor: "var(--color-border)",
})

const slots = useSlots()
const hasProtrusion = computed(() => !!slots.protrusion)

const uid = useId()
const shapeId = `cc-shape-${uid}`
const roundId = `cc-round-${uid}`

const shapeTableValues = computed(() => `0 ${0.5 * props.multiplier} 0`)
const roundTableValues = computed(() => `${-props.blurRadius} ${props.blurRadius + 1}`)

const containerStyle = computed<Record<string, string>>(() => {
  const style: Record<string, string> = {
    "--cc-size": props.size,
    "--cc-overflow": props.overflow || `calc(${props.size} / 2)`,
    "--cc-offset": props.offset,
    "--cc-gap": props.gap,
    "--cc-radius": props.radius,
    "--cc-m": String(props.multiplier),
  }
  if (props.bordered) {
    // Stacked drop-shadows to create a visible ~1px border + subtle shadow
    style.filter = [
      `drop-shadow(0 0 0.4px ${props.borderColor})`,
      `drop-shadow(0 0 0.4px ${props.borderColor})`,
      `drop-shadow(0 0 0.4px ${props.borderColor})`,
      `drop-shadow(0 1px 3px rgba(0,0,0,0.08))`,
      `drop-shadow(0 1px 2px rgba(0,0,0,0.06))`,
    ].join(" ")
  }
  return style
})

const cutoutStyle = computed<Record<string, string>>(() => {
  const style: Record<string, string> = {
    position: "absolute",
    width: "calc(var(--cc-size) + var(--cc-gap) * 2)",
    height: "calc(var(--cc-size) + var(--cc-gap) * 2)",
    borderRadius: "50%",
    background: "black",
    top: "calc(-1 * var(--cc-gap))",
  }

  switch (props.position) {
    case "top-left":
      style.left = "calc(var(--cc-offset) - var(--cc-gap))"
      break
    case "top-center":
      style.left = "50%"
      style.transform = "translateX(-50%)"
      break
    case "top-right":
      style.right = "calc(var(--cc-offset) - var(--cc-gap))"
      break
  }
  return style
})

const protrusionStyle = computed<Record<string, string>>(() => {
  const style: Record<string, string> = {
    position: "absolute",
    top: "0",
    width: "var(--cc-size)",
    height: "var(--cc-size)",
    zIndex: "2",
  }

  switch (props.position) {
    case "top-left":
      style.left = "var(--cc-offset)"
      break
    case "top-center":
      style.left = "50%"
      style.transform = "translateX(-50%)"
      break
    case "top-right":
      style.right = "var(--cc-offset)"
      break
  }
  return style
})
</script>

<template>
  <div :class="cn('relative', props.class)" :style="containerStyle">
    <template v-if="hasProtrusion">
      <!-- SVG Filters for concave rounding effect -->
      <svg
        aria-hidden="true"
        width="0"
        height="0"
        style="position: fixed; pointer-events: none"
      >
        <defs>
          <filter :id="shapeId">
            <feComponentTransfer>
              <feFuncA type="table" :tableValues="shapeTableValues" />
            </feComponentTransfer>
          </filter>
          <filter :id="roundId">
            <feGaussianBlur :stdDeviation="props.blurRadius" />
            <feComponentTransfer>
              <feFuncA type="table" :tableValues="roundTableValues" />
            </feComponentTransfer>
            <feComposite in="SourceGraphic" operator="in" />
          </filter>
        </defs>
      </svg>

      <!-- Filtered wrapper: creates the concave-rounded card shape -->
      <div
        :style="{
          paddingTop: 'var(--cc-overflow)',
          clipPath: 'inset(-2em)',
          filter: `url(#${shapeId}) url(#${roundId})`,
          isolation: 'isolate',
        }"
      >
        <!-- Back layer: black shapes that define card outline + protrusion cutout -->
        <div class="pointer-events-none absolute inset-0">
          <!-- Card frame: outline goes OUTSIDE, leaving inside transparent -->
          <div
            class="absolute inset-x-0 bottom-0"
            style="outline: solid 9em black"
            :style="{
              top: 'var(--cc-overflow)',
              borderRadius: 'var(--cc-radius)',
            }"
          />
          <!-- Protrusion cutout: black circle that becomes transparent after filter -->
          <div :style="cutoutStyle" />
        </div>

        <!-- Card body: content with lighten blend over back layer -->
        <div
          :class="cn('bg-card text-card-foreground relative', bodyClass)"
          :style="{
            zIndex: 1,
            opacity: `calc(1 - 1 / var(--cc-m))`,
            mixBlendMode: 'lighten',
          }"
        >
          <slot />
        </div>
      </div>

      <!-- Protruding element: outside the filter, sits visually in the concave notch -->
      <div :style="protrusionStyle">
        <slot name="protrusion" />
      </div>
    </template>

    <!-- Fallback: no protrusion, render as simple card -->
    <template v-else>
      <div
        :class="cn('bg-card text-card-foreground', bodyClass)"
        :style="{ borderRadius: 'var(--cc-radius)' }"
      >
        <slot />
      </div>
    </template>
  </div>
</template>
