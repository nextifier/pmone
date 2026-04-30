<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Page header -->
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:time-schedule" class="size-5 sm:size-6" />
        <h1 class="page-title">Rundown</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <RundownExportMenu :username="username" :event-slug="eventSlug" />
        <RundownImportDialog :username="username" :event-slug="eventSlug" @imported="fetchItems">
          <template #trigger="{ open }">
            <button
              type="button"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
              @click="open"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span class="hidden sm:inline">Import</span>
            </button>
          </template>
        </RundownImportDialog>
        <button
          type="button"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
          @click="openTrash"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span class="hidden sm:inline">Trash</span>
        </button>
        <button
          type="button"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
          @click="openCreate(null)"
        >
          <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
          <span>Add Item</span>
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div v-if="!loading && rawDays.length" class="flex flex-wrap items-center gap-2">
      <div class="relative min-w-48 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input v-model="search" placeholder="Search title, theme, location..." class="pl-9" />
      </div>

      <Select v-model="visibilityFilter">
        <SelectTrigger class="w-40 shrink-0">
          <SelectValue placeholder="All visibility" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All visibility</SelectItem>
          <SelectItem value="visible">Visible only</SelectItem>
          <SelectItem value="hidden">Hidden only</SelectItem>
        </SelectContent>
      </Select>

      <button
        v-if="hasActiveFilters"
        type="button"
        class="text-muted-foreground hover:bg-muted rounded-md px-3 py-1.5 text-sm tracking-tight"
        @click="resetFilters"
      >
        Reset
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty (no items at all) -->
    <div
      v-else-if="!hasAnyItem && !rawDays.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:calendar-03" />
        </div>
        <div>
          <Icon name="hugeicons:clock-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:task-daily-01" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No rundown items yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Add your first session, keynote, break, or field trip. Items are auto-grouped by date.
        </p>
      </div>
      <button
        type="button"
        class="bg-primary text-primary-foreground hover:bg-primary/90 mt-2 flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        @click="openCreate(null)"
      >
        <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
        <span>Add First Item</span>
      </button>
    </div>

    <!-- Filtered empty -->
    <div
      v-else-if="!filteredDays.some((d) => d.items.length) && hasActiveFilters"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No items match your filters.
      <button type="button" class="text-primary hover:underline" @click="resetFilters">
        Clear filters
      </button>
    </div>

    <!-- Day tabs -->
    <Tabs
      v-else
      v-model="activeDayKey"
      variant="segmented"
      swipe
      class="flex w-full flex-col gap-6"
    >
      <div class="flex justify-center">
        <TabsList class="scroll-fade-x no-scrollbar w-full max-w-full overflow-x-auto">
          <TabsIndicator />
          <TabsTrigger
            v-for="day in filteredDays"
            :key="dayKey(day)"
            :value="dayKey(day)"
            class="h-auto shrink-0 py-1.5 [&>span]:overflow-visible [&>span]:whitespace-normal"
          >
            <span class="flex flex-col items-center gap-0.5 px-1">
              <span class="text-base leading-tight font-semibold tracking-tighter">
                {{ day.day_label ?? "Unscheduled" }}
              </span>
              <span class="text-xs leading-tight tracking-tight tabular-nums sm:text-sm">
                {{ formatTabDate(day.date) }}
              </span>
            </span>
          </TabsTrigger>
        </TabsList>
      </div>

      <TabsContent
        v-for="day in filteredDays"
        :key="dayKey(day)"
        :value="dayKey(day)"
        force-mount
        class="mx-auto w-full max-w-2xl space-y-3"
      >
        <div class="flex items-center justify-between gap-x-3 sm:px-3">
          <p class="text-muted-foreground text-sm font-medium tracking-tight">
            {{ formatDate(day.date) }} · {{ day.items.length }}
            {{ day.items.length === 1 ? "item" : "items" }}
          </p>
          <button
            type="button"
            class="border-border bg-background hover:bg-muted flex shrink-0 items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
            @click="openCreate(day.date)"
          >
            <Icon name="hugeicons:plus-sign" class="size-3.5 shrink-0" />
            <span>Add Item</span>
          </button>
        </div>

        <div
          v-if="day.items.length"
          :ref="(el) => setDayContainerRef(day.date, el)"
          class="flex flex-col"
        >
          <button
            v-for="item in day.items"
            :key="item.id"
            type="button"
            :data-item-id="item.id"
            class="hover:bg-muted/50 group relative flex w-full cursor-pointer items-start gap-x-1 rounded-2xl py-2 text-left transition active:scale-[0.998] sm:gap-x-2 sm:px-3 sm:py-3"
            @click="openEdit(item)"
          >
            <span
              class="drag-handle text-muted-foreground hover:bg-muted hover:text-foreground -ml-1 inline-flex size-6 shrink-0 cursor-grab items-center justify-center self-start rounded-md transition active:cursor-grabbing sm:size-7"
              @click.stop
            >
              <Icon name="hugeicons:drag-drop" class="size-4" />
            </span>

            <!-- Time range + poster (left column) -->
            <div class="flex w-24 shrink-0 flex-col gap-2">
              <span class="pt-0.5 text-sm tracking-tight tabular-nums sm:pt-1">
                {{ formatTimeRange(item) }}
              </span>

              <div
                v-if="item.poster_image?.sm"
                class="bg-muted w-20 shrink-0 overflow-hidden rounded-xl"
              >
                <img
                  :src="item.poster_image.sm"
                  :alt="resolve(item.title)"
                  class="h-full w-full object-cover"
                  loading="lazy"
                />
              </div>
            </div>

            <!-- Body -->
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-x-2">
                <span class="text-base font-semibold tracking-tighter sm:text-lg">
                  {{ resolve(item.title) || "Untitled" }}
                </span>
                <span
                  v-if="!item.is_active"
                  class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs tracking-tight sm:text-sm"
                >
                  Hidden
                </span>
              </div>

              <p v-if="resolveLocalizedList(item.speakers).length" class="mt-0.5 text-sm tracking-tight">
                {{ resolveLocalizedList(item.speakers).map((s) => s?.name).filter(Boolean).join(", ") }}
              </p>

              <p
                v-if="resolveLocalizedList(item.panelists).length"
                class="text-muted-foreground mt-0.5 text-sm tracking-tight"
              >
                <span class="text-muted-foreground">Panelists:</span>
                {{ resolveLocalizedList(item.panelists).map((p) => p?.name).filter(Boolean).join(", ") }}
              </p>

              <p
                v-if="resolve(item.subtitle)"
                class="text-muted-foreground mt-0.5 text-sm tracking-tight"
              >
                {{ resolve(item.subtitle) }}
              </p>

              <p
                v-if="resolve(item.theme)"
                class="text-muted-foreground mt-0.5 text-sm tracking-tight"
              >
                {{ resolve(item.theme) }}
              </p>

              <p
                v-if="resolve(item.location)"
                class="text-muted-foreground mt-0.5 text-sm tracking-tight"
              >
                {{ resolve(item.location) }}
              </p>

              <p
                v-if="resolve(item.moderator)"
                class="text-muted-foreground mt-0.5 text-sm tracking-tight"
              >
                <span class="text-muted-foreground">Moderator:</span>
                {{ resolve(item.moderator) }}
              </p>

              <p
                v-if="resolve(item.presented_by)"
                class="text-muted-foreground mt-0.5 text-sm tracking-tight"
              >
                Presented by {{ resolve(item.presented_by) }}
              </p>

              <div v-if="item.categories?.length" class="mt-1.5 flex flex-wrap items-center gap-1">
                <span
                  v-for="cat in item.categories"
                  :key="cat"
                  class="bg-muted text-foreground rounded px-1.5 py-0.5 text-xs tracking-tight sm:text-sm"
                >
                  {{ cat }}
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex shrink-0 items-center gap-1 self-start pt-0.5" @click.stop>
              <button
                type="button"
                class="bg-background hover:bg-muted border-border inline-flex size-7 items-center justify-center rounded-md border"
                v-tippy="'Edit'"
                @click.stop="openEdit(item)"
              >
                <Icon name="hugeicons:edit-02" class="size-4" />
              </button>
              <button
                type="button"
                class="bg-background hover:bg-destructive/10 border-border inline-flex size-7 items-center justify-center rounded-md border"
                v-tippy="'Delete'"
                @click.stop="confirmDelete(item)"
              >
                <Icon name="hugeicons:delete-01" class="text-destructive-foreground size-4" />
              </button>
            </div>
          </button>
        </div>

        <div v-else class="text-muted-foreground py-6 text-center text-sm tracking-tight">
          No items in this day yet.
          <button type="button" class="text-primary hover:underline" @click="openCreate(day.date)">
            Add one
          </button>
        </div>
      </TabsContent>
    </Tabs>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="formOpen" dialog-max-width="42rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pt-5 pb-8 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editingItem ? "Edit Rundown Item" : "Add Rundown Item" }}
          </h3>
          <div class="mt-4">
            <FormRundownItem
              :key="formKey"
              :item="editingItem"
              :default-date="formDefaultDate"
              :event="event"
              :loading="formSaving"
              :errors="formErrors"
              @submit="handleSave"
              @cancel="formOpen = false"
            />
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteOpen" dialog-max-width="22rem">
      <template #default>
        <div class="px-4 pt-5 pb-6 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">Delete this item?</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            "{{ resolve(deletingItem?.title) || "Untitled" }}" will be moved to trash. You can
            restore it later.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              type="button"
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteOpen = false"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="deleteSaving"
              class="bg-destructive hover:bg-destructive/80 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              @click="handleDelete"
            >
              <Spinner v-if="deleteSaving" class="size-4 text-white" />
              <span>Delete</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Trash dialog -->
    <DialogResponsive v-model:open="trashOpen" dialog-max-width="36rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pt-5 pb-6 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">Trashed Items</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Restore deleted items, or permanently remove them.
          </p>

          <div class="mt-4 max-h-[60vh] space-y-2 overflow-y-auto">
            <div v-if="trashLoading" class="flex items-center justify-center py-8">
              <Spinner class="size-5" />
            </div>
            <div
              v-else-if="!trashItems.length"
              class="text-muted-foreground py-8 text-center text-sm tracking-tight"
            >
              Nothing in trash.
            </div>
            <div
              v-for="item in trashItems"
              v-else
              :key="item.id"
              class="border-border bg-card flex items-center gap-2 rounded-lg border p-3"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <h4 class="truncate text-sm font-medium tracking-tight">
                    {{ resolve(item.title) || "Untitled" }}
                  </h4>
                  <span class="text-muted-foreground text-xs tabular-nums sm:text-sm">
                    {{ item.start_time ?? "" }}
                  </span>
                </div>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Deleted {{ formatRelative(item.deleted_at) }}
                </p>
              </div>
              <button
                type="button"
                class="border-border hover:bg-muted rounded-md border px-2 py-1 text-xs tracking-tight active:scale-98 sm:text-sm"
                @click="restoreItem(item)"
              >
                Restore
              </button>
              <button
                type="button"
                class="hover:bg-destructive/10 inline-flex size-8 shrink-0 items-center justify-center rounded-md"
                v-tippy="'Delete forever'"
                @click="forceDeleteItem(item)"
              >
                <Icon name="hugeicons:delete-01" class="text-destructive size-3.5" />
              </button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import FormRundownItem from "@/components/FormRundownItem.vue";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import Sortable from "sortablejs";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Rundown" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/rundown-items`;

