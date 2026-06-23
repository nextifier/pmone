<script setup>
import { Stack } from "@/components/ui/stack";
</script>

<template>
  <Stack size="5rem" :gap="80" class="text-foreground">
    <template #layer-1>
      <div class="flex size-full flex-col items-center justify-center gap-1 p-2 text-center">
        <Icon name="lucide:check-check" class="size-5" />
        <span class="text-sm font-medium tracking-tight">Done</span>
      </div>
    </template>
    <template #layer-2>
      <div class="flex size-full items-center justify-center">
        <Icon name="lucide:image" class="text-muted-foreground size-8" />
      </div>
    </template>
    <template #layer-3>
      <div class="flex size-full items-center justify-center gap-1">
        <span class="bg-muted-foreground/40 size-2 rounded-full" />
        <span class="bg-muted-foreground/40 size-2 rounded-full" />
        <span class="bg-muted-foreground/40 size-2 rounded-full" />
      </div>
    </template>
  </Stack>
</template>
