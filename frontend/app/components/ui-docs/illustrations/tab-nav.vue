<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-end justify-center p-4">
      <div class="border-border flex w-full gap-4 border-b px-4">
        <div class="border-primary -mb-px border-b-2 py-2">
          <div class="bg-foreground h-1.5 w-10 rounded-full" />
        </div>
        <div class="py-2">
          <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
        </div>
        <div class="py-2">
          <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
