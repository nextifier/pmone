<script setup>
import { GridFill } from "@/components/ui/grid-fill";

const items = [1, 2, 3, 4, 5];
</script>

<template>
  <GridFill :count="items.length" :cols="3" :min-col-width="false" rounded="xl" class="w-full">
    <div v-for="item in items" :key="item" class="flex h-24 items-center justify-center">
      <span class="text-2xl font-semibold tracking-tighter">{{ item }}</span>
    </div>
  </GridFill>
</template>
