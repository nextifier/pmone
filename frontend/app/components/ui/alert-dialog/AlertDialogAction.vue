<script setup lang="ts">
import { buttonVariants, type ButtonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { AlertDialogActionProps } from "reka-ui";
import { AlertDialogAction } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<AlertDialogActionProps & {
  class?: HTMLAttributes["class"];
  variant?: ButtonVariants["variant"];
  size?: ButtonVariants["size"];
}>();

const delegatedProps = reactiveOmit(props, "class", "variant", "size");
</script>

<template>
  <AlertDialogAction v-bind="delegatedProps" :class="cn('cn-alert-dialog-action', buttonVariants({ variant, size }), props.class)">
    <slot />
  </AlertDialogAction>
</template>
