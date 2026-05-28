<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-1.5 p-4">
      <div class="border-border bg-card flex size-6 items-center justify-center rounded">
        <Icon name="lucide:chevron-left" class="text-muted-foreground/60 size-3" />
      </div>
      <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
      <div class="border-border bg-card flex size-6 items-center justify-center rounded">
        <Icon name="lucide:chevron-right" class="text-muted-foreground/60 size-3" />
      </div>
    </div>
  </IllustrationFrame>
</template>
