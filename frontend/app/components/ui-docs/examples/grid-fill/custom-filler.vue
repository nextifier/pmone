<script setup>
import { GridFill } from "@/components/ui/grid-fill";
</script>

<template>
  <GridFill :count="10" filler-class="bg-pattern-diagonal aspect-square" class="w-full">
    <div v-for="i in 10" :key="i" class="flex aspect-square flex-col items-center justify-center gap-y-1">
      <div class="bg-muted flex size-12 items-center justify-center rounded-lg text-lg font-medium tracking-tighter">
        {{ i }}
      </div>
      <span class="text-muted-foreground text-sm tracking-tight">Item {{ i }}</span>
    </div>
    <template #filler="{ index }">
      <div v-if="index === 1" class="flex aspect-square flex-col items-center justify-center p-4 text-center">
        <span class="text-muted-foreground text-sm tracking-tight sm:text-base">and many more</span>
      </div>
      <div v-else class="bg-pattern-diagonal aspect-square" />
    </template>
  </GridFill>
</template>
