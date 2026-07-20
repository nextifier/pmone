<script setup lang="ts">
import { buttonVariants, type ButtonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { AlertDialogCancelProps } from "reka-ui";
import { AlertDialogCancel } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(defineProps<AlertDialogCancelProps & {
  class?: HTMLAttributes["class"];
  variant?: ButtonVariants["variant"];
  size?: ButtonVariants["size"];
}>(), { variant: "outline" });

const delegatedProps = reactiveOmit(props, "class", "variant", "size");
</script>

<template>
  <AlertDialogCancel
    v-bind="delegatedProps"
    :class="cn('cn-alert-dialog-cancel', buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </AlertDialogCancel>
</template>
