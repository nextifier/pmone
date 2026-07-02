<script setup lang="ts">
import type { ButtonVariants } from "@/components/ui/button";
import { buttonVariants } from "@/components/ui/button";
import { reactiveOmit } from "@vueuse/core";
import { ChevronLeftIcon } from "lucide-vue-next";
import type { PaginationFirstProps } from "reka-ui";
import { PaginationFirst, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<
    PaginationFirstProps & {
      size?: ButtonVariants["size"];
      class?: HTMLAttributes["class"];
    }
  >(),
  {
    size: "default",
  }
);

const delegatedProps = reactiveOmit(props, "class", "size");
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
  <PaginationFirst
    data-slot="pagination-first"
    :class="cn(buttonVariants({ variant: 'ghost', size }), 'cn-pagination-first', props.class)"
    v-bind="forwarded"
  >
    <slot>
      <ChevronLeftIcon />
      <span class="cn-pagination-first-text hidden sm:block">First</span>
    </slot>
  </PaginationFirst>
</template>
