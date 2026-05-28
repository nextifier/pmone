<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center justify-between gap-2 px-4 py-2.5">
      <div class="flex items-center gap-1">
        <div v-for="i in 6" :key="i" class="bg-muted-foreground/40 size-1.5 rounded-full" />
      </div>
      <Icon name="lucide:eye" class="text-muted-foreground/60 size-4" />
    </div>
  </IllustrationFrame>
</template>
