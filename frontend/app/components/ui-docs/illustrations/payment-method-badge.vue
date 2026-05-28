<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center gap-2 p-4">
      <div class="border-border bg-card flex h-6 w-10 items-center justify-center rounded-md border">
        <div class="bg-muted-foreground/50 h-2.5 w-5 rounded-sm" />
      </div>
      <div class="border-border bg-card flex h-6 w-10 items-center justify-center rounded-md border">
        <div class="bg-muted-foreground/40 size-2.5 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
