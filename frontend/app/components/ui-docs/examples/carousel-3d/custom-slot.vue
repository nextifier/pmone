<script setup>
import { Carousel3d } from "@/components/ui/carousel-3d";

const items = [
  { label: "Strategy", tone: "from-info/30" },
  { label: "Design", tone: "from-primary/30" },
  { label: "Build", tone: "from-success/30" },
  { label: "Launch", tone: "from-warning/30" },
  { label: "Scale", tone: "from-destructive/30" },
];
</script>

<template>
  <Carousel3d :items="items" card-aspect="3 / 4" class="w-full">
    <template #item="{ item }">
      <div :class="['flex size-full items-center justify-center bg-linear-to-b to-card', item.tone]">
        <span class="text-lg font-medium tracking-tighter">{{ item.label }}</span>
      </div>
    </template>
  </Carousel3d>
</template>
