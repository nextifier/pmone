<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerMenuGroup). -->
  <div data-slot="drawer-menu-group" :class="cn('flex flex-col', props.class)">
    <slot />
  </div>
</template>
