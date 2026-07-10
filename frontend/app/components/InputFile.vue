<template>
  <ClientOnly>
    <FilePond
      ref="pond"
      name="file"
      :server="server"
      :accepted-file-types="acceptedFileTypes"
      :max-file-size="maxFileSize"
      :allow-multiple="allowMultiple"
      :max-files="maxFiles"
      :max-parallel-uploads="maxParallelUploads"
      @addfile="handleAddFile"
      @processfile="handleProcessFile"
      @processfiles="handleProcessFiles"
      @removefile="handleRemoveFile"
      label-idle='Drag & Drop your files or <span class="filepond--label-action">Browse</span>'
    />

    <template #fallback>
      <div class="min-h-20 rounded-md border"></div>
    </template>
  </ClientOnly>
</template>

<script setup>
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css";
import "filepond/dist/filepond.min.css";
import vueFilePond from "vue-filepond";

import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size";
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type";
import FilePondPluginImagePreview from "filepond-plugin-image-preview";

const FilePond = vueFilePond(
  FilePondPluginImagePreview,
  FilePondPluginFileValidateType,
  FilePondPluginFileValidateSize
);

const props = defineProps({
  acceptedFileTypes: {
    type: Array,
    default: () => [],
  },
  maxFileSize: {
    type: String,
    default: "20MB",
  },
  allowMultiple: {
    type: Boolean,
    default: false,
  },
  maxFiles: {
    type: Number,
    default: 1,
  },
  maxParallelUploads: {
    type: Number,
    default: 2,
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  skipOptimize: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "complete", "progress", "addfile", "removefile"]);

const sanctumFetch = useSanctumClient();

const pond = ref(null);
const uploadedTempIds = ref([]);
const doneCount = ref(0);
// Guards programmatic clear()/removeFiles() so the resulting removefile/
// processfiles events don't cascade back into emits.
const isClearing = ref(false);

const pondFileCount = () => pond.value?.getFiles?.()?.length ?? 0;

const emitProgress = () => {
  emit("progress", {
    done: doneCount.value,
    total: Math.max(pondFileCount(), doneCount.value),
  });
};

// A file was queued — report the new batch total so the parent can show X/Y.
const handleAddFile = (error, file) => {
  if (isClearing.value || error) {
    return;
  }
  emit("addfile", file);
  emitProgress();
};

// Handle FilePond process (per file finished uploading to temp storage)
const handleProcessFile = (error, file) => {
  if (isClearing.value) {
    return;
  }
  if (!error && file.serverId) {
    if (props.allowMultiple) {
      uploadedTempIds.value = [...uploadedTempIds.value, file.serverId];
      emit("update:modelValue", [...uploadedTempIds.value]);
    } else {
      uploadedTempIds.value = [file.serverId];
      emit("update:modelValue", [file.serverId]);
    }
    doneCount.value += 1;
    emitProgress();
  }
};

// The whole processing queue is now empty: signal the parent to attach the
// batch in one shot. This is the only correct moment to attach — waiting per
// file (debounced) raced with still-uploading files.
const handleProcessFiles = () => {
  if (isClearing.value) {
    return;
  }
  if (props.allowMultiple && uploadedTempIds.value.length) {
    emit("complete", [...uploadedTempIds.value]);
  }
};

// Handle FilePond remove
const handleRemoveFile = (error, file) => {
  if (isClearing.value) {
    return;
  }
  emit("removefile", file);
  if (props.allowMultiple) {
    uploadedTempIds.value = uploadedTempIds.value.filter((id) => id !== file.serverId);
    doneCount.value = uploadedTempIds.value.length;
    emit("update:modelValue", [...uploadedTempIds.value]);
  } else {
    uploadedTempIds.value = [];
    doneCount.value = 0;
    emit("update:modelValue", []);
  }
};

// Reset the uploader after a batch has been attached. Safe to call only once
// FilePond has finished processing (no in-flight uploads to abort).
const clear = () => {
  isClearing.value = true;
  uploadedTempIds.value = [];
  doneCount.value = 0;
  pond.value?.removeFiles?.();
  emit("update:modelValue", []);
  emit("progress", { done: 0, total: 0 });
  nextTick(() => {
    isClearing.value = false;
  });
};

// Custom server configuration
const server = {
  process: async (_fieldName, file, _metadata, load, error, progress, abort) => {
    const formData = new FormData();
    formData.append("file", file);
    if (props.skipOptimize) {
      formData.append("skip_optimize", "1");
    }

    const controller = new AbortController();

    try {
      const response = await sanctumFetch("/api/tmp-upload", {
        method: "POST",
        body: formData,
        signal: controller.signal,
        onUploadProgress: (progressEvent) => {
          progress(progressEvent.lengthComputable, progressEvent.loaded, progressEvent.total);
        },
      });

      if (response.folder) {
        load(response.folder);
      } else {
        error("Upload failed");
      }
    } catch (err) {
      if (err.name === "AbortError") {
        abort();
      } else {
        error(err.message || "Upload failed");
      }
    }

    return {
      abort: () => {
        controller.abort();
      },
    };
  },

  revert: async (uniqueFileId, load, error) => {
    if (uniqueFileId && uniqueFileId.startsWith("tmp-")) {
      try {
        await sanctumFetch("/api/tmp-upload", {
          method: "DELETE",
          body: uniqueFileId,
        });
        load();
      } catch (err) {
        error(err.message || "Revert failed");
      }
    } else {
      load();
    }
  },
};

// Add files programmatically (used for paste-from-clipboard and drag-onto-grid).
// Routes through the same FilePond queue as a manual drop, so the batch attach
// flow (processfiles → complete) is identical.
const addFiles = (fileList) => {
  const images = [...(fileList || [])].filter(
    (file) => file && file.type && file.type.startsWith("image/")
  );
  if (!images.length || !pond.value) {
    return;
  }
  pond.value.addFiles(images);
};

// Expose methods to parent
defineExpose({
  pond,
  clear,
  addFiles,
});
</script>
