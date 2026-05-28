<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="border-border bg-card flex gap-1 rounded-md border p-1">
        <div class="bg-muted rounded px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
        </div>
        <div class="rounded px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
        </div>
        <div class="rounded px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
