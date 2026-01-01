<template>
  <div class="min-h-screen">
    <div class="container pt-8 pb-16">
      <div class="mb-16 text-center">
        <h1 class="text-4xl font-semibold tracking-tighter sm:text-5xl lg:text-6xl">
          Gradient Collection
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg">
          Beautiful animated gradient backgrounds for your next project. Hover to see them in
          action.
        </p>
      </div>

      <!-- All Gradients -->
      <div class="grid grid-cols-[repeat(auto-fit,minmax(360px,1fr))] gap-2.5">
        <div
          v-for="gradient in allGradients"
          :key="gradient.name"
          class="group relative aspect-4/3 cursor-pointer overflow-hidden rounded-2xl bg-black ring ring-white/10 transition-all duration-300 ring-inset hover:ring-white/30"
          @click="openGradientDialog(gradient.name)"
        >
          <ClientOnly>
            <GradientBlob :preset="gradient.name" position="center" speed="normal" />
          </ClientOnly>
          <div class="absolute bottom-4 left-4 z-10">
            <Button variant="outline" class="text-sm font-medium tracking-tight text-white">
              {{ gradient.label }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Fullscreen Gradient Dialog -->
    <DialogResponsive
      v-model:open="isDialogOpen"
      :is-responsive="true"
      dialog-max-width="600px"
      :drawer-close-button="true"
    >
      <template #default>
        <div class="relative h-[80vh] w-full overflow-hidden bg-black md:h-[90vh]">
          <ClientOnly>
            <GradientBlob
              v-if="selectedPreset"
              :preset="selectedPreset"
              position="center"
              speed="normal"
              :animate="isDialogOpen"
            />
          </ClientOnly>
          <div class="absolute inset-x-0 bottom-8 z-10 text-center">
            <span
              class="rounded-full bg-black/60 px-4 py-2 text-sm font-medium text-white capitalize backdrop-blur-sm"
            >
              {{ selectedPreset?.replace(/-/g, " ") }}
            </span>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup lang="ts">
type PresetName =
  | "aurora"
  | "sunset"
  | "ocean"
  | "forest"
  | "neon"
  | "candy"
  | "midnight"
  | "ember"
  | "cosmic"
  | "mint"
  | "lavender"
  | "gold"
  | "framer-wireframer"
  | "framer-translate"
  | "framer-plugins"
  | "orange-sunset"
  | "orange-tangerine"
  | "orange-peach"
  | "orange-amber"
  | "orange-coral"
  | "orange-fire"
  | "orange-honey"
  | "orange-copper"
  | "orange-apricot"
  | "orange-flame";

definePageMeta({
  layout: "default",
});

useHead({
  title: "Gradient Collection - PM One",
});

// Dialog state
const isDialogOpen = ref(false);
const selectedPreset = ref<PresetName | null>(null);

const openGradientDialog = (preset: PresetName) => {
  selectedPreset.value = preset;
  isDialogOpen.value = true;
};

// All gradient presets combined
const allGradients = [
  { name: "framer-wireframer" as const, label: "Wireframer" },
  { name: "framer-translate" as const, label: "AI Translate" },
  { name: "framer-plugins" as const, label: "AI Plugins" },
  { name: "aurora" as const, label: "Aurora" },
  { name: "sunset" as const, label: "Sunset" },
  { name: "ocean" as const, label: "Ocean" },
  { name: "forest" as const, label: "Forest" },
  { name: "neon" as const, label: "Neon" },
  { name: "candy" as const, label: "Candy" },
  { name: "midnight" as const, label: "Midnight" },
  { name: "ember" as const, label: "Ember" },
  { name: "cosmic" as const, label: "Cosmic" },
  { name: "mint" as const, label: "Mint" },
  { name: "lavender" as const, label: "Lavender" },
  { name: "gold" as const, label: "Gold" },
  // Orange gradient collection
  { name: "orange-sunset" as const, label: "Orange Sunset" },
  { name: "orange-tangerine" as const, label: "Tangerine" },
  { name: "orange-peach" as const, label: "Peach" },
  { name: "orange-amber" as const, label: "Amber" },
  { name: "orange-coral" as const, label: "Coral" },
  { name: "orange-fire" as const, label: "Fire" },
  { name: "orange-honey" as const, label: "Honey" },
  { name: "orange-copper" as const, label: "Copper" },
  { name: "orange-apricot" as const, label: "Apricot" },
  { name: "orange-flame" as const, label: "Flame" },
];
</script>
