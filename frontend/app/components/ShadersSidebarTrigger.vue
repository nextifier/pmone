<template>
  <Tippy>
    <button
      data-sidebar="trigger"
      data-slot="sidebar-trigger"
      class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
      @click="toggleSidebar"
    >
      <Icon
        v-if="open && !isMobile"
        name="hugeicons:sidebar-left-01"
        class="text-primary size-5"
      />
      <Icon v-else name="hugeicons:sidebar-left" class="text-primary size-5" />
    </button>
    <template #content>
      <span class="inline-flex items-center gap-x-1.5 tracking-tight">
        <span>Toggle Sidebar</span>
        <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
      </span>
    </template>
  </Tippy>
</template>

<script setup>
import { useSidebar } from "@/components/ui/sidebar/utils";

const { toggleSidebar, open, isMobile } = useSidebar();
const { metaSymbol } = useShortcuts();
</script>