// --- State ---
const rawDays = ref([]);
const loading = ref(true);

const search = ref("");
const visibilityFilter = ref("all");

const hasActiveFilters = computed(() => Boolean(search.value) || visibilityFilter.value !== "all");

const resetFilters = () => {
  search.value = "";
  visibilityFilter.value = "all";
};

const fetchItems = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    rawDays.value = response.data?.days ?? [];
  } catch (err) {
    console.error("Failed to load rundown items:", err);
    toast.error("Failed to load rundown");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchItems);

const hasAnyItem = computed(() => rawDays.value.some((d) => d.items?.length));

const dayKey = (day) => day.date ?? "_unscheduled";

// --- Filtering ---
const filteredDays = computed(() => {
  const q = search.value.trim().toLowerCase();
  return rawDays.value.map((day) => ({
    ...day,
    items: (day.items ?? []).filter((item) => {
      if (visibilityFilter.value === "visible" && !item.is_active) return false;
      if (visibilityFilter.value === "hidden" && item.is_active) return false;

      if (q) {
        const haystack = [
          resolve(item.title),
          resolve(item.subtitle),
          resolve(item.theme),
          resolve(item.location),
          resolve(item.presented_by),
          resolve(item.moderator),
          ...(item.categories ?? []),
          ...resolveLocalizedList(item.speakers).map((s) => s?.name).filter(Boolean),
          ...resolveLocalizedList(item.panelists).map((p) => p?.name).filter(Boolean),
        ]
          .join(" ")
          .toLowerCase();
        if (!haystack.includes(q)) return false;
      }

      return true;
    }),
  }));
});

