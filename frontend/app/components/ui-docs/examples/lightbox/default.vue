<script setup>
import { ref } from "vue";
import { Lightbox } from "@/components/ui/lightbox";

const open = ref(false);
const index = ref(0);

const images = [
  { src: "https://images.unsplash.com/photo-1717689596878-aa7c61c20fbe?w=1200&q=80", alt: "Landscape" },
  { src: "https://images.unsplash.com/photo-1735633568712-e0d57df85a37?w=1200&q=80", alt: "Mountain" },
  { src: "https://images.unsplash.com/photo-1739267543141-fce8b71c63a4?w=1200&q=80", alt: "Forest" },
];

function openAt(i) {
  index.value = i;
  open.value = true;
}
</script>

<template>
  <div>
    <div class="grid grid-cols-3 gap-2">
      <button
        v-for="(img, i) in images"
        :key="i"
        type="button"
        @click="openAt(i)"
        class="overflow-hidden rounded-md"
      >
        <img :src="img.src" :alt="img.alt" class="aspect-square w-full object-cover" />
      </button>
    </div>
    <Lightbox v-model:open="open" v-model:index="index" :items="images" />
  </div>
</template>
