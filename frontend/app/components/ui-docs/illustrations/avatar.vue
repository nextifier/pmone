<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="bg-muted-foreground/40 relative size-10 rounded-full">
        <div class="ring-background bg-success absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full ring-2" />
      </div>
    </div>
  </IllustrationFrame>
</template>
