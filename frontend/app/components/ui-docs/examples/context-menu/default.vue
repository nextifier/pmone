<script setup>
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuShortcut,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";
</script>

<template>
  <ContextMenu>
    <ContextMenuTrigger
      class="border-border flex h-32 w-64 items-center justify-center rounded-md border border-dashed text-sm tracking-tight"
    >
      Right-click here
    </ContextMenuTrigger>
    <ContextMenuContent class="w-56">
      <ContextMenuItem>
        Back
        <ContextMenuShortcut>⌘[</ContextMenuShortcut>
      </ContextMenuItem>
      <ContextMenuItem disabled>Forward</ContextMenuItem>
      <ContextMenuItem>Reload</ContextMenuItem>
      <ContextMenuSeparator />
      <ContextMenuItem>View page source</ContextMenuItem>
    </ContextMenuContent>
  </ContextMenu>
</template>
