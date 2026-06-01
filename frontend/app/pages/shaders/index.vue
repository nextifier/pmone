<script setup>
import { ref, computed } from "vue";
import { useShaderPresets } from "@/components/shaders-docs/useShaderPresets";
import PresetCard from "@/components/shaders/PresetCard.vue";

definePageMeta({ layout: "empty" });

usePageMeta(null, {
  title: "Shaders",
  description: "A gallery of GPU-accelerated shader presets with a live editor, ready to drop into any project.",
});

const { index } = useShaderPresets();

const activeCategory = ref("all");

const formatCategory = (c) =>
  c === "all" ? "All" : c.replace(/-/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());

const categories = computed(() => {
  const set = new Set(index.map((p) => p.category).filter(Boolean));
  return ["all", ...[...set].sort()];
});

const filtered = computed(() => {
  return index.filter((preset) => {
    if (activeCategory.value !== "all" && preset.category !== activeCategory.value) return false;
    return true;
  });
});
</script>

<template>
  <div class="bg-background min-h-screen">
    <ShadersHeader />

    <main class="container-wider pt-12 pb-24 sm:pt-16">
      <div class="max-w-3xl">
        <h1 class="text-primary text-4xl font-semibold tracking-tighter sm:text-5xl">
          GPU shaders, ready to ship.
        </h1>
        <p class="text-muted-foreground mt-4 text-base tracking-tight text-pretty sm:text-lg">
          A curated gallery of {{ index.length }} declarative WebGPU presets. Preview them live,
          tweak in the editor, and export to Vue code or an image.
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2">
          <Button to="/shaders/editor" size="lg">
            Open editor
            <Icon name="hugeicons:arrow-right-01" />
          </Button>
          <Button to="/shaders/docs" size="lg" variant="outline">Read the docs</Button>
        </div>
      </div>

      <div class="mt-12 flex flex-wrap gap-1.5">
        <Button
          v-for="category in categories"
          :key="category"
          size="sm"
          :variant="activeCategory === category ? 'default' : 'outline'"
          @click="activeCategory = category"
        >
          {{ formatCategory(category) }}
        </Button>
      </div>

      <p class="text-muted-foreground mt-6 text-sm tracking-tight">
        {{ filtered.length }} {{ filtered.length === 1 ? "preset" : "presets" }}
      </p>

      <div class="mt-4 grid gap-x-2 gap-y-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <PresetCard
          v-for="preset in filtered"
          :key="preset.id"
          :preset="preset"
        />
      </div>
    </main>
  </div>
</template>
