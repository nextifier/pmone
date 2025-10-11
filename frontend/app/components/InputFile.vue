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
      @processfile="handleProcessFile"
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
    default: "10MB",
  },
  allowMultiple: {
    type: Boolean,
    default: false,
  },
  maxFiles: {
    type: Number,
    default: 1,
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue"]);

const sanctumFetch = useSanctumClient();

const pond = ref(null);
const uploadedTempId = ref(null);

// Handle FilePond process
const handleProcessFile = (error, file) => {
  if (!error && file.serverId) {
    uploadedTempId.value = file.serverId;
    emit("update:modelValue", [file.serverId]);
  }
};

// Handle FilePond remove
const handleRemoveFile = () => {
  uploadedTempId.value = null;
  emit("update:modelValue", []);
};

// Custom server configuration
const server = {
  process: async (_fieldName, file, _metadata, load, error, progress, abort) => {
    const formData = new FormData();
    formData.append("file", file);

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

// Expose methods to parent
defineExpose({
  pond,
});
</script>
