<script setup>
import { ref, toRef } from "vue";
import { useSortableList } from "@/composables/useSortableList";

defineOptions({ name: "ShaderLayerItem" });

const props = defineProps({
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

const emit = defineEmits(["select", "remove", "move", "duplicate", "add-child"]);

const { byName } = useShaderRegistry();

const CATEGORY_ICONS = {
  Textures: "hugeicons:paint-board",
  Shapes: "hugeicons:shapes",
  "Shape Effects": "hugeicons:diamond-02",
  Blurs: "hugeicons:blur",
  Distortions: "hugeicons:flow-square",
  Stylize: "hugeicons:sparkles",
  Adjustments: "hugeicons:color-picker",
  Interactive: "hugeicons:cursor-magic-selection-03",
  Utilities: "hugeicons:folder-01",
};

const iconFor = (type) => CATEGORY_ICONS[byName[type]?.category] ?? "hugeicons:layers-01";

// Each nesting level is independently drag-sortable. SortableJS reorders the
// `nodes` array in place (same reactive array the tree holds), so the live
// preview and exported code update automatically.
const listRef = ref(null);
useSortableList(listRef, toRef(props, "nodes"));
</script>

<template>
  <div ref="listRef" class="flex flex-col gap-y-0.5">
    <div v-for="node in nodes" :key="node.id">
      <div
        class="group flex cursor-pointer items-center gap-x-2 rounded-lg py-1.5 pr-1 pl-1.5 transition"
        :class="
          node.id === activeId
            ? 'bg-muted ring-border ring-1'
            : 'hover:bg-muted/60 ring-1 ring-transparent'
        "
        :style="{ marginLeft: `${depth * 12}px` }"
        @click="emit('select', node.id)"
      >
        <span
          class="drag-handle bg-background/60 text-muted-foreground flex size-6 shrink-0 cursor-grab items-center justify-center rounded-md active:cursor-grabbing"
        >
          <Icon :name="iconFor(node.type)" class="size-3.5" />
        </span>
        <span class="flex-1 truncate text-sm tracking-tight">{{ node.type }}</span>
        <div class="flex items-center opacity-0 transition group-hover:opacity-100">
          <button
            class="hover:bg-background text-muted-foreground hover:text-foreground rounded p-1"
            title="Add inside"
            @click.stop="emit('add-child', node.id)"
          >
            <Icon name="hugeicons:add-01" class="size-3.5" />
          </button>
          <button
            class="hover:bg-background text-muted-foreground hover:text-foreground rounded p-1"
            title="Duplicate"
            @click.stop="emit('duplicate', node.id)"
          >
            <Icon name="hugeicons:copy-01" class="size-3.5" />
          </button>
          <button
            class="hover:bg-background text-muted-foreground hover:text-destructive rounded p-1"
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
        @duplicate="emit('duplicate', $event)"
        @add-child="emit('add-child', $event)"
      />
    </div>
  </div>
</template>
