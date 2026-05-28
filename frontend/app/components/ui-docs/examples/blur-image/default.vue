<script setup>
import { BlurImage } from "@/components/ui/blur-image";
</script>

<template>
  <div class="w-full max-w-sm overflow-hidden rounded-lg">
    <BlurImage
      src="https://images.unsplash.com/photo-1735633568712-e0d57df85a37?w=800&q=80"
      lqip="https://images.unsplash.com/photo-1735633568712-e0d57df85a37?w=40&blur=80&q=10"
      alt="Mountain"
      :width="800"
      :height="500"
      image-class="object-cover"
    />
  </div>
</template>
