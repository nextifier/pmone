<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="flex items-center -space-x-2">
        <div class="ring-background bg-muted-foreground/40 size-8 rounded-full ring-2" />
        <div class="ring-background bg-muted-foreground/60 size-8 rounded-full ring-2" />
        <div class="ring-background bg-muted-foreground/30 size-8 rounded-full ring-2" />
        <div
          class="ring-background bg-muted text-muted-foreground flex size-8 items-center justify-center rounded-full text-[10px] font-medium ring-2"
        >
          +3
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
