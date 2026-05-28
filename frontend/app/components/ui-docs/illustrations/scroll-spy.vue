<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 gap-3 p-4">
      <div class="bg-border relative w-px self-stretch">
        <div class="bg-primary absolute top-4 -left-px h-4 w-0.5 rounded-full" />
      </div>
      <div class="flex flex-1 flex-col gap-2">
        <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
        <div class="bg-foreground h-1.5 w-14 rounded-full" />
        <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
        <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
