<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-3 p-4">
      <div class="bg-muted-foreground/40 h-1.5 w-3/4 rounded-full" />
      <div class="flex flex-col gap-1.5">
        <div class="bg-muted-foreground/20 h-1.5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-5/6 rounded-full" />
      </div>
      <div class="mt-1 flex items-center justify-end gap-2">
        <div class="bg-muted-foreground/20 h-4 w-8 rounded-sm" />
        <div class="h-4 w-10 rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
      </div>
    </div>
  </IllustrationFrame>
</template>
