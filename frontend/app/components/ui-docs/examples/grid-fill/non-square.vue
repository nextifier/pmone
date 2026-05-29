<script setup>
import { GridFill } from "@/components/ui/grid-fill";
</script>

<template>
  <GridFill :count="5" min-col-width="280px" :cols="1" filler-class="bg-pattern-diagonal min-h-32" class="w-full">
    <div v-for="i in 5" :key="i" class="flex min-h-32 items-center gap-4 p-5">
      <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg text-sm font-medium tracking-tighter">
        {{ i }}
      </div>
      <div>
        <p class="text-base font-medium tracking-tight">Card item {{ i }}</p>
        <p class="text-muted-foreground text-sm tracking-tight">Flexible height, not square.</p>
      </div>
    </div>
  </GridFill>
</template>
