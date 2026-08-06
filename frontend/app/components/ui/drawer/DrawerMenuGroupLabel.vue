<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerMenuGroupLabel). -->
  <div
    data-slot="drawer-menu-group-label"
    :class="cn('px-2 py-1.5 font-medium text-muted-foreground text-xs', props.class)"
  >
    <slot />
  </div>
</template>
