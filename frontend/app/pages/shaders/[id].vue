<script setup>
import { ref, computed } from "vue";
import { useShaderPresets } from "@/components/shaders-docs/useShaderPresets";
import { generateShaderCode } from "@/components/shaders/generateShaderCode";
import ShaderCanvas from "@/components/shaders/ShaderCanvas.vue";
import CodeBlock from "@/components/ui-docs/CodeBlock.vue";

definePageMeta({ layout: "empty" });

const route = useRoute();
const id = computed(() => route.params.id);

const { getPreset, collectionsById } = useShaderPresets();
const { downloadImage } = useShaderExport();

const { data: preset } = await useAsyncData(
  () => `shader-preset-${id.value}`,
  () => getPreset(id.value),
  { watch: [id] },
);

const collection = computed(() =>
  preset.value ? collectionsById.get(preset.value.collectionId) : null,
);

const code = computed(() =>
  preset.value
    ? generateShaderCode(preset.value.config, {
        colorSpace: preset.value.colorSpace,
        toneMapping: preset.value.toneMapping,
      })
    : "",
);

const canvasRef = ref(null);
const downloading = ref(false);

async function download() {
  if (!canvasRef.value) return;
  downloading.value = true;
  try {
    await downloadImage(canvasRef.value, { format: "jpeg", filename: preset.value.title || "shader" });
  } finally {
    downloading.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => (preset.value ? `${preset.value.title} · Shaders` : "Shaders")),
  description: computed(() => preset.value?.description?.slice(0, 160) || ""),
});
</script>

<template>
  <div class="bg-background min-h-screen">
    <ShadersHeader />

    <main v-if="preset" class="mx-auto max-w-5xl px-4 pt-8 pb-24 sm:px-6 lg:px-8">
      <Button to="/shaders" variant="ghost" size="sm" class="text-muted-foreground -ml-2 mb-4">
        <Icon name="hugeicons:arrow-left-01" />
        Gallery
      </Button>

      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tighter sm:text-3xl">{{ preset.title }}</h1>
          <NuxtLink
            v-if="collection"
            to="/shaders"
            class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
          >
            {{ collection.name }}
          </NuxtLink>
        </div>
        <div class="flex items-center gap-x-2">
          <Button variant="outline" :disabled="downloading" @click="download">
            <Icon :name="downloading ? 'hugeicons:loading-03' : 'hugeicons:image-download-02'" :class="downloading && 'animate-spin'" />
            JPG
          </Button>
          <Button :to="`/shaders/editor?preset=${preset.id}`">
            <Icon name="hugeicons:sliders-horizontal" />
            Open in editor
          </Button>
        </div>
      </div>

      <div class="bg-muted ring-border mt-6 aspect-video overflow-hidden rounded-2xl ring-1">
        <ShaderCanvas
          ref="canvasRef"
          :config="preset.config"
          :color-space="preset.colorSpace"
          :tone-mapping="preset.toneMapping"
          class="size-full"
        />
      </div>

      <p v-if="preset.description" class="text-muted-foreground mt-6 max-w-3xl text-sm tracking-tight text-pretty sm:text-base">
        {{ preset.description }}
      </p>

      <div class="mt-8">
        <h2 class="mb-3 text-sm font-medium tracking-tight">Code</h2>
        <CodeBlock :code="code" language="vue" />
      </div>
    </main>

    <main v-else class="mx-auto max-w-5xl px-4 py-24 text-center sm:px-6 lg:px-8">
      <p class="text-muted-foreground text-sm tracking-tight">Preset not found.</p>
      <Button to="/shaders" variant="outline" class="mt-4">Back to gallery</Button>
    </main>
  </div>
</template>
