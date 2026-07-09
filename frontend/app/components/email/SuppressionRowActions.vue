<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="iconSm" aria-label="Row actions">
        <Icon name="hugeicons:more-horizontal" class="size-4" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuItem class="hover:text-destructive" @select="emit('remove')">
        <Icon name="hugeicons:delete-02" class="size-4" />
        <span>Remove from list</span>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const emit = defineEmits(["remove"]);
</script>
