<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col gap-3 p-4">
      <div class="flex flex-col gap-1.5">
        <div class="bg-muted-foreground/88 h-1.5 w-10 rounded-full" />
        <div class="border-border bg-card h-6 rounded-md border" />
      </div>
      <div class="flex flex-col gap-1.5">
        <div class="bg-muted-foreground/88 h-1.5 w-14 rounded-full" />
        <div class="border-border bg-card h-6 rounded-md border" />
      </div>
      <div class="mt-1 h-5 w-14 rounded-md bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
    </div>
  </IllustrationFrame>
</template>
