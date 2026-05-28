<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-end justify-end p-3">
      <div class="bg-card border-border flex w-32 flex-col gap-1.5 rounded-lg border p-2.5 shadow-lg">
        <div class="flex items-center gap-1.5">
          <div class="bg-success size-1.5 rounded-full" />
          <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
        </div>
        <div class="bg-muted-foreground/20 h-1 w-full rounded-full" />
        <div class="bg-muted-foreground/20 h-1 w-3/4 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
