<script setup>
import { Stack } from "@/components/ui/stack";
</script>

<template>
  <div class="flex flex-wrap items-center justify-center gap-6">
    <div v-for="n in [2, 4, 5]" :key="n" class="flex flex-col items-center gap-1">
      <Stack :layers="n" class="text-muted-foreground">
        <Icon name="lucide:layers" class="size-6" />
      </Stack>
      <span class="text-muted-foreground text-sm tracking-tight">{{ n }} layers</span>
    </div>
  </div>
</template>
