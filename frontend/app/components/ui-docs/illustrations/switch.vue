<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col items-center justify-center gap-3 p-4">
      <div class="flex items-center gap-2">
        <div class="bg-primary flex h-4 w-7 items-center rounded-full">
          <div class="bg-primary-foreground ml-auto size-3 rounded-full" />
        </div>
        <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
      </div>
      <div class="flex items-center gap-2">
        <div class="bg-muted-foreground/30 flex h-4 w-7 items-center rounded-full">
          <div class="bg-background ml-0.5 size-3 rounded-full" />
        </div>
        <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
