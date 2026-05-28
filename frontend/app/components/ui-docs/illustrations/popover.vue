<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col items-center justify-center gap-2 p-4">
      <div class="border-border bg-card flex w-fit items-center gap-2 rounded-md border px-3 py-1.5">
        <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
      </div>
      <div class="border-border bg-card flex flex-col gap-1.5 rounded-md border p-3 shadow-md">
        <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-16 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
