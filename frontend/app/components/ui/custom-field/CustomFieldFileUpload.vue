<template>
  <div class="space-y-2">
    <input
      ref="fileInputRef"
      type="file"
      class="hidden"
      :accept="acceptAttribute"
      :multiple="isMultipleFile"
      @change="handleFileSelect"
    />

    <!-- File cards: already-submitted + newly staged + in-progress upload -->
    <div v-if="hasVisibleFiles" class="space-y-2">
      <!-- Already-submitted files (populated on load from existingFiles) -->
      <Attachment v-for="file in visibleExistingFiles" :key="`existing-${file.id}`" state="done">
        <AttachmentMedia :variant="isImage(file) ? 'image' : 'icon'">
          <img v-if="isImage(file)" :src="file.url" :alt="file.name" />
          <Icon v-else :name="fileIcon(file.name)" class="size-5" />
        </AttachmentMedia>
        <AttachmentContent>
          <AttachmentTitle>{{ file.name }}</AttachmentTitle>
          <AttachmentDescription>{{ describeFile(file) }}</AttachmentDescription>
        </AttachmentContent>
        <AttachmentActions>
          <AttachmentAction
            v-if="canUpload && !isMultipleFile"
            type="button"
            aria-label="Replace file"
            @click="startReplace"
          >
            <Icon name="lucide:refresh-cw" class="size-4" />
          </AttachmentAction>
        </AttachmentActions>
        <AttachmentTrigger
          v-if="file.url"
          as="a"
          :href="file.url"
          target="_blank"
          rel="noopener"
          :aria-label="`Open ${file.name}`"
        />
      </Attachment>

      <!-- Newly staged (not-yet-submitted) uploads -->
      <Attachment v-for="file in uploadedFiles" :key="`tmp-${file.folder}`" state="done">
        <AttachmentMedia>
          <Icon :name="fileIcon(file.name)" class="size-5" />
        </AttachmentMedia>
        <AttachmentContent>
          <AttachmentTitle>{{ file.name }}</AttachmentTitle>
          <AttachmentDescription>{{ describeFile(file) }}</AttachmentDescription>
        </AttachmentContent>
        <AttachmentActions>
          <AttachmentAction type="button" aria-label="Remove file" @click="removeFile(file)">
            <Icon name="lucide:x" class="size-4" />
          </AttachmentAction>
        </AttachmentActions>
      </Attachment>

      <!-- In-progress upload -->
      <Attachment v-if="uploadingFile" state="uploading">
        <AttachmentMedia>
          <Spinner class="size-5" />
        </AttachmentMedia>
        <AttachmentContent>
          <AttachmentTitle>{{ uploadingName }}</AttachmentTitle>
          <AttachmentDescription>Uploading... {{ uploadProgress }}%</AttachmentDescription>
        </AttachmentContent>
      </Attachment>
    </div>

    <!-- Upload area (empty, adding, or replacing) -->
    <div
      v-if="showDropArea"
      class="rounded-md border border-dashed p-4 transition-colors"
      :class="isDragging ? 'border-primary bg-primary/5' : 'border-border'"
      @dragover.prevent="canUpload && (isDragging = true)"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <div class="flex flex-col items-center gap-y-2 text-center">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="!canUpload || uploadingFile || maxFilesReached"
          @click="fileInputRef?.click()"
        >
          <Icon name="lucide:paperclip" class="size-4" />
          <span>{{ addLabel }}</span>
        </Button>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">or drag and drop here</p>
      </div>
    </div>

    <!-- Cancel a single-file replace -->
    <button
      v-if="isReplacing && !isMultipleFile && !uploadedFiles.length && !uploadingFile"
      type="button"
      class="text-muted-foreground hover:text-foreground text-xs tracking-tight sm:text-sm"
      @click="cancelReplace"
    >
      Cancel
    </button>

    <p v-if="fileConstraintText" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
      {{ fileConstraintText }}
    </p>
    <FieldError :errors="fileError ? [fileError] : []" />
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import {
  Attachment,
  AttachmentAction,
  AttachmentActions,
  AttachmentContent,
  AttachmentDescription,
  AttachmentMedia,
  AttachmentTitle,
  AttachmentTrigger,
} from "../attachment";
import { Button } from "../button";
import { FieldError } from "../field";
import { MAX_UPLOAD_SIZE_KB } from "./core";

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  disabled: { type: Boolean, default: false },
  // Already-submitted files for this field: [{ id, name, url, size }].
  existingFiles: { type: Array, default: () => [] },
  // async (file, onProgress) => { folder, name?, size? }
  uploadHandler: { type: Function, default: null },
  // async (folder) => void
  revertHandler: { type: Function, default: null },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const fileInputRef = ref(null);
const uploadingFile = ref(false);
const fileError = ref(null);
const uploadedFiles = ref([]);
const isReplacing = ref(false);

// No handler (preview / render mode) disables the whole control.
const canUpload = computed(() => !props.disabled && typeof props.uploadHandler === "function");

const isMultipleFile = computed(() => !!props.field.settings?.multiple);
const maxFiles = computed(() => Number(props.field.validation?.max_files) || 5);

const existingFiles = computed(() => props.existingFiles || []);

// Single-file replace hides the current file until the new one is confirmed.
const visibleExistingFiles = computed(() => {
  if (isMultipleFile.value) {
    return existingFiles.value;
  }
  if (isReplacing.value || uploadedFiles.value.length || uploadingFile.value) {
    return [];
  }
  return existingFiles.value;
});

const hasVisibleFiles = computed(
  () => visibleExistingFiles.value.length > 0 || uploadedFiles.value.length > 0 || uploadingFile.value
);

