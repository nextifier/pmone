<template>
  <div
    class="border-input focus-within:border-ring focus-within:ring-ring min-h-[38px] cursor-text rounded-md border p-1 text-sm transition-[color,box-shadow] outline-none focus-within:ring-[1px]"
  >
    <div class="flex flex-wrap gap-1">
      <span
        v-for="(tag, index) in localTags"
        :key="index"
        class="bg-background text-secondary-foreground hover:bg-background relative inline-flex h-9 cursor-default items-center rounded-md border pr-9 pl-3 text-xs font-medium transition-all"
      >
        {{ tag }}
        <button
          type="button"
          @click="removeTag(index)"
          class="text-muted-foreground/80 hover:text-foreground absolute -inset-y-px -end-px flex size-9 items-center justify-center rounded-e-md border border-transparent p-0"
        >
          <Icon name="lucide:x" class="size-4" />
        </button>
      </span>

      <input
        v-model="inputValue"
        @keydown.enter.prevent="addTag"
        @keydown.backspace="onBackspace"
        :placeholder="localTags.length === 0 ? placeholder : ''"
        class="placeholder:text-muted-foreground/70 flex-1 bg-transparent px-2 py-1 tracking-tight outline-hidden"
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
});

const emit = defineEmits(["update:modelValue"]);

const inputValue = ref("");

const localTags = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

function addTag() {
  const tag = inputValue.value.trim();
  if (tag && !localTags.value.includes(tag)) {
    localTags.value = [...localTags.value, tag];
    inputValue.value = "";
  }
}

function removeTag(index) {
  localTags.value = localTags.value.filter((_, i) => i !== index);
}

function onBackspace() {
  if (inputValue.value === "" && localTags.value.length > 0) {
    removeTag(localTags.value.length - 1);
  }
}
</script>
