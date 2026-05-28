<script setup>
import { ref } from "vue";
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { Button } from "@/components/ui/button";

const open = ref(false);

defineShortcuts({
  meta_k: () => (open.value = !open.value),
});
</script>

<template>
  <div class="flex flex-col items-center gap-3">
    <Button variant="outline" @click="open = true">Open command (⌘K)</Button>
    <CommandDialog v-model:open="open">
      <CommandInput placeholder="Type a command or search" />
      <CommandList>
        <CommandEmpty>No results.</CommandEmpty>
        <CommandGroup heading="Quick actions">
          <CommandItem value="new-doc">New document</CommandItem>
          <CommandItem value="new-project">New project</CommandItem>
          <CommandItem value="invite">Invite teammate</CommandItem>
        </CommandGroup>
      </CommandList>
    </CommandDialog>
  </div>
</template>
