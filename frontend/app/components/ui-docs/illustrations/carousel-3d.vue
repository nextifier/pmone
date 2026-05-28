<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-1 p-4 [perspective:400px]">
      <div class="bg-muted-foreground/10 h-12 w-8 rounded-md [transform:rotateY(35deg)]" />
      <div class="bg-muted-foreground/30 h-14 w-10 rounded-md shadow-md" />
      <div class="bg-muted-foreground/10 h-12 w-8 rounded-md [transform:rotateY(-35deg)]" />
    </div>
  </IllustrationFrame>
</template>
