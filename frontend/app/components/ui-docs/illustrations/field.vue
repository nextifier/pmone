<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <div class="flex w-full max-w-72 flex-col gap-2">
    <div class="bg-muted-foreground/88 h-1.5 w-12 rounded-full" />
    <IllustrationFrame max-width="72" variant="solid">
      <div class="flex-1 px-4 py-3">
        <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      </div>
    </IllustrationFrame>
    <div class="bg-muted-foreground/20 h-1 w-2/3 rounded-full" />
  </div>
</template>
