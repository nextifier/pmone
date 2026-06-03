<script setup>
import { computed, ref } from "vue";
import { Input } from "@/components/ui/input";
import { imageFileToSdf } from "./sdf/imageToSdf";

/**
 * Control for the `shapeSdfUrl` prop of the shape-effect components (Glass / Neon /
 * Emboss / Crystal / SmokeFill). Upload an SVG or PNG logo and it is converted to a
 * Signed Distance Field in the browser, then handed to the shader as an in-memory
 * object URL for an instant live preview. The text field remains the source of
 * truth on export, so a stable URL (e.g. a committed `/shaders/sdf/*.bin`) can be
 * pasted in - use the download button to obtain that `.bin`.
 */
const props = defineProps({
  modelValue: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const fileInput = ref(null);
const converting = ref(false);
const error = ref("");
const shapeName = ref("");

let generatedBlob = null;
let generatedUrl = "";

const hasShape = computed(() => Boolean(props.modelValue));
const canDownload = computed(() => Boolean(generatedBlob));

function pickFile() {
  error.value = "";
  fileInput.value?.click();
}

async function onFile(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) {
    return;
  }
  converting.value = true;
  error.value = "";
  try {
    const { blob } = await imageFileToSdf(file);
    if (generatedUrl) {
      URL.revokeObjectURL(generatedUrl);
    }
    generatedBlob = blob;
    generatedUrl = URL.createObjectURL(blob);
    shapeName.value = file.name.replace(/\.(svg|png)$/i, "");
    emit("update:modelValue", generatedUrl);
  } catch (e) {
    error.value = e?.message || "Failed to convert the file.";
  } finally {
    converting.value = false;
  }
}

function downloadBin() {
  if (!generatedBlob) {
    return;
  }
  const url = URL.createObjectURL(generatedBlob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${shapeName.value || "shape"}_sdf.bin`;
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function clearShape() {
  if (generatedUrl) {
    URL.revokeObjectURL(generatedUrl);
    generatedUrl = "";
  }
  generatedBlob = null;
  shapeName.value = "";
  emit("update:modelValue", "");
}
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center gap-x-1.5">
      <Input
        :model-value="modelValue"
        placeholder="URL .bin…"
        class="font-mono text-xs sm:text-sm"
        @update:model-value="emit('update:modelValue', $event)"
      />
      <Button
        v-tippy="'Upload SVG / PNG'"
        variant="outline"
        size="iconSm"
        type="button"
        :disabled="converting"
        @click="pickFile"
      >
        <Icon
          :name="converting ? 'hugeicons:loading-03' : 'hugeicons:upload-01'"
          :class="converting && 'animate-spin'"
        />
      </Button>
      <Button
        v-if="canDownload"
        v-tippy="'Download .bin'"
        variant="outline"
        size="iconSm"
        type="button"
        @click="downloadBin"
      >
        <Icon name="hugeicons:download-01" />
      </Button>
      <Button
        v-if="hasShape"
        v-tippy="'Remove shape'"
        variant="outline"
        size="iconSm"
        type="button"
        @click="clearShape"
      >
        <Icon name="hugeicons:delete-02" />
      </Button>
      <input
        ref="fileInput"
        type="file"
        accept=".svg,.png,image/svg+xml,image/png"
        class="hidden"
        @change="onFile"
      />
    </div>
    <p v-if="error" class="text-destructive text-xs tracking-tight">{{ error }}</p>
    <p v-else-if="shapeName" class="text-muted-foreground text-xs tracking-tight">
      Converted from {{ shapeName }}. Download the .bin to use on export.
    </p>
    <p v-else class="text-muted-foreground text-xs tracking-tight">
      Upload an SVG/PNG logo (auto-converted to SDF), or paste an existing .bin URL.
    </p>
  </div>
</template>
