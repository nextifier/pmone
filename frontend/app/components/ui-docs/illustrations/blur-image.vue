<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="bg-muted-foreground/20 relative h-14 w-24 overflow-hidden rounded-md">
        <div class="bg-muted-foreground/30 absolute inset-0 blur-xl" />
        <div class="from-muted-foreground/20 to-muted-foreground/40 absolute inset-0 bg-linear-to-br" />
      </div>
    </div>
  </IllustrationFrame>
</template>
