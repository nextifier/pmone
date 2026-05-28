<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-2 p-4">
      <div class="flex items-center gap-2">
        <div class="border-primary bg-primary flex size-4 items-center justify-center rounded-full border">
          <div class="bg-primary-foreground size-1.5 rounded-full" />
        </div>
        <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
      </div>
      <div class="flex items-center gap-2">
        <div class="border-border bg-card size-4 rounded-full border" />
        <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
      </div>
      <div class="flex items-center gap-2">
        <div class="border-border bg-card size-4 rounded-full border" />
        <div class="bg-muted-foreground/40 h-1.5 w-14 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
