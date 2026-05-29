<script setup>
import { ref } from "vue";
import {
  Menubar,
  MenubarCheckboxItem,
  MenubarContent,
  MenubarMenu,
  MenubarSeparator,
  MenubarTrigger,
} from "@/components/ui/menubar";

const showStatusBar = ref(true);
const showActivityBar = ref(false);
const showPanel = ref(false);
</script>

<template>
  <Menubar>
    <MenubarMenu>
      <MenubarTrigger>View</MenubarTrigger>
      <MenubarContent>
        <MenubarCheckboxItem v-model="showStatusBar">Status bar</MenubarCheckboxItem>
        <MenubarCheckboxItem v-model="showActivityBar">Activity bar</MenubarCheckboxItem>
        <MenubarSeparator />
        <MenubarCheckboxItem v-model="showPanel">Panel</MenubarCheckboxItem>
      </MenubarContent>
    </MenubarMenu>
  </Menubar>
</template>
