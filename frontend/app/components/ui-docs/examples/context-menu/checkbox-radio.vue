<script setup>
import { ref } from "vue";
import {
  ContextMenu,
  ContextMenuCheckboxItem,
  ContextMenuContent,
  ContextMenuLabel,
  ContextMenuRadioGroup,
  ContextMenuRadioItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";

const showStatusBar = ref(true);
const showPanel = ref(false);
const position = ref("bottom");
</script>

<template>
  <ContextMenu>
    <ContextMenuTrigger
      class="border-border flex h-32 w-64 items-center justify-center rounded-md border border-dashed text-sm tracking-tight"
    >
      Right-click here
    </ContextMenuTrigger>
    <ContextMenuContent class="w-56">
      <ContextMenuLabel>Appearance</ContextMenuLabel>
      <ContextMenuCheckboxItem v-model="showStatusBar">Status bar</ContextMenuCheckboxItem>
      <ContextMenuCheckboxItem v-model="showPanel">Panel</ContextMenuCheckboxItem>
      <ContextMenuSeparator />
      <ContextMenuLabel>Position</ContextMenuLabel>
      <ContextMenuRadioGroup v-model="position">
        <ContextMenuRadioItem value="top">Top</ContextMenuRadioItem>
        <ContextMenuRadioItem value="bottom">Bottom</ContextMenuRadioItem>
      </ContextMenuRadioGroup>
    </ContextMenuContent>
  </ContextMenu>
</template>
