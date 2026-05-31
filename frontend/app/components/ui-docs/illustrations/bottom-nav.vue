<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col p-4">
      <div class="flex flex-1 flex-col gap-2.5 pb-4">
        <div class="bg-muted-foreground/40 h-1.5 w-2/5 rounded-full" />
        <div class="bg-muted-foreground/8 h-4 rounded-sm" />
        <div class="bg-muted-foreground/8 h-4 w-4/5 rounded-sm" />
      </div>
      <div class="border-border flex items-center justify-between border-t pt-3">
        <div class="size-2 rounded-full bg-muted-foreground/20" />
        <div class="size-2 rounded-full bg-muted-foreground/20" />
        <div
          class="flex h-5 items-center rounded-full bg-muted-foreground/88 px-2"
        >
          <div class="size-2 rounded-full bg-background" />
        </div>
        <div class="size-2 rounded-full bg-muted-foreground/20" />
        <div class="size-2 rounded-full bg-muted-foreground/20" />
      </div>
    </div>
  </IllustrationFrame>
</template>
