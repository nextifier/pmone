<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="border-border grid grid-cols-3 gap-px overflow-hidden rounded-md border bg-border">
        <div class="bg-card h-8 w-10" />
        <div class="bg-card h-8 w-10" />
        <div class="bg-card h-8 w-10" />
        <div class="bg-card h-8 w-10" />
        <div class="bg-card h-8 w-10" />
        <div class="bg-pattern-diagonal h-8 w-10" />
      </div>
    </div>
  </IllustrationFrame>
</template>
