<script setup>
import { Stack } from "@/components/ui/stack";
</script>

<template>
  <div class="flex flex-wrap items-center justify-center gap-8">
    <div class="flex flex-col items-center gap-1">
      <Stack :interactive="true" class="text-muted-foreground">
        <Icon name="lucide:sparkles" class="size-6" />
      </Stack>
      <span class="text-muted-foreground text-sm tracking-tight">interactive (hover)</span>
    </div>
    <div class="flex flex-col items-center gap-1">
      <Stack :interactive="false" class="text-muted-foreground">
        <Icon name="lucide:square" class="size-6" />
      </Stack>
      <span class="text-muted-foreground text-sm tracking-tight">static</span>
    </div>
  </div>
</template>
