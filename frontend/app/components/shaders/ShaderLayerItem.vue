<script setup>
defineOptions({ name: "ShaderLayerItem" });

defineProps({
  nodes: {
    type: Array,
    default: () => [],
  },
  activeId: {
    type: [String, null],
    default: null,
  },
  depth: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(["select", "remove", "move"]);
</script>

<template>
  <template v-for="node in nodes" :key="node.id">
    <div
      class="group flex cursor-pointer items-center gap-x-1.5 rounded-md py-1.5 pr-1 transition"
      :class="node.id === activeId ? 'bg-muted' : 'hover:bg-muted/60'"
      :style="{ paddingLeft: `${depth * 14 + 8}px` }"
      @click="emit('select', node.id)"
    >
      <Icon name="hugeicons:layers-01" class="text-muted-foreground size-3.5 shrink-0" />
      <span class="flex-1 truncate text-sm tracking-tight">{{ node.type }}</span>
      <div class="flex items-center opacity-0 transition group-hover:opacity-100">
        <button
          class="hover:bg-background text-muted-foreground hover:text-foreground rounded p-0.5"
          title="Move up"
          @click.stop="emit('move', node.id, -1)"
        >
          <Icon name="hugeicons:arrow-up-01" class="size-3.5" />
        </button>
        <button
          class="hover:bg-background text-muted-foreground hover:text-foreground rounded p-0.5"
          title="Move down"
          @click.stop="emit('move', node.id, 1)"
        >
          <Icon name="hugeicons:arrow-down-01" class="size-3.5" />
        </button>
        <button
          class="hover:bg-background text-muted-foreground hover:text-destructive rounded p-0.5"
          title="Remove"
          @click.stop="emit('remove', node.id)"
        >
          <Icon name="hugeicons:cancel-01" class="size-3.5" />
        </button>
      </div>
    </div>
    <ShaderLayerItem
      v-if="node.children?.length"
      :nodes="node.children"
      :active-id="activeId"
      :depth="depth + 1"
      @select="emit('select', $event)"
      @remove="emit('remove', $event)"
      @move="(id, dir) => emit('move', id, dir)"
    />
  </template>
</template>
