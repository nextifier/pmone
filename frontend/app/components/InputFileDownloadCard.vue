<template>
  <div class="w-full space-y-2">
    <!-- Existing / uploaded file card -->
    <div
      v-if="showCard"
      class="border-border flex items-center gap-3 rounded-lg border p-3"
    >
      <div class="bg-muted flex size-11 shrink-0 items-center justify-center rounded-lg">
        <Icon :name="fileIcon" class="text-muted-foreground size-5.5" />
      </div>

      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium tracking-tight">{{ fileName }}</p>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          <span v-if="fileMeta.size">{{ formatSize(fileMeta.size) }}</span>
          <span v-if="fileMeta.size && fileMeta.uploaded_at"> · </span>
          <span v-if="fileMeta.uploaded_at">{{ $dayjs(fileMeta.uploaded_at).format("MMM D, YYYY") }}</span>
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-1.5">
        <Tippy v-if="fileMeta.url">
          <a
            :href="fileMeta.url"
            target="_blank"
            rel="noopener"
            class="text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 items-center justify-center rounded-md transition"
          >
            <Icon name="hugeicons:download-01" class="size-4" />
          </a>
          <template #content>
            <span class="text-xs tracking-tight">Download</span>
          </template>
        </Tippy>
        <Tippy>
          <button
            type="button"
            @click="handleReplace"
            class="text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 items-center justify-center rounded-md transition"
          >
            <Icon name="hugeicons:exchange-01" class="size-4" />
          </button>
          <template #content>
            <span class="text-xs tracking-tight">Replace</span>
          </template>
        </Tippy>
        <Tippy>
          <button
            type="button"
            @click="handleDelete"
            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive flex size-8 items-center justify-center rounded-md transition"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </button>
          <template #content>
            <span class="text-xs tracking-tight">Remove</span>
          </template>
        </Tippy>
      </div>
    </div>

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

const fileName = computed(() => fileMeta.value.name || "Uploaded file");

const fileIcon = computed(() => {
  const mime = fileMeta.value.mime_type || "";
  const ext = (fileMeta.value.extension || "").toLowerCase();
  if (mime.startsWith("image/")) return "hugeicons:image-01";
  if (mime === "application/pdf" || ext === "pdf") return "hugeicons:pdf-01";
  if (mime.includes("zip") || ext === "zip") return "hugeicons:zip-01";
  if (ext === "ai" || mime.includes("postscript") || mime.includes("illustrator"))
    return "hugeicons:ai-01";
  return "hugeicons:file-01";
});

function formatSize(bytes) {
  if (!bytes || bytes <= 0) return "";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

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
