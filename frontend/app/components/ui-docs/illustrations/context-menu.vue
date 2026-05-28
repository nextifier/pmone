<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-1 p-3">
      <div class="border-border bg-card flex flex-col gap-1 rounded-md border p-1.5 shadow-md">
        <div class="hover:bg-muted flex items-center gap-2 rounded px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
        </div>
        <div class="bg-muted hover:bg-muted flex items-center gap-2 rounded px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
        </div>
        <div class="bg-border h-px" />
        <div class="flex items-center gap-2 px-2 py-1">
          <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
