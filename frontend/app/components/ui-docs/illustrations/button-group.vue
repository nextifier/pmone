<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="flex">
        <div class="border-border bg-card rounded-l-md border px-3 py-2">
          <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
        </div>
        <div class="border-border bg-card -ml-px border px-3 py-2">
          <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
        </div>
        <div class="border-border bg-card -ml-px rounded-r-md border px-3 py-2">
          <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
