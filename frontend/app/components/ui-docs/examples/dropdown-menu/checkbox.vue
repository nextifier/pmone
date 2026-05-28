<script setup>
import { ref } from "vue";
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";

const showBookmarks = ref(true);
const showUrls = ref(false);
const showActivity = ref(false);
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline">View</Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent class="w-56">
      <DropdownMenuLabel>Appearance</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuCheckboxItem v-model="showBookmarks">Bookmarks bar</DropdownMenuCheckboxItem>
      <DropdownMenuCheckboxItem v-model="showUrls">Full URLs</DropdownMenuCheckboxItem>
      <DropdownMenuCheckboxItem v-model="showActivity">Activity log</DropdownMenuCheckboxItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
