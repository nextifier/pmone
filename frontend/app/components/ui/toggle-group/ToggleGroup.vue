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
  }
>();
const emits = defineEmits<ToggleGroupRootEmits>();

provide("toggleGroup", {
  variant: props.variant,
  size: props.size,
});

const delegatedProps = reactiveOmit(props, "class", "size", "variant");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ToggleGroupRoot
    v-slot="slotProps"
    data-slot="toggle-group"
    :data-size="size"
    :data-variant="variant"
    v-bind="forwarded"
    :class="
      cn(
        'group/toggle-group flex items-center',
        variant === 'pill'
          ? 'flex-wrap gap-2'
          : 'w-fit rounded-md data-[variant=outline]:shadow-xs',
        props.class
      )
    "
  >
    <slot v-bind="slotProps" />
  </ToggleGroupRoot>
</template>
