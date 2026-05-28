<script setup>
import { Carousel3d } from "@/components/ui/carousel-3d";

const items = [
  { id: 1, image: "https://images.unsplash.com/photo-1717689596878-aa7c61c20fbe?w=600&q=80" },
  { id: 2, image: "https://images.unsplash.com/photo-1735633568712-e0d57df85a37?w=600&q=80" },
  { id: 3, image: "https://images.unsplash.com/photo-1739267543141-fce8b71c63a4?w=600&q=80" },
  { id: 4, image: "https://images.unsplash.com/photo-1738591958459-c1d39bf28691?w=600&q=80" },
];
</script>

<template>
  <Carousel3d :items="items" class="w-full" />
</template>
