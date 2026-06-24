<template>
  <div class="flex justify-end">
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="iconSm"
          aria-label="Item actions"
          class="data-[state=open]:bg-muted"
        >
          <Icon name="hugeicons:more-horizontal" class="size-4 shrink-0" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-48">
        <DropdownMenuItem @click="$emit('adjust', item)">
          <Icon name="hugeicons:discount-tag-02" class="size-4 shrink-0" />
          Price adjustment
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

defineProps({
  item: { type: Object, required: true },
});

defineEmits(["adjust"]);
</script>
