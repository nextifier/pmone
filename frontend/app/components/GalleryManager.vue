<template>
  <div class="space-y-2">
    <div
      v-if="canReorder || canDelete"
      class="flex items-center justify-between gap-2"
      :class="{ 'min-h-7': !compactToolbar }"
    >
      <div class="flex items-center gap-2">
        <template v-if="canDelete && selectedCount > 0">
          <div class="bg-muted/50 flex items-center gap-x-2 rounded-lg border px-2.5 py-1.5">
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
        </template>
        <slot name="toolbar-actions" />
      </div>

      <Transition
        enter-from-class="opacity-0 translate-y-0.5"
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-to-class="opacity-100 translate-y-0"
        leave-from-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="opacity-0 translate-y-0.5"
      >
        <div
          v-if="canReorder && saveStatus !== 'idle'"
          role="status"
          aria-live="polite"
          class="text-muted-foreground inline-flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
        >
          <span class="relative inline-flex size-4 shrink-0 items-center justify-center">
            <Spinner v-if="saveStatus === 'saving'" class="size-4" />
            <Icon
              v-else-if="saveStatus === 'saved'"
              name="hugeicons:checkmark-circle-02"
              class="text-success-foreground size-4"
            />
            <Icon v-else name="hugeicons:alert-circle" class="text-destructive size-4" />
          </span>
          <span>{{ statusLabel }}</span>
        </div>
      </Transition>
    </div>

    <slot v-if="!localItems.length" name="empty" />

    <Lightbox v-else :items="localItems" :alt="alt" :thumbnail-key="thumbnailKey">
      <template #trigger="{ openAt }">
        <div ref="gridRef" class="grid" :class="columnsClass">
          <div
            v-for="(item, index) in localItems"
            :key="item[idKey]"
            class="gallery-tile bg-muted relative overflow-hidden rounded-lg border"
            :class="[
              tileAspectClass,
              {
                'ring-primary ring-2': isSelected(item[idKey]),
                'is-leaving': leavingIds.has(item[idKey]),
              },
            ]"
          >
            <button
              type="button"
              class="block size-full"
              :class="lightbox ? 'cursor-zoom-in' : 'cursor-default'"
              @click="lightbox && openAt(index)"
            >
              <img
                :src="item[thumbnailKey] || item.md || item.url"
                :alt="item.caption || item.name || alt"
                loading="lazy"
                decoding="async"
                draggable="false"
                class="size-full object-cover"
              />
            </button>

            <div
              v-if="canDelete || canReorder"
              class="pointer-events-none absolute inset-x-0 top-0 h-11 bg-linear-to-b from-black/30 to-transparent"
            />

            <div v-if="canDelete" class="absolute top-1.5 left-1.5 z-10" @click.stop>
              <Checkbox
                :model-value="isSelected(item[idKey])"
                :aria-label="`Select ${item.name || 'image'}`"
                class="size-5 rounded-md border-white/80 bg-black/20 shadow-sm backdrop-blur-sm data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                @update:model-value="(value) => toggle(item[idKey], value)"
              />
            </div>

            <button
              v-if="canReorder"
              type="button"
              class="drag-handle absolute top-1.5 right-1.5 z-10 flex size-7 cursor-grab touch-none items-center justify-center rounded-md text-white/90 transition-colors hover:text-white focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:outline-none active:cursor-grabbing"
              aria-label="Reorder image. Use arrow keys to move."
              @keydown="(event) => onHandleKeydown(event, index)"
            >
              <Icon name="lucide:grip-vertical" class="size-4 shrink-0" />
            </button>

            <template v-if="canEditCaption">
              <div
                class="pointer-events-none absolute inset-x-0 bottom-0 h-11 bg-linear-to-t from-black/35 to-transparent"
              />
              <Popover
                :open="openCaptionId === item[idKey]"
                @update:open="(open) => toggleCaption(item, open)"
              >
                <PopoverTrigger as-child>
                  <button
                    type="button"
                    class="absolute bottom-1.5 left-1.5 z-10 flex max-w-[calc(100%-0.75rem)] items-center gap-x-1 rounded-md px-1.5 py-1 text-white/90 backdrop-blur-sm transition-colors hover:text-white focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:outline-none"
                    :class="item.caption ? 'bg-primary/80' : 'bg-black/30'"
                    :aria-label="item.caption ? 'Edit caption' : 'Add caption'"
                    @click.stop
                  >
                    <Icon name="lucide:pencil" class="size-3.5 shrink-0" />
                    <span class="truncate text-xs tracking-tight">
                      {{ item.caption || "Caption" }}
                    </span>
                  </button>
                </PopoverTrigger>
                <PopoverContent
                  class="w-72 space-y-2"
                  @click.stop
                  @pointerdown.stop
                  @keydown.stop
                >
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Caption / alt text for this photo.
                  </p>
                  <Textarea
                    v-model="draftCaptions[item[idKey]]"
                    placeholder="Describe this photo…"
                    rows="3"
                    class="resize-none"
                  />
                  <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" size="sm" @click="cancelCaption(item)">
                      Cancel
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      :disabled="savingCaptionId === item[idKey]"
                      @click="saveCaption(item)"
                    >
                      <Spinner v-if="savingCaptionId === item[idKey]" class="size-4" />
                      <span>Save</span>
                    </Button>
                  </div>
                </PopoverContent>
              </Popover>
            </template>

            <slot name="tile-overlay" :item="item" :index="index" />
          </div>
        </div>
      </template>
    </Lightbox>

    <span class="sr-only" role="status" aria-live="polite">{{ announcement }}</span>

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
            <Button
              type="button"
              variant="destructive"
              :disabled="deletePending"
              @click="confirmDelete"
            >
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
import { computed, nextTick, reactive, ref, watch } from "vue";
import { useMediaQuery } from "@vueuse/core";
import { toast } from "vue-sonner";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Spinner } from "@/components/ui/spinner";
import { Lightbox } from "@/components/ui/lightbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { useGalleryReorder } from "@/composables/useGalleryReorder";

