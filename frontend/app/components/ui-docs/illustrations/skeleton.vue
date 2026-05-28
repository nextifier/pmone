<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center gap-3 p-4">
      <div class="bg-muted-foreground/20 size-10 animate-pulse rounded-full" />
      <div class="flex-1 space-y-2">
        <div class="bg-muted-foreground/20 h-1.5 w-3/4 animate-pulse rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-full animate-pulse rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
