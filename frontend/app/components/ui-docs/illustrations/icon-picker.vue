<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="border-border bg-card grid grid-cols-3 gap-2 rounded-lg border p-3">
        <Icon name="lucide:smile" class="text-muted-foreground/60 size-4" />
        <Icon name="lucide:zap" class="text-muted-foreground/60 size-4" />
        <Icon name="lucide:heart" class="text-muted-foreground/60 size-4" />
        <Icon name="lucide:star" class="text-primary size-4" />
        <Icon name="lucide:circle" class="text-muted-foreground/60 size-4" />
        <Icon name="lucide:square" class="text-muted-foreground/60 size-4" />
      </div>
    </div>
  </IllustrationFrame>
</template>
