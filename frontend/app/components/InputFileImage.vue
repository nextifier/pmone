<template>
  <div class="space-y-2">
    <InputFile
      ref="inputFileRef"
      v-if="showInput"
      v-model="localFiles"
      :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
      :allow-multiple="false"
      :max-files="1"
      class="mt-3"
    />

    <div v-else :class="containerClass">
      <img
        :src="imageUrl"
        alt=""
        class="border-border size-full rounded-lg border object-cover"
        loading="lazy"
      />

      <button
        type="button"
        @click="handleDelete"
        class="absolute top-1.5 right-1.5 flex size-8 items-center justify-center rounded-full bg-black/40 text-white shadow-sm ring ring-white/20 backdrop-blur-sm transition hover:bg-black"
      >
        <Icon name="hugeicons:delete-01" class="size-4" />
      </button>
    </div>

    <button
      v-if="showUndo"
      type="button"
      @click="handleUndo"
      class="text-primary hover:text-primary/80 mx-auto flex items-center gap-1.5 text-sm font-medium tracking-tight transition"
    >
      <Icon name="hugeicons:undo-02" class="size-4" />
      Undo Delete
    </button>
  </div>
</template>

<script setup>
const config = useRuntimeConfig();
const client = useSanctumClient();

const inputFileRef = ref(null);

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  initialImage: {
    type: [Object, String],
    default: null,
  },
  deleteFlag: {
    type: Boolean,
    default: false,
  },
  containerClass: {
    type: String,
    default: "relative isolate",
  },
});

const emit = defineEmits(["update:modelValue", "update:deleteFlag", "delete", "undo"]);

// Getter to access pond from parent
const getPond = () => inputFileRef.value?.pond;

// Expose the pond getter so parent can access it
defineExpose({
  get pond() {
    return getPond();
  },
});

const localFiles = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

// Check if there's a temp file uploaded
const hasTempUpload = computed(() => {
  const value = props.modelValue?.[0];
  return typeof value === "string" && value.startsWith("tmp-");
});

// When a new temp upload completes, reset deleteFlag
watch(hasTempUpload, (hasUpload) => {
  if (hasUpload && props.deleteFlag) {
    emit("update:deleteFlag", false);
  }
});

// Show input only if: no temp upload AND (no initial image OR deleteFlag is true)
const showInput = computed(() => {
  // Priority: If there's a temp upload, always show image preview
  if (hasTempUpload.value) return false;
  // Then check deleteFlag or no initial image
  if (props.deleteFlag) return true;
  return !props.initialImage;
});

// Show undo only if there's an initial image and it was deleted
const showUndo = computed(() => props.deleteFlag && props.initialImage);

const imageUrl = computed(() => {
  // Priority 1: Check for temp uploaded file
  if (hasTempUpload.value) {
    const folder = props.modelValue[0];
    const baseUrl = config.public.apiUrl || "";
    return `${baseUrl}/api/tmp-upload/load?folder=${folder}`;
  }

  // Priority 2: Use initial image if available and not deleted
  if (props.initialImage && !props.deleteFlag) {
    // Handle string URL (Ghost imports)
    if (typeof props.initialImage === "string") {
      return props.initialImage;
    }
    // Handle object with lg/url properties (Media Library)
    return props.initialImage?.lg || props.initialImage?.url;
  }

  return null;
});

async function handleDelete() {
  // If there's a temp upload, delete it from server
  if (hasTempUpload.value) {
    const folder = props.modelValue[0];
    try {
      await client("/api/tmp-upload", {
        method: "DELETE",
        body: folder,
      });
    } catch (err) {
      console.warn("Failed to delete temp file:", err);
    }
  }

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
