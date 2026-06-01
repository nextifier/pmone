<script setup>
defineOptions({ name: "ShaderTree", inheritAttrs: false });

const props = defineProps({
  nodes: {
    type: Array,
    default: () => [],
  },
  // The `shaders/vue` namespace, loaded client-side by <ShaderCanvas> and
  // threaded down so the whole tree shares one dynamic import (no SSR import).
  lib: {
    type: Object,
    default: null,
  },
});

function resolve(type) {
  return props.lib?.[type] ?? null;
}
</script>

<template>
  <template v-for="(node, index) in nodes" :key="node.id ?? `${node.type}-${index}`">
    <component
      :is="resolve(node.type)"
      v-if="resolve(node.type)"
      :id="node.id"
      v-bind="node.props"
    >
      <ShaderTree v-if="node.children?.length" :nodes="node.children" :lib="lib" />
    </component>
  </template>
</template>
