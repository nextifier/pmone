<script setup>
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuSub,
  ContextMenuSubContent,
  ContextMenuSubTrigger,
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
      <ContextMenuItem>Open</ContextMenuItem>
      <ContextMenuItem>Rename</ContextMenuItem>
      <ContextMenuSub>
        <ContextMenuSubTrigger>Share</ContextMenuSubTrigger>
        <ContextMenuSubContent class="w-44">
          <ContextMenuItem>Copy link</ContextMenuItem>
          <ContextMenuItem>Email</ContextMenuItem>
          <ContextMenuItem>Invite people</ContextMenuItem>
        </ContextMenuSubContent>
      </ContextMenuSub>
      <ContextMenuSeparator />
      <ContextMenuItem>Delete</ContextMenuItem>
    </ContextMenuContent>
  </ContextMenu>
</template>
