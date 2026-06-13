<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-5xl">
    <!-- Page header -->
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:presentation-bar-chart-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Programs</h1>
        <span
          v-if="!loading && programs.length"
          class="text-muted-foreground text-sm tracking-tight tabular-nums"
        >
          {{ programs.length }}
        </span>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
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
          <span>Add Program</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="!programs.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:star" />
        </div>
        <div>
          <Icon name="hugeicons:layers-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:activity-01" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No programs yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Add the main programs and highlights shown on the event website.
        </p>
      </div>
      <button
        type="button"
        class="bg-primary text-primary-foreground hover:bg-primary/90 mt-2 flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        @click="openCreate"
      >
        <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
        <span>Add First Program</span>
      </button>
    </div>

    <!-- List -->
    <div v-else ref="listContainer" class="mx-auto flex w-full max-w-2xl flex-col">
      <button
        v-for="program in programs"
        :key="program.id"
        type="button"
        :data-item-id="program.id"
        class="hover:bg-muted/50 group relative flex w-full cursor-pointer items-start gap-x-1 rounded-2xl py-2 text-left transition active:scale-[0.998] sm:gap-x-2 sm:px-3 sm:py-3"
        @click="openEdit(program)"
      >
        <span
          class="drag-handle text-muted-foreground hover:bg-muted hover:text-foreground -ml-1 inline-flex size-6 shrink-0 cursor-grab items-center justify-center self-start rounded-md transition active:cursor-grabbing sm:size-7"
          @click.stop
        >
          <Icon name="lucide:grip-vertical" class="size-4" />
        </span>

        <!-- Thumbnail / icon / order -->
        <div class="flex w-14 shrink-0 items-center justify-center self-start sm:w-16">
          <div
            v-if="program.image?.sm"
            class="bg-muted aspect-[2/3] w-12 shrink-0 overflow-hidden rounded-lg sm:w-14"
          >
            <img
              :src="program.image.sm"
              :alt="resolve(program.title)"
              class="h-full w-full object-cover"
              loading="lazy"
            />
          </div>
          <span
            v-else-if="program.icon"
            class="bg-muted flex size-10 items-center justify-center rounded-xl"
          >
            <Icon :name="program.icon" class="size-5" />
          </span>
          <span
            v-else
            class="text-muted-foreground bg-muted/60 flex size-10 items-center justify-center rounded-xl text-sm font-medium tabular-nums"
          >
            {{ program.order_column }}
          </span>
        </div>

        <!-- Body -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-x-2">
            <span class="text-base font-semibold tracking-tighter sm:text-lg">
              {{ resolve(program.title) || "Untitled" }}
            </span>
            <span
              v-if="!program.is_active"
              class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs tracking-tight sm:text-sm"
            >
              Hidden
            </span>
          </div>
          <p
            v-if="resolve(program.description)"
            class="text-muted-foreground mt-0.5 line-clamp-2 text-sm tracking-tight"
          >
            {{ resolve(program.description) }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex shrink-0 items-center gap-1 self-start pt-0.5" @click.stop>
          <button
            type="button"
            class="bg-background hover:bg-muted border-border inline-flex size-7 items-center justify-center rounded-md border"
            v-tippy="'Edit'"
            @click.stop="openEdit(program)"
          >
            <Icon name="hugeicons:edit-02" class="size-4" />
          </button>
          <button
            type="button"
            class="bg-background hover:bg-destructive/10 border-border inline-flex size-7 items-center justify-center rounded-md border"
            v-tippy="'Delete'"
            @click.stop="confirmDelete(program)"
          >
            <Icon name="hugeicons:delete-01" class="text-destructive-foreground size-4" />
          </button>
        </div>
      </button>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="formOpen" dialog-max-width="36rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pt-5 pb-8 md:px-6">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editingItem ? "Edit Program" : "Add Program" }}
          </h3>
          <div class="mt-4">
            <FormProgram
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
          <h3 class="text-lg font-semibold tracking-tight">Delete this program?</h3>
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
          <h3 class="text-lg font-semibold tracking-tight">Trashed Programs</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Restore deleted programs, or permanently remove them.
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
                  {{ resolve(item.title) || "Untitled" }}
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
import FormProgram from "@/components/FormProgram.vue";
import { Spinner } from "@/components/ui/spinner";
import { useSortableList } from "@/composables/useSortableList";
import { toast } from "vue-sonner";

defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Programs" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/programs`;

// --- State ---
const programs = ref([]);
const loading = ref(true);

const fetchPrograms = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    programs.value = response.data ?? [];
  } catch (err) {
    console.error("Failed to load programs:", err);
    toast.error("Failed to load programs");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchPrograms);

// --- Drag-drop reorder (useSortableList mutates `programs` in place) ---
const listContainer = ref(null);

useSortableList(listContainer, programs, {
  onReorder: async () => {
    const orders = programs.value.map((p, idx) => ({ id: p.id, order: idx + 1 }));
    try {
      await client(`${apiBase}/reorder`, { method: "POST", body: { orders } });
      programs.value.forEach((p, idx) => (p.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder programs");
      await fetchPrograms();
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

const openEdit = (program) => {
  editingItem.value = program;
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
      toast.success("Program updated");
    } else {
      await client(apiBase, { method: "POST", body: payload });
      toast.success("Program created");
    }
    formOpen.value = false;
    await fetchPrograms();
  } catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      formErrors.value = err.data.errors;
    } else {
      toast.error("Failed to save program", {
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

const confirmDelete = (program) => {
  deletingItem.value = program;
  deleteOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleteSaving.value = true;
  try {
    await client(`${apiBase}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Program moved to trash");
    deleteOpen.value = false;
    await fetchPrograms();
  } catch (err) {
    toast.error("Failed to delete program");
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
    toast.success("Program restored");
    trashItems.value = trashItems.value.filter((t) => t.id !== item.id);
    await fetchPrograms();
  } catch (err) {
    toast.error("Failed to restore program");
  }
};

const forceDeleteItem = async (item) => {
  try {
    await client(`${apiBase}/trash/${item.id}`, { method: "DELETE" });
    toast.success("Program permanently deleted");
    trashItems.value = trashItems.value.filter((t) => t.id !== item.id);
  } catch (err) {
    toast.error("Failed to delete program");
  }
};

// --- Helpers ---
function resolve(translatable) {
  if (!translatable) return "";
  if (typeof translatable === "string") return translatable;
  return translatable.en ?? translatable.id ?? Object.values(translatable)[0] ?? "";
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
