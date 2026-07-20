<script lang="ts" setup>
import type { HtmlHTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{
  class?: HtmlHTMLAttributes["class"];
}>();
</script>

<template>
  <div data-slot="drawer-header" :class="cn('cn-drawer-header flex flex-col', props.class)">
    <slot />
  </div>
</template>
