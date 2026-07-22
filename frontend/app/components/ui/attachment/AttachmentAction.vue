<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";
import type { ButtonVariants } from "../button";
import Button from "../button/Button.vue";

interface Props extends PrimitiveProps {
  class?: HTMLAttributes["class"];
  variant?: ButtonVariants["variant"];
  size?: ButtonVariants["size"];
}

const props = withDefaults(defineProps<Props>(), {
  variant: "ghost",
  size: "icon-xs",
});
</script>

<template>
  <Button
    data-slot="attachment-action"
    :as="as"
    :as-child="asChild"
    :variant="variant"
    :size="size"
    :class="cn('cn-attachment-action', props.class)"
  >
    <slot />
  </Button>
</template>
