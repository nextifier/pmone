<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="border-border bg-card flex items-center gap-2 rounded-md border px-3 py-1.5">
        <Icon name="lucide:globe" class="text-muted-foreground/88 size-4" />
        <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
        <Icon name="lucide:chevron-down" class="text-muted-foreground/60 size-3" />
      </div>
    </div>
  </IllustrationFrame>
</template>
