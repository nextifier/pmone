<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div class="flex items-start justify-between gap-4 rounded-lg border p-4">
      <div class="space-y-1">
        <Label class="text-sm tracking-tight sm:text-base">Enable scan sounds</Label>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Play a sound on every scan result. Vibration on supported devices stays on regardless of this setting.
        </p>
      </div>
      <Switch v-model="form.enabled" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-3 rounded-lg border p-4">
        <div class="flex items-center justify-between gap-2">
          <Label>Check-in success</Label>
          <Button
            v-if="form.success_url"
            type="button"
            variant="ghost"
            size="iconSm"
            aria-label="Preview success sound"
            @click="preview(form.success_url)"
          >
            <Icon name="hugeicons:play" class="size-4 shrink-0" />
          </Button>
        </div>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Plays when a ticket is valid and checked in.
        </p>
        <InputFile
          v-model="successFiles"
          :accepted-file-types="audioMimeTypes"
          :max-files="1"
          max-file-size="5MB"
        />
        <label
          v-if="form.success_url"
          class="text-muted-foreground flex items-center gap-2 text-xs tracking-tight sm:text-sm"
        >
          <Checkbox v-model="deleteSuccess" />
          <span>Remove current sound</span>
        </label>
      </div>

      <div class="space-y-3 rounded-lg border p-4">
        <div class="flex items-center justify-between gap-2">
          <Label>Scan failed</Label>
          <Button
            v-if="form.failed_url"
            type="button"
            variant="ghost"
            size="iconSm"
            aria-label="Preview failed sound"
            @click="preview(form.failed_url)"
          >
            <Icon name="hugeicons:play" class="size-4 shrink-0" />
          </Button>
        </div>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Plays on an invalid ticket or one that was already redeemed.
        </p>
        <InputFile
          v-model="failedFiles"
          :accepted-file-types="audioMimeTypes"
          :max-files="1"
          max-file-size="5MB"
        />
        <label
          v-if="form.failed_url"
          class="text-muted-foreground flex items-center gap-2 text-xs tracking-tight sm:text-sm"
        >
          <Checkbox v-model="deleteFailed" />
          <span>Remove current sound</span>
        </label>
      </div>
    </div>

    <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
      MP3, WAV or OGG. Keep it short (under ~1 second) so scanning stays fast.
    </p>

    <div class="flex justify-end border-t pt-4">
      <Button type="submit" :disabled="saving">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ submitLabel }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from "vue";
import InputFile from "@/components/InputFile.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  submitLabel: { type: String, default: "Save Sounds" },
});

const emit = defineEmits(["submit"]);

const audioMimeTypes = [
  "audio/mpeg",
  "audio/mp3",
  "audio/wav",
  "audio/x-wav",
  "audio/ogg",
  "audio/mp4",
  "audio/aac",
  "audio/webm",
];

const successFiles = ref([]);
const failedFiles = ref([]);
const deleteSuccess = ref(false);
const deleteFailed = ref(false);

const blank = () => ({ success_url: "", failed_url: "", enabled: true });
const form = reactive(blank());

watch(
  () => props.modelValue,
  (val) => {
    Object.assign(form, { ...blank(), ...(val || {}) });
    successFiles.value = [];
    failedFiles.value = [];
    deleteSuccess.value = false;
    deleteFailed.value = false;
  },
  { immediate: true, deep: true }
);

let previewEl = null;
const preview = (url) => {
  if (!url) return;
  try {
    if (previewEl) previewEl.pause();
    previewEl = new Audio(url);
    previewEl.play().catch(() => {});
  } catch {
    /* best-effort */
  }
};

const tmpFolder = (files) => {
  const id = files?.[0];
  return typeof id === "string" && id.startsWith("tmp-") ? id : null;
};

const handleSubmit = () => {
  emit("submit", {
    success_url: form.success_url,
    failed_url: form.failed_url,
    enabled: !!form.enabled,
    tmp_success: tmpFolder(successFiles.value),
    tmp_failed: tmpFolder(failedFiles.value),
    delete_success: !!deleteSuccess.value,
    delete_failed: !!deleteFailed.value,
  });
};
</script>
