<script setup>
import {
  Menubar,
  MenubarContent,
  MenubarItem,
  MenubarMenu,
  MenubarSeparator,
  MenubarSub,
  MenubarSubContent,
  MenubarSubTrigger,
  MenubarTrigger,
} from "@/components/ui/menubar";
</script>

<template>
  <Menubar>
    <MenubarMenu>
      <MenubarTrigger>File</MenubarTrigger>
      <MenubarContent>
        <MenubarItem>New tab</MenubarItem>
        <MenubarSub>
          <MenubarSubTrigger>Share</MenubarSubTrigger>
          <MenubarSubContent>
            <MenubarItem>Copy link</MenubarItem>
            <MenubarItem>Email</MenubarItem>
            <MenubarItem>Notes</MenubarItem>
          </MenubarSubContent>
        </MenubarSub>
        <MenubarSeparator />
        <MenubarItem>Print</MenubarItem>
      </MenubarContent>
    </MenubarMenu>
  </Menubar>
</template>
