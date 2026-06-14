<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title">{{ group.label }}</div>
      <div v-if="group.description" class="frame-description">{{ group.description }}</div>
    </div>

    <div class="frame-panel !px-0 !py-0">
      <div ref="listRef" class="divide-border divide-y overflow-hidden rounded-[inherit]">
        <div
          v-for="banner in items"
          :key="banner.id"
          class="hover:bg-muted/40 flex items-center gap-x-2.5 px-3 py-3 transition-colors sm:gap-x-3 sm:px-5"
          :class="{ 'opacity-60': !banner.is_active }"
        >
          <!-- Drag handle -->
          <Icon
            v-if="canReorder"
            name="lucide:grip-vertical"
            class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab active:cursor-grabbing"
          />

          <!-- Thumbnail -->
          <div class="bg-muted aspect-square w-14 shrink-0 overflow-hidden rounded-lg sm:w-16">
            <button
              v-if="banner.image?.sm"
              type="button"
              class="block size-full cursor-zoom-in"
              :aria-label="`Open ${banner.title || 'banner'} image`"
              @click="openImage(banner)"
            >
              <img
                :src="banner.image.sm"
                :alt="banner.title || 'Banner'"
                class="size-full object-cover"
                loading="lazy"
              />
            </button>
            <div v-else class="text-muted-foreground flex size-full items-center justify-center">
              <Icon :name="banner.type === 'text' ? 'lucide:type' : 'lucide:image'" class="size-5" />
            </div>
          </div>

          <!-- Info -->
          <div class="min-w-0 flex-1 space-y-0.5">
            <div class="flex items-center gap-x-2">
              <p class="truncate text-sm font-medium tracking-tight">
                {{ banner.title || "Untitled banner" }}
              </p>
              <Badge v-if="!banner.is_active" variant="muted" class="shrink-0">Hidden</Badge>
            </div>
            <div
              class="text-muted-foreground flex flex-col gap-y-0.5 text-xs tracking-tight sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-1.5 sm:text-sm"
            >
              <span>{{ typeLabel(banner.type) }}</span>
              <span v-if="scheduleLabel(banner)" class="flex items-center gap-x-1.5">
                <span aria-hidden="true" class="hidden sm:inline">·</span>{{ scheduleLabel(banner) }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex shrink-0 flex-col items-end gap-y-1.5">
            <div class="flex items-center gap-x-1 sm:gap-x-2">
              <Switch
                v-if="canUpdate"
                :model-value="banner.is_active"
                @update:model-value="emit('toggle', banner)"
              />

              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="iconSm" aria-label="Banner actions">
                    <Icon name="lucide:ellipsis" class="size-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-40">
                  <DropdownMenuItem @select="emit('analytics', banner)">
                    <Icon name="hugeicons:analytics-01" class="size-4" />
                    Analytics
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="canUpdate" @select="emit('edit', banner)">
                    <Icon name="hugeicons:edit-02" class="size-4" />
                    Edit
                  </DropdownMenuItem>
                  <template v-if="canDelete">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive" @select="emit('delete', banner)">
                      <Icon name="hugeicons:delete-01" class="size-4" />
                      Delete
                    </DropdownMenuItem>
                  </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <!-- Stats -->
            <div
              class="text-muted-foreground mr-1 flex items-center gap-2 text-xs tracking-tight tabular-nums sm:gap-3 sm:text-sm"
            >
              <span class="flex items-center gap-1" v-tippy="'Total impressions'">
                <Icon name="lucide:eye" class="size-4" />{{ formatCount(banner.impressions_count) }}
              </span>
              <span class="flex items-center gap-1" v-tippy="'Total clicks'">
                <Icon name="lucide:mouse-pointer-click" class="size-4" />{{
                  formatCount(banner.clicks_count)
                }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useSortableList } from "@/composables/useSortableList";

const props = defineProps({
  group: { type: Object, required: true },
  canUpdate: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  openImage: { type: Function, required: true },
});

const emit = defineEmits(["toggle", "edit", "delete", "analytics", "reorder"]);

const { $dayjs } = useNuxtApp();

const typeLabel = (type) =>
  ({ image: "Image", text: "Text", image_text: "Image + Text" })[type] ?? type;

const formatCount = (n) => (n ?? 0).toLocaleString();

const scheduleLabel = (banner) => {
  const start = banner.start_time ? $dayjs(banner.start_time) : null;
  const end = banner.end_time ? $dayjs(banner.end_time) : null;

  if (start && end) {
    const startLabel = start.isSame(end, "year")
      ? start.format("MMM D")
      : start.format("MMM D, YYYY");
    return `${startLabel} – ${end.format("MMM D, YYYY")}`;
  }
  if (start) return `From ${start.format("MMM D, YYYY")}`;
  if (end) return `Until ${end.format("MMM D, YYYY")}`;
  return null;
};

// ── Reorder (within this placement only) ──────────────
// SortableJS needs a writable ref it can splice/reassign, so keep a local copy
// that mirrors the group's items and re-syncs whenever the parent rebuilds the
// group (e.g. after a save). The parent reconciles its own order on @reorder.
const listRef = ref(null);
const items = ref([...props.group.items]);
watch(
  () => props.group.items,
  (next) => {
    items.value = [...next];
  },
);

const canReorder = computed(() => props.canUpdate && items.value.length > 1);

useSortableList(listRef, items, {
  enabled: canReorder,
  onReorder: () =>
    emit("reorder", {
      placement: props.group.placement,
      ids: items.value.map((banner) => banner.id),
    }),
});
</script>
