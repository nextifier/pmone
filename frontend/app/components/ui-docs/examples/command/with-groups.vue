<script setup>
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command";
</script>

<template>
  <Command class="w-full max-w-sm rounded-lg border shadow-md">
    <CommandInput placeholder="Search actions" />
    <CommandList>
      <CommandEmpty>No results found.</CommandEmpty>
      <CommandGroup heading="Files">
        <CommandItem value="new-file">
          <Icon name="lucide:file-plus" />
          New file
        </CommandItem>
        <CommandItem value="open-file">
          <Icon name="lucide:folder-open" />
          Open file
        </CommandItem>
      </CommandGroup>
      <CommandSeparator />
      <CommandGroup heading="Edit">
        <CommandItem value="copy">
          <Icon name="lucide:copy" />
          Copy
        </CommandItem>
        <CommandItem value="paste">
          <Icon name="lucide:clipboard" />
          Paste
        </CommandItem>
      </CommandGroup>
      <CommandSeparator />
      <CommandGroup heading="View">
        <CommandItem value="zoom-in">
          <Icon name="lucide:zoom-in" />
          Zoom in
        </CommandItem>
        <CommandItem value="zoom-out">
          <Icon name="lucide:zoom-out" />
          Zoom out
        </CommandItem>
      </CommandGroup>
    </CommandList>
  </Command>
</template>
