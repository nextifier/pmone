<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center gap-1 p-4">
      <div class="bg-muted-foreground/15 flex size-7 items-center justify-center rounded-md">
        <Icon name="lucide:align-left" class="text-muted-foreground/60 size-3" />
      </div>
      <div class="bg-muted flex size-7 items-center justify-center rounded-md">
        <Icon name="lucide:align-center" class="text-foreground size-3" />
      </div>
      <div class="bg-muted-foreground/15 flex size-7 items-center justify-center rounded-md">
        <Icon name="lucide:align-right" class="text-muted-foreground/60 size-3" />
      </div>
    </div>
  </IllustrationFrame>
</template>
