<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
import { CircularProgress } from "@/components/ui/circular-progress";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <CircularProgress :value="72" :size="72" :stroke-width="6" />
    </div>
  </IllustrationFrame>
</template>
