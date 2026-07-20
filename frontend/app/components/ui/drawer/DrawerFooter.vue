<script lang="ts" setup>
import type { HtmlHTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{
  class?: HtmlHTMLAttributes["class"];
}>();
</script>

<template>
  <div data-slot="drawer-footer" :class="cn('cn-drawer-footer mt-auto flex flex-col', props.class)">
    <slot />
  </div>
</template>
