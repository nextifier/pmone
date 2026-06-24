<script setup lang="ts">
import type { ToggleEmits, ToggleProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ToggleVariants } from "."
import { reactiveOmit } from "@vueuse/core"
import { Toggle, useForwardPropsEmits } from "reka-ui"
import { computed } from "vue"
import { cn } from "@/lib/utils"
import { toggleCardClass, togglePillClass, toggleVariants } from "."

const props = withDefaults(defineProps<ToggleProps & {
  class?: HTMLAttributes["class"]
  variant?: ToggleVariants["variant"] | "pill" | "card"
  size?: ToggleVariants["size"]
  // Render a radio-style dot that fills when the toggle is on. Opt-in; pairs
  // with the `card` variant for radio-card layouts (e.g. the session picker).
  indicator?: boolean
}>(), {
  variant: "default",
  size: "default",
  disabled: false,
  indicator: false,
})

const emits = defineEmits<ToggleEmits>()

const delegatedProps = reactiveOmit(props, "class", "size", "variant", "indicator")
const forwarded = useForwardPropsEmits(delegatedProps, emits)

// `pill`/`card` are resolved outside the cva (see togglePillClass/toggleCardClass);
// every other variant goes through the canonical segmented styles.
const variantClass = computed(() => {
  if (props.variant === "pill") return togglePillClass
  if (props.variant === "card") return toggleCardClass
  return toggleVariants({
    variant: props.variant as ToggleVariants["variant"],
    size: props.size,
  })
})
</script>

<template>
  <Toggle
    v-slot="slotProps"
    data-slot="toggle"
    v-bind="forwarded"
    :class="cn(indicator && 'group/toggle', variantClass, props.class)"
  >
    <!-- Radio-style indicator matching the project's <RadioGroupItem>: an empty
         bordered ring that becomes a filled primary circle with a contrast dot
         while the toggle is on. Reacts to the root's data-state via the group. -->
    <span
      v-if="indicator"
      aria-hidden="true"
      class="border-border text-primary-foreground flex size-4 shrink-0 items-center justify-center rounded-full border shadow-xs transition-colors group-data-[state=on]/toggle:border-primary group-data-[state=on]/toggle:bg-primary"
    >
      <svg
        width="6"
        height="6"
        viewBox="0 0 6 6"
        fill="currentColor"
        class="opacity-0 transition-opacity group-data-[state=on]/toggle:opacity-100"
      >
        <circle cx="3" cy="3" r="3" />
      </svg>
    </span>
    <slot v-bind="slotProps" />
  </Toggle>
</template>