// --- Active day tab ---
const activeDayKey = ref(null);

watch(
  filteredDays,
  (days) => {
    if (!days?.length) return;
    if (!days.some((d) => dayKey(d) === activeDayKey.value)) {
      activeDayKey.value = dayKey(days[0]);
    }
  },
  { immediate: true }
);

// --- Drag-drop reorder per day ---
const dayContainerRefs = {};
const daySortableInstances = {};

const setDayContainerRef = (dateKey, el) => {
  const key = dateKey ?? "_unscheduled";
  if (el) {
    dayContainerRefs[key] = el;
  }
};

const initDaySortables = () => {
  Object.values(daySortableInstances).forEach((instance) => instance.destroy());
  Object.keys(daySortableInstances).forEach((key) => delete daySortableInstances[key]);

  for (const day of filteredDays.value) {
    const key = day.date ?? "_unscheduled";
    const el = dayContainerRefs[key];
    if (!el || !day.items?.length) continue;

    daySortableInstances[key] = Sortable.create(el, {
      animation: 200,
      handle: ".drag-handle",
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      dragClass: "sortable-drag",
      onEnd: async () => {
        const ids = Array.from(el.querySelectorAll("[data-item-id]")).map((node) =>
          Number(node.dataset.itemId)
        );

        const orders = ids.map((id, idx) => ({ id, order: idx + 1 }));

        try {
          await client(`${apiBase}/reorder`, {
            method: "POST",
            body: { date: day.date, orders },
          });
          await fetchItems();
        } catch (err) {
          toast.error("Failed to reorder items");
        }
      },
    });
  }
};

