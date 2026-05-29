<script setup>
import { Lightbox } from "@/components/ui/lightbox";

const ids = [
  "1540968221243-29f5d70540bf",
  "1596135187959-562c650d98bc",
  "1628944682084-831f35256163",
  "1590013330451-3946e83e0392",
  "1590421959604-741d0eec0a2e",
  "1572613000712-eadc57acbecd",
];

const images = ids.map((id, i) => ({
  sm: `https://images.unsplash.com/photo-${id}?w=400`,
  lg: `https://images.unsplash.com/photo-${id}?w=1600`,
  alt: `Photo ${i + 1}`,
}));
</script>

<template>
  <Lightbox :items="images" class="w-full" />
</template>
