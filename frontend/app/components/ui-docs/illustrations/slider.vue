<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="bg-muted relative h-1.5 w-32 rounded-full">
        <div class="bg-primary absolute h-full w-3/5 rounded-full" />
        <div class="border-primary bg-card absolute top-1/2 left-3/5 size-3.5 -translate-y-1/2 -translate-x-1/2 rounded-full border-2 shadow" />
      </div>
    </div>
  </IllustrationFrame>
</template>
