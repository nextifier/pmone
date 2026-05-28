<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col gap-1.5 p-3">
      <div class="flex items-center justify-between px-1">
        <Icon name="lucide:chevron-left" class="text-muted-foreground/60 size-2.5" />
        <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
        <Icon name="lucide:chevron-right" class="text-muted-foreground/60 size-2.5" />
      </div>
      <div class="grid grid-cols-7 gap-0.5">
        <div
          v-for="i in 21"
          :key="i"
          class="size-3.5 rounded-sm"
          :class="[
            i === 8 || i === 14 ? 'bg-primary' : i > 8 && i < 14 ? 'bg-primary/30' : 'bg-muted-foreground/10',
          ]"
        />
      </div>
    </div>
  </IllustrationFrame>
</template>
