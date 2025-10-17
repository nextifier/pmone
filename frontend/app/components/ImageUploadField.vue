<template>
  <div class="space-y-2">
    <Label>{{ label }}</Label>

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

      <button type="button" @click="handleDelete" :class="DELETE_BUTTON_CLASS">
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

    <InputErrorMessage :errors="errors" />
  </div>
</template>

<script setup>
import { Label } from "@/components/ui/label";

const DELETE_BUTTON_CLASS =
  "absolute top-1.5 right-1.5 flex size-8 items-center justify-center rounded-full bg-black/40 text-white shadow-sm ring ring-white/20 backdrop-blur-sm transition hover:bg-black";

// Ref to InputFile component
const inputFileRef = ref(null);

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  initialImage: {
    type: Object,
    default: null,
  },
  deleteFlag: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Array,
    default: null,
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

const showInput = computed(() => !props.initialImage || props.deleteFlag);
const showUndo = computed(() => props.deleteFlag && props.initialImage);
const imageUrl = computed(() => props.initialImage?.sm);

function handleDelete() {
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
