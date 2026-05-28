<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-end justify-center gap-1 p-4">
      <div class="bg-muted-foreground/40 h-6 w-2 rounded-sm" />
      <div class="bg-muted-foreground/40 h-10 w-2 rounded-sm" />
      <div class="bg-muted-foreground/40 h-7 w-2 rounded-sm" />
      <div class="h-14 w-2 rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
      <div class="bg-muted-foreground/40 h-11 w-2 rounded-sm" />
      <div class="bg-muted-foreground/40 h-8 w-2 rounded-sm" />
    </div>
  </IllustrationFrame>
</template>
