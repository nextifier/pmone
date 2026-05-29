<script setup>
import { GridFill } from "@/components/ui/grid-fill";

const rounds = ["lg", "xl", "2xl"];
</script>

<template>
  <div class="grid w-full gap-6 sm:grid-cols-3">
    <div v-for="r in rounds" :key="r">
      <span class="text-muted-foreground mb-2 block text-sm tracking-tight">rounded="{{ r }}"</span>
      <GridFill :count="5" :rounded="r">
        <div v-for="i in 5" :key="i" class="flex aspect-square items-center justify-center">
          <span class="text-lg font-medium tracking-tighter">{{ i }}</span>
        </div>
      </GridFill>
    </div>
  </div>
</template>
