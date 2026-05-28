<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36" overflow>
    <div class="flex flex-1 flex-col gap-3 p-4 pt-2">
      <div class="bg-muted-foreground/40 mx-auto h-1 w-8 rounded-full" />
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <div class="bg-muted-foreground/8 h-3.5 rounded-sm" />
      <div class="bg-muted-foreground/8 h-3.5 rounded-sm" />
      <div class="h-4 w-full rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
    </div>
  </IllustrationFrame>
</template>
