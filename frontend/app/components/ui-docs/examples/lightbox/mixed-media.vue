<script setup>
import { Lightbox } from "@/components/ui/lightbox";

const items = [
  {
    sm: "https://images.unsplash.com/photo-1540968221243-29f5d70540bf?w=400",
    lg: "https://images.unsplash.com/photo-1540968221243-29f5d70540bf?w=1600",
    alt: "Landscape",
  },
  {
    type: "video",
    src: "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4",
    poster: "https://images.unsplash.com/photo-1628944682084-831f35256163?w=1600",
    alt: "Sample video",
    caption: "Video auto-pauses when the slide changes.",
  },
  {
    sm: "https://images.unsplash.com/photo-1596135187959-562c650d98bc?w=400",
    lg: "https://images.unsplash.com/photo-1596135187959-562c650d98bc?w=1600",
    alt: "Mountain",
  },
];
</script>

<template>
  <Lightbox :items="items" class="w-full" />
</template>
