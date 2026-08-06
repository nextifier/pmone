<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerMenuSeparator). -->
  <div data-slot="drawer-menu-separator" :class="cn('mx-2 my-1 h-px bg-border', props.class)" />
</template>
