<template>
  <div
    class="border-input focus-within:border-ring focus-within:ring-ring min-h-[38px] cursor-text rounded-md border p-1 text-sm transition-[color,box-shadow] outline-none focus-within:ring-[1px]"
  >
    <div class="flex flex-wrap gap-1">
      <span
        v-for="(tag, index) in localTags"
        :key="tag"
        class="bg-background text-secondary-foreground relative inline-flex h-9 cursor-default items-center rounded-md border pr-9 pl-3 text-xs font-medium transition-all"
        :class="{
          'ring-2 ring-destructive/50 bg-destructive/10': pendingDeleteIndex === index,
        }"
      >
        {{ tag }}
        <button
          type="button"
          @click="removeTag(index)"
          class="text-muted-foreground/80 hover:text-destructive absolute -inset-y-px -end-px flex size-9 items-center justify-center rounded-e-md border border-transparent p-0 transition-colors"
        >
          <Icon name="lucide:x" class="size-4" />
        </button>
      </span>

      <input
        ref="inputRef"
        v-model="inputValue"
        @keydown.enter.prevent="addTag"
        @keydown.backspace="onBackspace"
        @keydown.tab="onTab"
        @blur="resetPendingDelete"
        :placeholder="localTags.length === 0 ? placeholder : ''"
        class="placeholder:text-muted-foreground/70 min-w-[80px] flex-1 bg-transparent px-2 py-1 tracking-tight outline-hidden"
        :class="{
          '-ml-1': localTags.length !== 0,
        }"
      />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: "Add tags...",
  },
  maxTags: {
    type: Number,
    default: 10,
  },
});

const emit = defineEmits(["update:modelValue"]);

const inputRef = ref(null);
const inputValue = ref("");
const pendingDeleteIndex = ref(null);
let pendingDeleteTimeout = null;

const localTags = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

function addTag() {
  const tag = inputValue.value.trim().toLowerCase();
  if (!tag) return;

  // Check max tags limit
  if (localTags.value.length >= props.maxTags) {
    return;
  }

  // Check for duplicates (case-insensitive)
  if (!localTags.value.some((t) => t.toLowerCase() === tag)) {
    localTags.value = [...localTags.value, tag];
  }
  inputValue.value = "";
  resetPendingDelete();
}

function onTab(event) {
  // Allow tab to add tag if there's input
  if (inputValue.value.trim()) {
    event.preventDefault();
    addTag();
  }
}

function removeTag(index) {
  localTags.value = localTags.value.filter((_, i) => i !== index);
  resetPendingDelete();
}

function onBackspace() {
  if (inputValue.value !== "" || localTags.value.length === 0) {
    resetPendingDelete();
    return;
  }

  const lastIndex = localTags.value.length - 1;

  // If already pending delete for last tag, delete it
  if (pendingDeleteIndex.value === lastIndex) {
    removeTag(lastIndex);
    return;
  }

  // Mark last tag for pending delete (visual feedback)
  pendingDeleteIndex.value = lastIndex;

  // Auto-reset after 1.5 seconds
  clearTimeout(pendingDeleteTimeout);
  pendingDeleteTimeout = setTimeout(() => {
    resetPendingDelete();
  }, 1500);
}

function resetPendingDelete() {
  pendingDeleteIndex.value = null;
  clearTimeout(pendingDeleteTimeout);
}

onBeforeUnmount(() => {
  clearTimeout(pendingDeleteTimeout);
});
</script>
