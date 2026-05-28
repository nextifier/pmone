<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50" variant="solid">
    <div class="flex flex-1 flex-col gap-1.5 px-4 py-3">
      <div class="border-destructive bg-background h-6 rounded-md border" />
      <div class="flex items-center gap-1.5">
        <Icon name="lucide:circle-alert" class="text-destructive size-3" />
        <div class="bg-destructive/60 h-1.5 w-20 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