const items = defineModel("items", { type: Array, default: () => [] });

const props = defineProps({
  canDelete: { type: Boolean, default: true },
  canReorder: { type: Boolean, default: true },
  columnsClass: {
    type: String,
    default: "grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5",
  },
  tileAspectClass: { type: String, default: "aspect-square" },
  compactToolbar: { type: Boolean, default: false },
  thumbnailKey: { type: String, default: "sm" },
  lightbox: { type: Boolean, default: true },
  alt: { type: String, default: "Gallery" },
  idKey: { type: String, default: "id" },
  reorderEndpoint: { type: String, default: "/api/media/reorder" },
  deleteEndpoint: { type: String, default: "/api/media/bulk-delete" },
  editableCaption: { type: Boolean, default: false },
  captionEndpoint: { type: Function, default: (id) => `/api/media/${id}` },
});

const canEditCaption = computed(() => props.editableCaption);

const emit = defineEmits(["changed"]);

const client = useSanctumClient();
const reduceMotion = useMediaQuery("(prefers-reduced-motion: reduce)");
const gridRef = ref(null);
const idKey = computed(() => props.idKey);
const getId = (media) => media[props.idKey];
const leavingIds = ref(new Set());

/**
 * Local writable working copy. SortableJS mutates its target array in place, but
 * the `items` model proxies a readonly prop, so splicing it directly is blocked
 * by Vue. We sort/delete on this copy and commit the result back to the model.
 */
const localItems = ref([]);
let suppressResync = false;

function commit(ordered) {
  suppressResync = true;
  items.value = (ordered ?? localItems.value).map((media) => ({ ...media }));
  nextTick(() => {
    suppressResync = false;
  });
}

const { saveStatus, scheduleSave, seedSaved } = useGalleryReorder({
  items: localItems,
  client,
  endpoint: () => props.reorderEndpoint,
  idKey,
  onCommit: commit,
  onChanged: () => emit("changed"),
  onError: (e) => toast.error(e?.data?.message || "Failed to save order"),
});

const statusLabel = computed(
  () => ({ saving: "Saving…", saved: "Saved", error: "Couldn't save" })[saveStatus.value] ?? "",
);

watch(
  items,
  (val) => {
    if (suppressResync) {
      return;
    }
    const incoming = Array.isArray(val) ? val : [];
    localItems.value = incoming.map((media) => ({ ...media }));
    seedSaved(incoming);
  },
  { immediate: true, deep: true },
);

