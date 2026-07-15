<template>
  <div class="w-full space-y-2">
    <!-- Existing / uploaded file card -->
    <AttachmentLink
      v-if="showCard"
      :file="fileMeta"
      fallback-name="Uploaded file"
      :disable-open="!fileMeta.url"
    >
      <template #description>
        <span v-if="fileMeta.size">{{ formatFileSize(fileMeta.size) }}</span>
        <span v-if="fileMeta.size && fileMeta.uploaded_at"> · </span>
        <span v-if="fileMeta.uploaded_at">{{ $dayjs(fileMeta.uploaded_at).format("MMM D, YYYY") }}</span>
      </template>
      <template #actions>
        <AttachmentAction v-tippy="'Replace'" type="button" aria-label="Replace" @click="handleReplace">
          <Icon name="hugeicons:exchange-01" class="size-4" />
        </AttachmentAction>
        <AttachmentAction v-tippy="'Remove'" type="button" aria-label="Remove" @click="handleDelete">
          <Icon name="hugeicons:delete-02" class="size-4" />
        </AttachmentAction>
      </template>
    </AttachmentLink>

    <!-- Uploader (empty or replacing) -->
    <template v-else>
      <InputFile
        ref="inputFileRef"
        v-model="localFiles"
        :accepted-file-types="acceptedFileTypes"
        :allow-multiple="false"
        :max-files="1"
        :skip-optimize="skipOptimize"
      />
      <button
        v-if="showUndo"
        type="button"
        @click="handleUndo"
        class="text-primary hover:text-primary/80 flex items-center gap-1.5 text-sm font-medium tracking-tight transition"
      >
        <Icon name="hugeicons:undo-02" class="size-4" />
        Undo
      </button>
    </template>
  </div>
</template>

<script setup>
import { AttachmentAction } from "@/components/ui/attachment";
import { formatFileSize } from "@/utils/attachments";

const client = useSanctumClient();

const inputFileRef = ref(null);

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  // Raw file metadata: { url, name, size, mime_type, extension, uploaded_at }
  initialFile: {
    type: Object,
    default: null,
  },
  deleteFlag: {
    type: Boolean,
    default: false,
  },
  acceptedFileTypes: {
    type: Array,
    default: () => [],
  },
  skipOptimize: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "update:deleteFlag", "delete", "undo"]);

const isReplacing = ref(false);

const localFiles = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const hasTempUpload = computed(() => {
  const value = props.modelValue?.[0];
  return typeof value === "string" && value.startsWith("tmp-");
});

// A fresh upload wins; reset the deleteFlag so it isn't stuck.
watch(hasTempUpload, (hasUpload) => {
  if (hasUpload) {
    isReplacing.value = false;
    if (props.deleteFlag) {
      emit("update:deleteFlag", false);
    }
  }
});

// Show the card for an existing (not-yet-deleted, not-replacing) file, OR once a
// temp upload lands (so the freshly picked file reads back as a card).
const showCard = computed(() => {
  if (hasTempUpload.value) {
    return true;
  }
  if (isReplacing.value || props.deleteFlag) {
    return false;
  }
  return !!props.initialFile;
});

const showUndo = computed(() => props.deleteFlag && props.initialFile && !isReplacing.value);

// When a temp file is active we only know its name via metadata; fall back to a
// generic label. The initialFile carries full metadata for existing media.
const fileMeta = computed(() => {
  if (hasTempUpload.value) {
    return { url: null, name: null, size: null, mime_type: null, uploaded_at: null };
  }
  return props.initialFile || {};
});

function handleReplace() {
  isReplacing.value = true;
}

async function handleDelete() {
  if (hasTempUpload.value) {
    const folder = props.modelValue[0];
    try {
      await client("/api/tmp-upload", { method: "DELETE", body: folder });
    } catch (err) {
      console.warn("Failed to delete temp file:", err);
    }
  }

  isReplacing.value = false;
  emit("update:deleteFlag", true);
  emit("update:modelValue", []);
  emit("delete");
}

function handleUndo() {
  emit("update:deleteFlag", false);
  emit("update:modelValue", []);
  emit("undo");
}
</script>
