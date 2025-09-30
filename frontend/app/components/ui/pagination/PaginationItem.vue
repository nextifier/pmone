<script setup lang="ts">
import type { ButtonVariants } from "@/components/ui/button";
import { reactiveOmit } from "@vueuse/core";
import type { PaginationListItemProps } from "reka-ui";
import { PaginationListItem } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<
    PaginationListItemProps & {
      size?: ButtonVariants["size"];
      class?: HTMLAttributes["class"];
      isActive?: boolean;
    }
  >(),
  {
    size: "icon",
  }
);

const delegatedProps = reactiveOmit(props, "class", "size", "isActive");
</script>

<template>
  <PaginationListItem data-slot="pagination-item" v-bind="delegatedProps">
    <slot />
  </PaginationListItem>
</template>
