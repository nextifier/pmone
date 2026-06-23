<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";
import type { EmptyMediaVariants } from ".";
import { emptyMediaVariants } from ".";
import { Stack } from "@/components/ui/stack";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  variant?: EmptyMediaVariants["variant"];
}>();
</script>

<template>
  <div
    v-if="variant === 'stacked'"
    data-slot="empty-icon"
    data-variant="stacked"
    :class="cn('mb-2 flex items-center justify-center', props.class)"
  >
    <Stack class="text-muted-foreground">
      <slot />
    </Stack>
  </div>
  <div
    v-else
    data-slot="empty-icon"
    :data-variant="variant"
    :class="cn(emptyMediaVariants({ variant }), props.class)"
  >
    <slot />
  </div>
</template>
