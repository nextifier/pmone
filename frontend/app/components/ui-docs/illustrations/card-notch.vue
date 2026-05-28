<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="relative">
        <div class="border-border bg-card h-20 w-32 rounded-xl border" />
        <div class="bg-card border-border absolute -top-3 -right-3 size-8 rounded-full border" />
      </div>
    </div>
  </IllustrationFrame>
</template>
