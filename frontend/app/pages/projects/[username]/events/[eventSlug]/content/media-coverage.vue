<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-5xl">
    <!-- Page header -->
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:news" class="size-5 sm:size-6" />
        <h1 class="page-title">Media Coverage</h1>
        <span
          v-if="!loading && items.length"
          class="text-muted-foreground text-sm tracking-tight tabular-nums"
        >
          {{ items.length }}
        </span>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          type="button"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
          @click="openCopyDialog"
        >
          <Icon name="hugeicons:copy-01" class="size-4 shrink-0" />
          <span class="hidden sm:inline">Copy from Event</span>
        </button>
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
          @click="openCreate"
        >
          <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
          <span>Add Coverage</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="!items.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:news" />
        </div>
        <div>
          <Icon name="hugeicons:file-02" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:link-02" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No media coverage yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Add press articles and media coverage shown on the event website.
        </p>
      </div>
      <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
        <button
          type="button"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
          @click="openCreate"
        >
          <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
          <span>Add First Coverage</span>
        </button>
        <button
          type="button"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
          @click="openCopyDialog"
        >
          <Icon name="hugeicons:copy-01" class="size-4 shrink-0" />
          <span>Copy from Event</span>
        </button>
      </div>
    </div>

    <!-- List -->
    <div v-else ref="listContainer" class="mx-auto flex w-full max-w-2xl flex-col">
      <button
        v-for="item in items"
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
          <Icon name="lucide:grip-vertical" class="size-4" />
        </span>

        <span
          class="text-muted-foreground bg-muted/60 mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg text-sm font-medium tabular-nums"
        >
          {{ item.order_column }}
        </span>

        <!-- Body -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-x-2">
            <span class="line-clamp-2 text-base font-semibold tracking-tighter sm:text-lg">
              {{ item.title || "Untitled" }}
            </span>
            <span
              v-if="!item.is_active"
              class="bg-muted text-muted-foreground shrink-0 rounded px-1.5 py-0.5 text-xs tracking-tight sm:text-sm"
            >
              Hidden
            </span>
          </div>
          <p class="text-muted-foreground mt-0.5 line-clamp-1 text-sm tracking-tight">
            {{ domain(item.url) }}
            <template v-if="item.published_at"> · {{ formatDate(item.published_at) }}</template>
          </p>
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

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="formOpen" dialog-max-width="42rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pt-5 pb-8 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editingItem ? "Edit Media Coverage" : "Add Media Coverage" }}
          </h3>
          <div class="mt-4">
            <FormMediaCoverage
              :key="formKey"
              :item="editingItem"
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
          <h3 class="text-lg font-semibold tracking-tight">Delete this coverage?</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            "{{ deletingItem?.title || "Untitled" }}" will be moved to trash. You can restore it
            later.
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

    <!-- Copy from Event dialog -->
    <DialogResponsive v-model:open="copyOpen" dialog-max-width="28rem">
      <template #default>
        <div class="px-4 pt-5 pb-8 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">Copy Coverage from Event</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Copy all media coverage from another event in this project into the current event.
          </p>

          <div class="mt-4 space-y-4">
            <div v-if="copyEventsLoading" class="flex justify-center py-4">
              <Spinner class="size-5" />
            </div>
            <div
              v-else-if="!copyEvents.length"
              class="text-muted-foreground py-4 text-center text-sm tracking-tight"
            >
              No other events in this project have media coverage.
            </div>
            <div v-else class="space-y-2">
              <Label>Source Event</Label>
              <Select v-model="copySourceEventId">
                <SelectTrigger>
                  <SelectValue placeholder="Select an event" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="ev in copyEvents" :key="ev.id" :value="String(ev.id)">
                    {{ ev.title }} ({{ ev.media_coverages_count }} items)
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="copyOpen = false"
              >
                Cancel
              </button>
              <button
                type="button"
                :disabled="!copySourceEventId || copySaving"
                class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                @click="handleCopyFromEvent"
              >
                <Spinner v-if="copySaving" class="size-4" />
                <span>Copy</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Trash dialog -->
    <DialogResponsive v-model:open="trashOpen" dialog-max-width="36rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pt-5 pb-6 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">Trashed Coverage</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Restore deleted media coverage, or permanently remove them.
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
                <h4 class="truncate text-sm font-medium tracking-tight">
                  {{ item.title || "Untitled" }}
                </h4>
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
import FormMediaCoverage from "@/components/FormMediaCoverage.vue";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { useSortableList } from "@/composables/useSortableList";
import { toast } from "vue-sonner";

defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Media Coverage" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/media-coverages`;

// --- State ---
const items = ref([]);
const loading = ref(true);

const fetchItems = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    items.value = response.data ?? [];
  } catch (err) {
    console.error("Failed to load media coverage:", err);
    toast.error("Failed to load media coverage");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchItems);

// --- Drag-drop reorder (useSortableList mutates `items` in place) ---
const listContainer = ref(null);

useSortableList(listContainer, items, {
  onReorder: async () => {
    const orders = items.value.map((f, idx) => ({ id: f.id, order: idx + 1 }));
    try {
      await client(`${apiBase}/reorder`, { method: "POST", body: { orders } });
      items.value.forEach((f, idx) => (f.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder media coverage");
      await fetchItems();
    }
  },
});

// --- Create / Edit ---
const formOpen = ref(false);
const editingItem = ref(null);
const formSaving = ref(false);
const formErrors = ref({});
const formKey = ref(0);

const openCreate = () => {
  editingItem.value = null;
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

const openEdit = (item) => {
  editingItem.value = item;
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

const handleSave = async (payload) => {
  formSaving.value = true;
  formErrors.value = {};

  try {
    if (editingItem.value) {
      await client(`${apiBase}/${editingItem.value.id}`, { method: "PUT", body: payload });
      toast.success("Media coverage updated");
    } else {
      await client(apiBase, { method: "POST", body: payload });
      toast.success("Media coverage created");
    }
    formOpen.value = false;
    await fetchItems();
  } catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      formErrors.value = err.data.errors;
    } else {
      toast.error("Failed to save media coverage", {
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
    toast.success("Media coverage moved to trash");
    deleteOpen.value = false;
    await fetchItems();
  } catch (err) {
    toast.error("Failed to delete media coverage");
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
    toast.success("Media coverage restored");
    trashItems.value = trashItems.value.filter((t) => t.id !== item.id);
    await fetchItems();
  } catch (err) {
    toast.error("Failed to restore media coverage");
  }
};

const forceDeleteItem = async (item) => {
  try {
    await client(`${apiBase}/trash/${item.id}`, { method: "DELETE" });
    toast.success("Media coverage permanently deleted");
    trashItems.value = trashItems.value.filter((t) => t.id !== item.id);
  } catch (err) {
    toast.error("Failed to delete media coverage");
  }
};

// --- Copy from another event ---
const copyOpen = ref(false);
const copyEvents = ref([]);
const copyEventsLoading = ref(false);
const copySourceEventId = ref(null);
const copySaving = ref(false);

const openCopyDialog = async () => {
  copySourceEventId.value = null;
  copyOpen.value = true;
  copyEventsLoading.value = true;
  try {
    const response = await client(`${apiBase}/source-events`);
    copyEvents.value = response.data ?? [];
  } catch (err) {
    copyEvents.value = [];
    toast.error("Failed to load events");
  } finally {
    copyEventsLoading.value = false;
  }
};

const handleCopyFromEvent = async () => {
  if (!copySourceEventId.value) return;
  copySaving.value = true;
  try {
    const response = await client(`${apiBase}/copy-from-event`, {
      method: "POST",
      body: { source_event_id: Number(copySourceEventId.value) },
    });
    toast.success(response.message || "Media coverage copied");
    copyOpen.value = false;
    await fetchItems();
  } catch (err) {
    toast.error("Failed to copy media coverage", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    copySaving.value = false;
  }
};

// --- Helpers ---
function domain(url) {
  if (!url) return "";
  return url.replace(/^https?:\/\/(www\.)?/, "").split("/")[0];
}

function formatDate(datetime) {
  if (!datetime) return "";
  return new Date(datetime).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
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
