<template>
  <TipTapEditorImpl
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :placeholder="placeholder"
    :post-id="postId"
    :model-type="modelType"
    :collection="collection"
    :sticky="sticky"
    :min-height="minHeight"
  />
</template>

<script setup>
defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  placeholder: {
    type: String,
    default: "Start writing your post...",
  },
  postId: {
    type: Number,
    default: null,
  },
  modelType: {
    type: String,
    default: "App\\Models\\Post",
  },
  collection: {
    type: String,
    default: "content_images",
  },
  sticky: {
    type: Boolean,
    default: true,
  },
  minHeight: {
    type: String,
    default: "350px",
  },
});

defineEmits(["update:modelValue"]);
</script>
