<script lang="ts" setup>
import type { DrawerTitleProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DrawerTitle } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<DrawerTitleProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerTitle). -->
  <DrawerTitle
    data-slot="drawer-title"
    v-bind="delegatedProps"
    :class="cn('font-heading font-semibold text-xl leading-none', props.class)"
  >
    <slot />
  </DrawerTitle>
</template>
