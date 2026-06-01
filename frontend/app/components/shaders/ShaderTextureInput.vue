<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";

/**
 * Source input for texture components (ImageTexture / VideoTexture). The URL is
 * what serializes into exported code, so it is the primary field; the upload
 * button is a local-preview convenience that sets an in-memory object URL
 * (ephemeral - it won't survive export or reload).
 */
const props = defineProps({
  modelValue: { type: String, default: "" },
  kind: { type: String, default: "image" }, // "image" | "video"
});

const emit = defineEmits(["update:modelValue"]);

const fileInput = ref(null);

function pickFile() {
  fileInput.value?.click();
}

function onFile(event) {
  const file = event.target.files?.[0];
  if (file) emit("update:modelValue", URL.createObjectURL(file));
  event.target.value = "";
}
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center gap-x-1.5">
      <Input
        :model-value="modelValue"
        placeholder="https://…"
        class="font-mono text-xs sm:text-sm"
        @update:model-value="emit('update:modelValue', $event)"
      />
      <Button v-tippy="'Upload a local file'" variant="outline" size="iconSm" type="button" @click="pickFile">
        <Icon name="hugeicons:upload-01" />
      </Button>
      <input
        ref="fileInput"
        type="file"
        :accept="kind === 'video' ? 'video/*' : 'image/*'"
        class="hidden"
        @change="onFile"
      />
    </div>
    <p class="text-muted-foreground text-xs tracking-tight">
      Paste a URL (kept on export), or upload a local file for preview.
    </p>
  </div>
</template>
