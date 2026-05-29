<script setup>
import { Lightbox } from "@/components/ui/lightbox";
import { Button } from "@/components/ui/button";

const ids = ["1540968221243-29f5d70540bf", "1596135187959-562c650d98bc", "1628944682084-831f35256163"];

const images = ids.map((id, i) => ({
  sm: `https://images.unsplash.com/photo-${id}?w=400`,
  lg: `https://images.unsplash.com/photo-${id}?w=1600`,
  alt: `Photo ${i + 1}`,
}));
</script>

<template>
  <Lightbox :items="images">
    <template #trigger="{ open }">
      <Button variant="outline" @click="open()">
        <Icon name="lucide:images" class="size-4" />
        View gallery ({{ images.length }})
      </Button>
    </template>
  </Lightbox>
</template>
