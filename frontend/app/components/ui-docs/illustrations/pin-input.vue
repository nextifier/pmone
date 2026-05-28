<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-1 p-4">
      <div v-for="i in 4" :key="i" class="border-border bg-card flex size-7 items-center justify-center rounded-md border">
        <div v-if="i <= 2" class="bg-muted-foreground/40 size-1 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
