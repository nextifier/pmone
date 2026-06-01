<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from "vue";
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

// Fullscreen preview. Uses the native Fullscreen API where supported (desktop,
// Android) and always falls back to a fixed inset-0 overlay so it still covers
// the whole viewport on iOS Safari, which can't fullscreen arbitrary elements.
const isFullscreen = ref(false);
const previewRef = ref(null);

async function enterFullscreen() {
  isFullscreen.value = true;
  await nextTick();
  const el = previewRef.value;
  try {
    if (el?.requestFullscreen) {
      await el.requestFullscreen();
    } else if (el?.webkitRequestFullscreen) {
      el.webkitRequestFullscreen();
    }
  } catch {
    /* overlay still covers the viewport where the API is unavailable */
  }
}

async function exitFullscreen() {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    } else if (document.webkitFullscreenElement) {
      document.webkitExitFullscreen?.();
    }
  } catch {
    /* ignore */
  }
  isFullscreen.value = false;
}

function toggleFullscreen() {
  if (isFullscreen.value) {
    exitFullscreen();
  } else {
    enterFullscreen();
  }
}

function syncFullscreen() {
  const active = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
  if (!active && isFullscreen.value) isFullscreen.value = false;
}

function onKeydown(event) {
  if (event.key === "Escape" && isFullscreen.value) exitFullscreen();
}

onMounted(() => {
  document.addEventListener("fullscreenchange", syncFullscreen);
  document.addEventListener("webkitfullscreenchange", syncFullscreen);
  window.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener("fullscreenchange", syncFullscreen);
  document.removeEventListener("webkitfullscreenchange", syncFullscreen);
  window.removeEventListener("keydown", onKeydown);
});

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
            <Icon :name="downloading ? 'hugeicons:loading-03' : 'hugeicons:download-01'" :class="downloading && 'animate-spin'" />
            JPG
          </Button>
          <Button :to="`/shaders/editor?preset=${preset.id}`">
            <Icon name="hugeicons:sliders-horizontal" />
            Open in editor
          </Button>
        </div>
      </div>

      <Teleport to="body" :disabled="!isFullscreen">
        <div
          ref="previewRef"
          :class="
            isFullscreen
              ? 'fixed inset-0 z-[100] bg-background'
              : 'bg-muted ring-border relative mt-6 aspect-video overflow-hidden rounded-2xl ring-1'
          "
        >
          <ShaderCanvas
            ref="canvasRef"
            :config="preset.config"
            :color-space="preset.colorSpace"
            :tone-mapping="preset.toneMapping"
            class="size-full"
          />
          <Button
            variant="secondary"
            size="iconSm"
            class="absolute top-3 right-3 z-10 opacity-80 transition hover:opacity-100"
            :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
            @click="toggleFullscreen"
          >
            <Icon
              :name="
                isFullscreen ? 'hugeicons:square-arrow-shrink-02' : 'hugeicons:square-arrow-expand-02'
              "
            />
          </Button>
        </div>
      </Teleport>

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