const totalFileCount = computed(() => existingFiles.value.length + uploadedFiles.value.length);
const maxFilesReached = computed(
  () => isMultipleFile.value && totalFileCount.value >= maxFiles.value
);

const showDropArea = computed(() => {
  if (!canUpload.value || uploadingFile.value) {
    return false;
  }
  if (isMultipleFile.value) {
    return !maxFilesReached.value;
  }
  // Single: only when there is nothing to show, or the user chose to replace.
  return uploadedFiles.value.length === 0 && (existingFiles.value.length === 0 || isReplacing.value);
});

const addLabel = computed(() => (isMultipleFile.value ? "Add file" : "Choose file"));

const allowedExtensions = computed(() =>
  (props.field.validation?.allowed_file_types || []).map((ext) =>
    String(ext).toLowerCase().replace(/^\./, "")
  )
);

const acceptAttribute = computed(() =>
  allowedExtensions.value.length ? allowedExtensions.value.map((e) => `.${e}`).join(",") : undefined
);

// Clamp to the real server ceiling so the UI never promises more than the
// backend can accept (mirrors PublicFormService's `min(..., 20480)` clamp).
const maxFileSizeKb = computed(() =>
  Math.min(Number(props.field.validation?.max_file_size) || MAX_UPLOAD_SIZE_KB, MAX_UPLOAD_SIZE_KB)
);

const fileConstraintText = computed(() => {
  const parts = [];
  if (allowedExtensions.value.length) parts.push(allowedExtensions.value.join(", ").toUpperCase());
  parts.push(`max ${formatFileSize(maxFileSizeKb.value * 1024)}`);
  if (isMultipleFile.value) parts.push(`up to ${maxFiles.value} files`);
  return parts.join(" · ");
});

const formatFileSize = (bytes) => {
  if (!bytes) return "";
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, "")} MB`;
};

const extensionOf = (name) => String(name || "").split(".").pop()?.toLowerCase() || "";

const isImage = (file) =>
  ["png", "jpg", "jpeg", "webp", "gif", "svg"].includes(extensionOf(file.name)) && !!file.url;

const fileIcon = (name) => {
  const ext = extensionOf(name);
  if (["pdf"].includes(ext)) return "lucide:file-text";
  if (["doc", "docx"].includes(ext)) return "lucide:file-text";
  if (["xls", "xlsx", "csv"].includes(ext)) return "lucide:sheet";
  if (["png", "jpg", "jpeg", "webp", "gif", "svg"].includes(ext)) return "lucide:image";
  return "lucide:file";
};

const describeFile = (file) => {
  const ext = extensionOf(file.name).toUpperCase();
  return [ext, formatFileSize(file.size)].filter(Boolean).join(" · ");
};

const syncFileModel = () => {
  const folders = uploadedFiles.value.map((f) => f.folder);
  emit("update:modelValue", isMultipleFile.value ? folders : folders[0] || null);
};

const isDragging = ref(false);
const uploadProgress = ref(0);
const uploadingName = ref(null);

const startReplace = () => {
  isReplacing.value = true;
};

const cancelReplace = () => {
  isReplacing.value = false;
};

// When the persisted files change (i.e. a submit landed), drop any staged
// upload + exit replace mode so the freshly-saved file reads back cleanly.
watch(
  () => existingFiles.value.map((f) => f.id).join(","),
  () => {
    uploadedFiles.value = [];
    isReplacing.value = false;
  }
);

const handleDrop = (event) => {
  isDragging.value = false;
  if (!canUpload.value) return;
  processFiles(Array.from(event.dataTransfer?.files || []));
};

const handleFileSelect = (event) => {
  const files = Array.from(event.target.files || []);
  event.target.value = "";
  processFiles(files);
};

const processFiles = async (files) => {
  if (!files.length || !canUpload.value) return;

  fileError.value = null;

  for (const file of files) {
    if (maxFilesReached.value) {
      fileError.value = `You can upload up to ${maxFiles.value} files.`;
      break;
    }

    const extension = file.name.split(".").pop()?.toLowerCase();
    if (allowedExtensions.value.length && !allowedExtensions.value.includes(extension)) {
      fileError.value = `"${file.name}" is not an accepted file type.`;
      continue;
    }

    if (file.size > maxFileSizeKb.value * 1024) {
      fileError.value = `"${file.name}" exceeds the ${formatFileSize(maxFileSizeKb.value * 1024)} limit.`;
      continue;
    }

    await uploadFile(file);
  }
};

const uploadFile = async (file) => {
  uploadingFile.value = true;
  uploadProgress.value = 0;
  uploadingName.value = file.name;
  emit("uploading", true);

  try {
    const response = await props.uploadHandler(file, (progress) => {
      uploadProgress.value = progress;
    });

    if (!isMultipleFile.value && uploadedFiles.value.length) {
      await revertFolder(uploadedFiles.value[0].folder);
      uploadedFiles.value = [];
    }

    uploadedFiles.value.push({
      folder: response.folder,
      name: response.name ?? file.name,
      size: response.size ?? file.size,
    });
    syncFileModel();
  } catch (e) {
    fileError.value =
      e?.data?.errors?.file?.[0] || e?.data?.message || "Upload failed. Please try again.";
  } finally {
    uploadingFile.value = false;
    uploadingName.value = null;
    emit("uploading", false);
  }
};

const removeFile = async (file) => {
  uploadedFiles.value = uploadedFiles.value.filter((f) => f.folder !== file.folder);
  syncFileModel();
  await revertFolder(file.folder);
};

const revertFolder = async (folder) => {
  if (typeof props.revertHandler !== "function") return;
  try {
    await props.revertHandler(folder);
  } catch {
    // Temp uploads are cleaned up server-side eventually; ignore revert errors.
  }
};
</script>
