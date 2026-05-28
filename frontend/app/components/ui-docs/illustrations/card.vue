<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-4 p-4">
      <div class="flex flex-col gap-2">
        <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-[90%] rounded-full" />
      </div>
      <div class="flex flex-col gap-2">
        <div class="bg-muted-foreground/8 h-4 rounded-sm" />
        <div class="bg-muted-foreground/8 h-4 rounded-sm" />
        <div class="h-4 w-full rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
      </div>
    </div>
  </IllustrationFrame>
</template>
