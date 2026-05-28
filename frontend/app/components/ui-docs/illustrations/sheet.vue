<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50" overflow>
    <div class="flex flex-1 items-stretch justify-end p-2">
      <div class="bg-card border-border flex w-1/2 flex-col gap-2 rounded-l-xl border p-3">
        <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
        <div class="bg-muted-foreground/8 h-3 rounded-sm" />
        <div class="bg-muted-foreground/8 h-3 rounded-sm" />
        <div class="bg-muted-foreground/8 h-3 w-4/5 rounded-sm" />
      </div>
    </div>
  </IllustrationFrame>
</template>
