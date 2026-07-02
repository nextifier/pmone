<script setup lang="ts">
import type { toggleVariants } from "@/components/ui/toggle";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { VariantProps } from "class-variance-authority";
import type { ToggleGroupRootEmits, ToggleGroupRootProps } from "reka-ui";
import { ToggleGroupRoot, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { provide } from "vue";

type ToggleGroupVariants = VariantProps<typeof toggleVariants>;

const props = defineProps<
  ToggleGroupRootProps & {
    class?: HTMLAttributes["class"];
    variant?: ToggleGroupVariants["variant"] | "pill";
    size?: ToggleGroupVariants["size"];
    // >0 = gapped, individually-rounded items (mirrors shadcn `spacing`);
    // 0/undefined = joined segmented control (pmone default, unchanged).
    spacing?: number;
  }
>();
const emits = defineEmits<ToggleGroupRootEmits>();

provide("toggleGroup", {
  variant: props.variant,
  size: props.size,
  spacing: props.spacing,
});

const delegatedProps = reactiveOmit(props, "class", "size", "variant", "spacing");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ToggleGroupRoot
    v-slot="slotProps"
    data-slot="toggle-group"
    :data-size="size"
    :data-variant="variant"
    :data-spacing="spacing"
    :style="spacing ? { gap: `calc(${spacing} * 0.25rem)` } : undefined"
    v-bind="forwarded"
    :class="
      cn(
        'cn-toggle-group group/toggle-group flex items-center',
        variant === 'pill'
          ? 'flex-wrap gap-2'
          : spacing
            ? 'w-fit'
            : 'w-fit rounded-md data-[variant=outline]:shadow-xs',
        props.class
      )
    "
  >
    <slot v-bind="slotProps" />
  </ToggleGroupRoot>
</template>
