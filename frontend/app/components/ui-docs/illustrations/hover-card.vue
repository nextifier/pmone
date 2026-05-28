<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-2 p-3">
      <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
      <div class="border-border bg-card flex w-fit gap-2 rounded-md border p-2 shadow-md">
        <div class="bg-muted-foreground/30 size-8 rounded-full" />
        <div class="flex flex-col justify-center gap-1">
          <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
          <div class="bg-muted-foreground/20 h-1 w-16 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
