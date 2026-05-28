<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="relative">
        <Icon name="lucide:bell" class="text-muted-foreground/88 size-6" />
        <div class="bg-primary ring-background absolute -top-0.5 -right-0.5 size-2 rounded-full ring-2" />
      </div>
    </div>
  </IllustrationFrame>
</template>
