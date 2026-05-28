<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-1.5 p-3">
      <div class="bg-card border-border flex w-fit items-center gap-1 self-end rounded-md border px-2 py-1">
        <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
        <Icon name="lucide:chevron-down" class="text-muted-foreground/60 size-3" />
      </div>
      <div class="bg-card border-border flex flex-col gap-1 self-end rounded-md border p-1.5 shadow-md">
        <div class="px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
        </div>
        <div class="bg-muted rounded px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 w-14 rounded-full" />
        </div>
        <div class="px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
