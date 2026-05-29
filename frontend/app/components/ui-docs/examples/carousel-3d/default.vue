<script setup>
import { Carousel3d } from "@/components/ui/carousel-3d";

const items = [
  { src: "https://images.unsplash.com/photo-1540968221243-29f5d70540bf?w=400", alt: "Photo 1" },
  { src: "https://images.unsplash.com/photo-1596135187959-562c650d98bc?w=400", alt: "Photo 2" },
  { src: "https://images.unsplash.com/photo-1628944682084-831f35256163?w=400", alt: "Photo 3" },
  { src: "https://images.unsplash.com/photo-1590013330451-3946e83e0392?w=400", alt: "Photo 4" },
  { src: "https://images.unsplash.com/photo-1590421959604-741d0eec0a2e?w=400", alt: "Photo 5" },
];
</script>

<template>
  <Carousel3d :items="items" class="w-full" />
</template>
