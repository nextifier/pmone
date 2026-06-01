<script setup>
import { ref, shallowRef, onMounted } from "vue";
import ShaderTree from "./ShaderTree.vue";

/**
 * The single wrapper every shader in this app renders through. It hard-codes
 * `:disable-telemetry="true"` (a project rule - see the shaders skill) so no
 * caller can ever forget it.
 *
 * The `shaders/vue` library is WebGPU-only and is imported DYNAMICALLY on the
 * client (never during SSR). Attributes (class, style) fall through to the
 * canvas and the loading skeleton, so callers size it with normal Tailwind.
 */
defineOptions({ inheritAttrs: false });

defineProps({
  // { components: ComponentConfig[] } - the reactive preset tree.
  config: {
    type: Object,
    required: true,
  },
  colorSpace: {
    type: String,
    default: "p3-linear",
  },
  toneMapping: {
    type: String,
    default: "aces",
  },
  isPreview: {
    type: Boolean,
    default: false,
  },
  enablePerformanceTracking: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["ready"]);

const shaderRef = ref(null);
const lib = shallowRef(null);
const ShaderComp = shallowRef(null);

onMounted(async () => {
  const mod = await import("shaders/vue");
  lib.value = mod;
  ShaderComp.value = mod.Shader;
});

defineExpose({
  captureImage: (options) => shaderRef.value?.captureImage(options),
  captureScreenshot: (maxWidth) => shaderRef.value?.captureScreenshot(maxWidth),
  getCanvas: () => shaderRef.value?.getCanvas() ?? null,
  getPerformanceStats: () => shaderRef.value?.getPerformanceStats(),
  beginRecordingResolution: (pixelRatio) => shaderRef.value?.beginRecordingResolution(pixelRatio),
});
</script>

<template>
  <ClientOnly>
    <component
      :is="ShaderComp"
      v-if="ShaderComp"
      ref="shaderRef"
      v-bind="$attrs"
      :disable-telemetry="true"
      :color-space="colorSpace"
      :tone-mapping="toneMapping"
      :is-preview="isPreview"
      :enable-performance-tracking="enablePerformanceTracking"
      @ready="$emit('ready')"
    >
      <ShaderTree :nodes="config.components" :lib="lib" />
    </component>
    <Skeleton v-else v-bind="$attrs" />

    <template #fallback>
      <Skeleton v-bind="$attrs" />
    </template>
  </ClientOnly>
</template>
