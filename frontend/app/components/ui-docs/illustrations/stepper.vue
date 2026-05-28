<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-1 p-4">
      <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-full text-xs font-medium">1</div>
      <div class="bg-primary h-px w-6" />
      <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-full text-xs font-medium">2</div>
      <div class="bg-muted h-px w-6" />
      <div class="border-border text-muted-foreground bg-card flex size-6 items-center justify-center rounded-full border text-xs">3</div>
    </div>
  </IllustrationFrame>
</template>
