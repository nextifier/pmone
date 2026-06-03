<template>
  <div class="space-y-2">
    <div
      v-if="canDelete && selectedCount > 0"
      class="bg-muted/50 flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
    >
      <div class="flex items-center gap-x-2">
        <Checkbox
          :model-value="selectAllState"
          aria-label="Select all images"
          @update:model-value="toggleSelectAll"
        />
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          {{ selectedCount }} selected
        </span>
      </div>
      <Button type="button" variant="destructive" size="sm" @click="openDelete">
        <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
        Delete ({{ selectedCount }})
      </Button>
    </div>

    <div ref="gridRef" class="grid" :class="columnsClass">
      <div
        v-for="(item, index) in localItems"
        :key="item.id"
        class="bg-muted relative aspect-square overflow-hidden rounded-lg border transition-colors"
        :class="{ 'ring-primary ring-2': isSelected(item.id) }"
      >
        <Lightbox v-if="lightbox" :items="localItems" :alt="alt" :thumbnail-key="thumbnailKey">
          <template #trigger="{ openAt }">
            <button
              type="button"
              class="block size-full cursor-zoom-in"
              @click="openAt(index)"
            >
              <img
                :src="item[thumbnailKey] || item.md || item.url"
                :alt="item.name || alt"
                loading="lazy"
                decoding="async"
                draggable="false"
                class="size-full object-cover"
              />
            </button>
          </template>
        </Lightbox>
        <img
          v-else
          :src="item[thumbnailKey] || item.md || item.url"
          :alt="item.name || alt"
          loading="lazy"
          decoding="async"
          draggable="false"
          class="size-full object-cover"
        />

        <div v-if="canDelete" class="absolute top-1.5 left-1.5 z-10" @click.stop>
          <Checkbox
            :model-value="isSelected(item.id)"
            :aria-label="`Select ${item.name || 'image'}`"
            class="size-5 rounded-md border-white/70 bg-black/35 shadow-sm backdrop-blur-sm data-[state=checked]:border-primary data-[state=checked]:bg-primary"
            @update:model-value="(value) => toggle(item.id, value)"
          />
        </div>

        <div
          v-if="canReorder"
          class="drag-handle absolute top-1.5 right-1.5 z-10 flex size-7 cursor-grab touch-none items-center justify-center rounded-full bg-black/35 text-white shadow-sm backdrop-blur-sm active:cursor-grabbing"
          aria-label="Drag to reorder"
        >
          <Icon name="lucide:grip-vertical" class="size-4 shrink-0" />
        </div>
      </div>
    </div>

    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tighter">Delete images?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            {{ selectedCount }} selected image{{ selectedCount > 1 ? "s" : "" }} will be permanently
            deleted. This action can't be undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              :disabled="deletePending"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </Button>
            <Button type="button" variant="destructive" :disabled="deletePending" @click="confirmDelete">
              <Spinner v-if="deletePending" class="size-4" />
              <span>Delete</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { toast } from "vue-sonner";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Spinner } from "@/components/ui/spinner";
import { Lightbox } from "@/components/ui/lightbox";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";

const items = defineModel("items", { type: Array, default: () => [] });

const props = defineProps({
  canDelete: { type: Boolean, default: true },
  canReorder: { type: Boolean, default: true },
  columnsClass: {
    type: String,
    default: "grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5",
  },
  thumbnailKey: { type: String, default: "sm" },
  lightbox: { type: Boolean, default: true },
  alt: { type: String, default: "Gallery" },
});

const emit = defineEmits(["changed"]);

const client = useSanctumClient();
const gridRef = ref(null);

/**
 * Local writable working copy. SortableJS mutates its target array in place,
 * but the `items` model proxies a readonly prop, so splicing it directly is
 * blocked by Vue. We sort/delete on this copy and commit the result back to
 * the model via reassignment.
 */
const localItems = ref([]);

watch(
  items,
  (val) => {
    const incoming = Array.isArray(val) ? val : [];
    const sameOrder =
      incoming.length === localItems.value.length &&
      incoming.every((media, index) => media.id === localItems.value[index]?.id);
    if (!sameOrder) {
      localItems.value = incoming.map((media) => media);
    }
  },
  { immediate: true },
);

function commit() {
  items.value = localItems.value.map((media) => media);
}

const selected = ref(new Set());
const selectedCount = computed(() => selected.value.size);
const isSelected = (id) => selected.value.has(id);

function toggle(id, value) {
  const next = new Set(selected.value);
  if (value) {
    next.add(id);
  } else {
    next.delete(id);
  }
  selected.value = next;
}

const selectAllState = computed(() => {
  const count = selected.value.size;
  if (count === 0) {
    return false;
  }
  if (count >= localItems.value.length) {
    return true;
  }
  return "indeterminate";
});

function toggleSelectAll(value) {
  selected.value = value ? new Set(localItems.value.map((media) => media.id)) : new Set();
}

watch(localItems, (list) => {
  const present = new Set(list.map((media) => media.id));
  const filtered = new Set([...selected.value].filter((id) => present.has(id)));
  if (filtered.size !== selected.value.size) {
    selected.value = filtered;
  }
});

const reordering = ref(false);
let dragStartKey = null;

useSortableList(gridRef, localItems, {
  enabled: computed(() => props.canReorder && !reordering.value),
  onReorder: autoSaveOrder,
  sortableOptions: {
    ghostClass: "opacity-40",
    onStart: () => {
      dragStartKey = localItems.value.map((media) => media.id).join(",");
    },
  },
});

async function autoSaveOrder() {
  if (!props.canReorder || localItems.value.length < 2) {
    return;
  }
  const order = localItems.value.map((media) => media.id);
  if (order.join(",") === dragStartKey) {
    return;
  }
  reordering.value = true;
  try {
    await client("/api/media/reorder", {
      method: "POST",
      body: { media_ids: order },
    });
    commit();
    toast.success("Order saved");
    emit("changed");
  } catch (e) {
    if (dragStartKey) {
      const byId = new Map(localItems.value.map((media) => [media.id, media]));
      localItems.value = dragStartKey
        .split(",")
        .map((id) => byId.get(Number(id)))
        .filter(Boolean);
    }
    toast.error(e?.data?.message || "Failed to save order");
    emit("changed");
  } finally {
    reordering.value = false;
  }
}

const deleteDialogOpen = ref(false);
const deletePending = ref(false);

function openDelete() {
  if (selectedCount.value > 0) {
    deleteDialogOpen.value = true;
  }
}

async function confirmDelete() {
  const ids = [...selected.value];
  if (!ids.length) {
    return;
  }
  deletePending.value = true;
  try {
    const response = await client("/api/media/bulk-delete", {
      method: "DELETE",
      body: { media_ids: ids },
    });
    const deletedIds = Array.isArray(response?.deleted_media)
      ? response.deleted_media.map((media) => media.id)
      : ids;
    const removed = new Set(deletedIds);
    localItems.value = localItems.value.filter((media) => !removed.has(media.id));
    commit();
    selected.value = new Set();
    deleteDialogOpen.value = false;
    toast.success(`${removed.size} image${removed.size > 1 ? "s" : ""} deleted`);
    emit("changed");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete images");
  } finally {
    deletePending.value = false;
  }
}
</script>
