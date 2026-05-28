<script setup>
import { AspectRatio } from "@/components/ui/aspect-ratio";
</script>

<template>
  <div class="grid w-full max-w-sm grid-cols-3 gap-2">
    <AspectRatio v-for="i in 6" :key="i" :ratio="1">
      <div class="bg-muted size-full rounded-md" />
    </AspectRatio>
  </div>
</template>
