<template>
  <PopoverTrigger asChild>
    <Button
      variant="outline"
      size="sm"
      :aria-label="label"
      :class="
        cn(
          'relative h-(--cn-input-h) shrink-0 active:scale-98 max-sm:aspect-square max-sm:px-0',
          props.class
        )
      "
    >
      <Icon :name="icon" class="size-4 shrink-0" />
      <span class="hidden sm:flex">{{ label }}</span>
      <span
        v-if="count > 0"
        class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-xs font-medium tracking-tight"
      >
        {{ count }}
      </span>
      <slot />
    </Button>
  </PopoverTrigger>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

const props = defineProps({
  count: {
    type: Number,
    default: 0,
  },
  label: {
    type: String,
    default: "Filter",
  },
  icon: {
    type: String,
    default: "hugeicons:filter-horizontal",
  },
  class: {
    type: null,
    default: undefined,
  },
});
</script>
