<template>
  <NodeViewWrapper class="image-node-view" :class="{ 'is-selected': selected }">
    <img
      :src="node.attrs.src"
      :alt="node.attrs.alt"
      :title="node.attrs.title"
      class="post-content-image"
      @click="selectNode"
    />
    <div class="caption-wrapper">
      <input
        ref="captionInput"
        type="text"
        :value="node.attrs['data-caption'] || ''"
        placeholder="Add a caption..."
        maxlength="500"
        class="caption-input"
        @input="updateCaption"
        @keydown.enter.prevent="editor.commands.focus()"
      />
    </div>
  </NodeViewWrapper>
</template>

<script setup>
import { NodeViewWrapper } from "@tiptap/vue-3";

const props = defineProps({
  node: { type: Object, required: true },
  updateAttributes: { type: Function, required: true },
  selected: { type: Boolean, default: false },
  editor: { type: Object, required: true },
  getPos: { type: Function, required: true },
});

const selectNode = () => {
  const pos = props.getPos();
  props.editor.commands.setNodeSelection(pos);
};

const updateCaption = (event) => {
  const value = event.target.value;
  props.updateAttributes({ "data-caption": value || null });
};
</script>

<style scoped>
@reference "../../assets/css/main.css";

.image-node-view {
  @apply my-4;
}

.image-node-view.is-selected .post-content-image {
  @apply ring-primary/50 ring-2;
}

.post-content-image {
  @apply h-auto max-w-full cursor-pointer rounded-lg;
}

.caption-wrapper {
  @apply mt-1.5;
}

.caption-input {
  @apply text-muted-foreground w-full border-none bg-transparent text-center text-sm italic outline-none;
  @apply placeholder:text-muted-foreground/50;
  @apply focus:text-foreground;
}
</style>