useSortableList(gridRef, localItems, {
  enabled: computed(() => props.canReorder),
  onReorder: scheduleSave,
  sortableOptions: {
    animation: reduceMotion.value ? 0 : 200,
    ghostClass: "gallery-ghost",
    chosenClass: "gallery-chosen",
    dragClass: "gallery-drag",
  },
});

const announcement = ref("");

function onHandleKeydown(event, index) {
  let target = null;
  if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
    target = index - 1;
  } else if (event.key === "ArrowRight" || event.key === "ArrowDown") {
    target = index + 1;
  } else {
    return;
  }
  event.preventDefault();
  if (target < 0 || target >= localItems.value.length) {
    return;
  }
  const next = localItems.value.slice();
  const [moved] = next.splice(index, 1);
  next.splice(target, 0, moved);
  localItems.value = next;
  announcement.value = `Moved to position ${target + 1} of ${next.length}`;
  scheduleSave();
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
  selected.value = value ? new Set(localItems.value.map(getId)) : new Set();
}

watch(localItems, (list) => {
  const present = new Set(list.map(getId));
  const filtered = new Set([...selected.value].filter((id) => present.has(id)));
  if (filtered.size !== selected.value.size) {
    selected.value = filtered;
  }
});

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
    const response = await client(props.deleteEndpoint, {
      method: "DELETE",
      body: { media_ids: ids },
    });
    const deletedIds = Array.isArray(response?.deleted_media)
      ? response.deleted_media.map((media) => media.id)
      : ids;
    const removed = new Set(deletedIds);
    selected.value = new Set();
    deleteDialogOpen.value = false;
    if (!reduceMotion.value) {
      leavingIds.value = new Set(removed);
      await new Promise((resolve) => setTimeout(resolve, 200));
    }
    localItems.value = localItems.value.filter((media) => !removed.has(getId(media)));
    leavingIds.value = new Set();
    commit();
    seedSaved(localItems.value);
    toast.success(`${removed.size} image${removed.size > 1 ? "s" : ""} deleted`);
    const failedCount = response?.failed_deletes?.length ?? 0;
    if (failedCount > 0) {
      toast.warning(`${failedCount} image${failedCount > 1 ? "s" : ""} couldn't be deleted`);
    }
    emit("changed");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete images");
  } finally {
    deletePending.value = false;
  }
}

// --- Caption editing (opt-in via `editableCaption`) ----------------------
const openCaptionId = ref(null);
const draftCaptions = reactive({});
const savingCaptionId = ref(null);

function toggleCaption(item, open) {
  const id = getId(item);
  if (open) {
    draftCaptions[id] = item.caption || "";
    openCaptionId.value = id;
  } else if (openCaptionId.value === id) {
    openCaptionId.value = null;
  }
}

function cancelCaption(item) {
  if (openCaptionId.value === getId(item)) {
    openCaptionId.value = null;
  }
}

async function saveCaption(item) {
  const id = getId(item);
  const value = (draftCaptions[id] ?? "").trim();
  savingCaptionId.value = id;
  try {
    const response = await client(props.captionEndpoint(id), {
      method: "PATCH",
      body: { caption: value },
    });
    const caption = response?.data?.caption ?? (value || null);
    const index = localItems.value.findIndex((media) => getId(media) === id);
    if (index !== -1) {
      localItems.value[index] = { ...localItems.value[index], caption };
      commit();
      seedSaved(localItems.value);
    }
    openCaptionId.value = null;
    toast.success("Caption saved");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to save caption");
  } finally {
    savingCaptionId.value = null;
  }
}
</script>

<style>
.gallery-tile {
  transition: opacity 0.2s ease;
}
.gallery-tile.is-leaving {
  opacity: 0;
}
.gallery-ghost {
  opacity: 0.4;
}
.gallery-chosen {
  box-shadow: var(--shadow-lg, 0 10px 25px -5px rgb(0 0 0 / 0.25));
}
.gallery-drag {
  transform: scale(1.03);
  opacity: 0.95;
  cursor: grabbing;
}
@media (prefers-reduced-motion: reduce) {
  .gallery-tile {
    transition: none;
  }
  .gallery-drag {
    transform: none;
  }
}
</style>
