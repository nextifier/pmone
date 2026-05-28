<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="bg-muted h-2 w-32 overflow-hidden rounded-full">
        <div class="bg-primary h-full w-3/5 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
