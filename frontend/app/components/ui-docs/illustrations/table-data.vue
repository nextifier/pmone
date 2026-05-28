<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72">
    <div class="flex flex-1 flex-col gap-1 p-3">
      <div class="border-border bg-card flex items-center gap-2 rounded-md border px-2 py-1.5">
        <Icon name="lucide:search" class="text-muted-foreground/60 size-3" />
        <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
      </div>
      <div class="divide-border divide-y">
        <div v-for="i in 4" :key="i" class="flex items-center gap-2 px-2 py-1.5">
          <div class="bg-muted-foreground/40 size-2.5 rounded-xs" />
          <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
          <div class="bg-muted-foreground/20 h-1.5 flex-1 rounded-full" />
          <div class="bg-muted-foreground/20 h-1.5 w-6 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
