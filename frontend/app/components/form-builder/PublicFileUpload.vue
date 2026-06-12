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
    <div
      class="rounded-md border border-dashed p-4 transition-colors"
      :class="isDragging ? 'border-primary bg-primary/5' : 'border-border'"
      @dragover.prevent="!preview && (isDragging = true)"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <div class="flex flex-col items-center gap-y-2 text-center">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="preview || uploadingFile || maxFilesReached"
          @click="fileInputRef?.click()"
        >
          <Spinner v-if="uploadingFile" class="size-4" />
          <Icon v-else name="lucide:paperclip" class="size-4" />
          <span>{{ uploadingFile ? "Uploading..." : isMultipleFile ? "Add file" : "Choose file" }}</span>
        </Button>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">or drag and drop here</p>
      </div>
      <div v-if="uploadingFile" class="mt-3 space-y-1">
        <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full">
          <div
            class="bg-primary h-full rounded-full transition-[width] duration-150"
            :style="{ width: `${uploadProgress}%` }"
          />
        </div>
        <p class="text-muted-foreground truncate text-xs tracking-tight tabular-nums">
          {{ uploadingName }} - {{ uploadProgress }}%
        </p>
      </div>
    </div>
    <ul v-if="uploadedFiles.length" class="space-y-1.5">
      <li
        v-for="file in uploadedFiles"
        :key="file.folder"
        class="bg-muted/50 border-border flex items-center gap-x-2 rounded-md border px-3 py-2"
      >
        <Icon name="lucide:file" class="text-muted-foreground size-4 shrink-0" />
        <span class="min-w-0 flex-1 truncate text-sm tracking-tight">{{ file.name }}</span>
        <span class="text-muted-foreground shrink-0 text-xs tabular-nums">
          {{ formatFileSize(file.size) }}
        </span>
        <button
          type="button"
          aria-label="Remove file"
          class="text-muted-foreground hover:text-destructive shrink-0 rounded p-0.5 transition-colors"
          @click="removeFile(file)"
        >
          <Icon name="lucide:x" class="size-4" />
        </button>
      </li>
    </ul>
    <p v-if="fileConstraintText" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
      {{ fileConstraintText }}
    </p>
    <p v-if="fileError" class="text-destructive text-sm tracking-tight">{{ fileError }}</p>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  formSlug: { type: String, default: null },
  preview: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const fileInputRef = ref(null);
const uploadingFile = ref(false);
const fileError = ref(null);
const uploadedFiles = ref([]);

const isMultipleFile = computed(() => !!props.field.settings?.multiple);
const maxFiles = computed(() => Number(props.field.validation?.max_files) || 5);
const maxFilesReached = computed(
  () => isMultipleFile.value && uploadedFiles.value.length >= maxFiles.value
);

const allowedExtensions = computed(() =>
  (props.field.validation?.allowed_file_types || []).map((ext) =>
    String(ext).toLowerCase().replace(/^\./, "")
  )
);

const acceptAttribute = computed(() =>
  allowedExtensions.value.length ? allowedExtensions.value.map((e) => `.${e}`).join(",") : undefined
);

const maxFileSizeKb = computed(() => Number(props.field.validation?.max_file_size) || 20480);

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
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const syncFileModel = () => {
  const folders = uploadedFiles.value.map((f) => f.folder);
  emit("update:modelValue", isMultipleFile.value ? folders : folders[0] || null);
};

const isDragging = ref(false);
const uploadProgress = ref(0);
const uploadingName = ref(null);

const handleDrop = (event) => {
  isDragging.value = false;
  if (props.preview) return;
  processFiles(Array.from(event.dataTransfer?.files || []));
};

const handleFileSelect = (event) => {
  const files = Array.from(event.target.files || []);
  event.target.value = "";
  processFiles(files);
};

const processFiles = async (files) => {
  if (!files.length || !props.formSlug || props.preview) return;

  fileError.value = null;

  for (const file of files) {
    if (isMultipleFile.value && uploadedFiles.value.length >= maxFiles.value) {
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

// XMLHttpRequest instead of $fetch so we can report upload progress
const xhrUpload = (url, formData, onProgress) =>
  new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", url);

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100));
      }
    };

    xhr.onload = () => {
      let data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch {
        data = null;
      }

      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(data);
      } else {
        reject({ data });
      }
    };

    xhr.onerror = () => reject(new Error("Upload failed"));
    xhr.send(formData);
  });

const uploadFile = async (file) => {
  uploadingFile.value = true;
  uploadProgress.value = 0;
  uploadingName.value = file.name;
  emit("uploading", true);

  try {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("field", props.field.ulid);

    const apiUrl = useRuntimeConfig().public.apiUrl;
    const response = await xhrUpload(
      `${apiUrl}/api/public/forms/${props.formSlug}/upload`,
      formData,
      (progress) => {
        uploadProgress.value = progress;
      }
    );

    if (!isMultipleFile.value && uploadedFiles.value.length) {
      await revertFolder(uploadedFiles.value[0].folder);
      uploadedFiles.value = [];
    }

    uploadedFiles.value.push({ folder: response.folder, name: file.name, size: file.size });
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
  try {
    const apiUrl = useRuntimeConfig().public.apiUrl;
    await $fetch(`${apiUrl}/api/public/forms/${props.formSlug}/upload`, {
      method: "DELETE",
      body: folder,
    });
  } catch {
    // Temp uploads are cleaned up server-side eventually; ignore revert errors.
  }
};
</script>
