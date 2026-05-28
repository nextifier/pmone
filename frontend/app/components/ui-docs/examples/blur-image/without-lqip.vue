<script setup>
import { BlurImage } from "@/components/ui/blur-image";
</script>

<template>
  <div class="w-full max-w-sm overflow-hidden rounded-lg">
    <BlurImage
      src="https://images.unsplash.com/photo-1739267543141-fce8b71c63a4?w=800&q=80"
      alt="Forest"
      :width="800"
      :height="500"
      image-class="object-cover"
    />
  </div>
</template>
