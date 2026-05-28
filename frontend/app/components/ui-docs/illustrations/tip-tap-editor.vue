<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72">
    <div class="flex flex-1 flex-col gap-1.5 p-3">
      <div class="border-border flex gap-1 border-b pb-2">
        <Icon name="lucide:bold" class="text-muted-foreground/60 size-3" />
        <Icon name="lucide:italic" class="text-muted-foreground/60 size-3" />
        <Icon name="lucide:list" class="text-muted-foreground/60 size-3" />
        <Icon name="lucide:link" class="text-muted-foreground/60 size-3" />
        <Icon name="lucide:image" class="text-muted-foreground/60 size-3" />
      </div>
      <div class="space-y-1.5 pt-1">
        <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-full rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-4/5 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
