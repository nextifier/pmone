<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="divide-border flex flex-1 flex-col divide-y p-1">
      <div class="flex items-center justify-between px-3 py-2">
        <div class="bg-muted-foreground/40 h-1.5 w-16 rounded-full" />
        <div class="bg-primary flex h-3.5 w-6 items-center rounded-full">
          <div class="bg-primary-foreground ml-auto size-2.5 rounded-full" />
        </div>
      </div>
      <div class="flex items-center justify-between px-3 py-2">
        <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
        <div class="bg-muted-foreground/30 flex h-3.5 w-6 items-center rounded-full">
          <div class="bg-background ml-0.5 size-2.5 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
