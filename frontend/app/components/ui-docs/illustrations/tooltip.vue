<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col items-center justify-center gap-2 p-4">
      <div class="bg-foreground text-background relative rounded-md px-2 py-1 text-xs shadow-lg">
        Tooltip
        <div class="bg-foreground absolute top-full left-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rotate-45" />
      </div>
      <div class="border-border bg-card mt-2 rounded-md border px-3 py-1.5">
        <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
