<script setup>
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from "@/components/ui/command";
</script>

<template>
  <Command class="w-full max-w-sm rounded-lg border shadow-md">
    <CommandInput placeholder="Type a command or search" />
    <CommandList>
      <CommandEmpty>No results found.</CommandEmpty>
      <CommandGroup heading="Suggestions">
        <CommandItem value="calendar">
          <Icon name="lucide:calendar" />
          Calendar
        </CommandItem>
        <CommandItem value="search-emoji">
          <Icon name="lucide:smile" />
          Search emoji
        </CommandItem>
      </CommandGroup>
      <CommandSeparator />
      <CommandGroup heading="Settings">
        <CommandItem value="profile">
          <Icon name="lucide:user" />
          Profile
          <CommandShortcut>⌘P</CommandShortcut>
        </CommandItem>
        <CommandItem value="settings">
          <Icon name="lucide:settings" />
          Settings
          <CommandShortcut>⌘S</CommandShortcut>
        </CommandItem>
      </CommandGroup>
    </CommandList>
  </Command>
</template>
