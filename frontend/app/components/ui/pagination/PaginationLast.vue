<script setup lang="ts">
import type { ButtonVariants } from "@/components/ui/button";
import { buttonVariants } from "@/components/ui/button";
import { reactiveOmit } from "@vueuse/core";
import { ChevronRightIcon } from "lucide-vue-next";
import type { PaginationLastProps } from "reka-ui";
import { PaginationLast, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<
    PaginationLastProps & {
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
  <PaginationLast
    data-slot="pagination-last"
    :class="cn(buttonVariants({ variant: 'ghost', size }), 'cn-pagination-last', props.class)"
    v-bind="forwarded"
  >
    <slot>
      <span class="cn-pagination-last-text hidden sm:block">Last</span>
      <ChevronRightIcon />
    </slot>
  </PaginationLast>
</template>