watch(
  () => filteredDays.value,
  async () => {
    await nextTick();
    initDaySortables();
  },
  { deep: false }
);

onUnmounted(() => {
  Object.values(daySortableInstances).forEach((instance) => instance.destroy());
});

// --- Create / Edit ---
const formOpen = ref(false);
const editingItem = ref(null);
const formDefaultDate = ref(null);
const formSaving = ref(false);
const formErrors = ref({});
const formKey = ref(0);

const defaultStartDate = () => {
  const start = props.event?.start_date;
  if (!start) return null;
  return String(start).slice(0, 10);
};

const openCreate = (date) => {
  editingItem.value = null;
  formDefaultDate.value = date ?? defaultStartDate();
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

const openEdit = (item) => {
  editingItem.value = item;
  formDefaultDate.value = null;
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

const handleSave = async (payload) => {
  formSaving.value = true;
  formErrors.value = {};

  try {
    if (editingItem.value) {
      await client(`${apiBase}/${editingItem.value.id}`, {
        method: "PUT",
        body: payload,
      });
      toast.success("Item updated");
    } else {
      await client(apiBase, {
        method: "POST",
        body: payload,
      });
      toast.success("Item created");
    }
    formOpen.value = false;
    await fetchItems();
  } catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      formErrors.value = err.data.errors;
    } else {
      toast.error("Failed to save item", {
        description: err?.data?.message || err?.message,
      });
    }
  } finally {
    formSaving.value = false;
  }
};

// --- Delete ---
const deleteOpen = ref(false);
const deletingItem = ref(null);
const deleteSaving = ref(false);

const confirmDelete = (item) => {
  deletingItem.value = item;
  deleteOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleteSaving.value = true;
  try {
    await client(`${apiBase}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Item moved to trash");
    deleteOpen.value = false;
    await fetchItems();
  } catch (err) {
    toast.error("Failed to delete item");
  } finally {
    deleteSaving.value = false;
  }
};

// --- Trash ---
const trashOpen = ref(false);
const trashItems = ref([]);
const trashLoading = ref(false);

const openTrash = async () => {
  trashOpen.value = true;
  trashLoading.value = true;
  try {
    const response = await client(`${apiBase}/trash`);
    trashItems.value = response.data ?? [];
  } catch (err) {
    toast.error("Failed to load trash");
  } finally {
    trashLoading.value = false;
  }
};

const restoreItem = async (item) => {
  try {
    await client(`${apiBase}/trash/${item.id}/restore`, { method: "POST" });
    toast.success("Item restored");
    trashItems.value = trashItems.value.filter((t) => t.id !== item.id);
    await fetchItems();
  } catch (err) {
    toast.error("Failed to restore item");
  }
};

const forceDeleteItem = async (item) => {
  try {
    await client(`${apiBase}/trash/${item.id}`, { method: "DELETE" });
    toast.success("Item permanently deleted");
    trashItems.value = trashItems.value.filter((t) => t.id !== item.id);
  } catch (err) {
    toast.error("Failed to delete item");
  }
};

// --- Helpers ---
function resolve(translatable) {
  if (!translatable) return "";
  if (typeof translatable === "string") return translatable;
  return translatable.en ?? translatable.id ?? Object.values(translatable)[0] ?? "";
}

function resolveLocalizedList(value) {
  if (!value) return [];
  if (Array.isArray(value)) return value;
  if (typeof value === "object") {
    return value.en ?? value.id ?? Object.values(value).find(Array.isArray) ?? [];
  }
  return [];
}

function formatDate(dateStr) {
  if (!dateStr) return "Unscheduled";
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatTabDate(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
  });
}

function formatTimeRange(item) {
  const s = item?.start_time;
  const e = item?.end_time;
  if (s && e) return `${s} - ${e}`;
  if (s) return `${s} - Finish`;
  return e || "";
}

function formatRelative(datetime) {
  if (!datetime) return "";
  const d = new Date(datetime);
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return d.toLocaleDateString();
}
</script>
