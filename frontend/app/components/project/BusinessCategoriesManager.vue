<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title flex items-center justify-between">
        <span>Business Categories</span>
        <div class="flex items-center gap-1">
          <ProjectBusinessCategoriesImportDialog
            :project-username="projectUsername"
            @imported="fetchCategories"
          >
            <template #trigger="{ open }">
              <button
                @click="open()"
                class="border-border hover:bg-muted text-foreground flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
                <span>Import</span>
              </button>
            </template>
          </ProjectBusinessCategoriesImportDialog>
          <button
            @click="handleExport"
            :disabled="exportPending || !categories.length"
            class="border-border hover:bg-muted text-foreground flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Spinner v-if="exportPending" class="size-4 shrink-0" />
            <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
            <span>Export</span>
          </button>
        </div>
      </div>
    </div>
    <div class="frame-panel">
      <p class="text-muted-foreground mb-4 text-sm">
        Define predefined business categories that brands can select from for this project.
      </p>

      <!-- Existing categories list -->
      <div v-if="categories.length" ref="sortableEl" class="mb-6 space-y-2">
        <div
          v-for="category in categories"
          :key="category.id"
          class="bg-muted/50 flex items-center gap-x-3 rounded-lg border px-3 py-2.5"
        >
          <Icon
            name="lucide:grip-vertical"
            class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
          />

          <div class="min-w-0 flex-1">
            <span class="text-sm font-medium">{{ category.name }}</span>
          </div>

          <div class="flex items-center gap-x-1">
            <button
              type="button"
              @click="editCategory(category)"
              class="text-muted-foreground hover:text-foreground rounded p-1 transition"
            >
              <Icon name="lucide:pencil" class="size-3.5" />
            </button>
            <button
              type="button"
              @click="openDeleteDialog(category)"
              class="text-muted-foreground hover:text-destructive rounded p-1 transition"
            >
              <Icon name="lucide:trash-2" class="size-3.5" />
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-muted-foreground mb-6 py-6 text-center text-sm">
        No business categories defined yet.
      </div>

    </div>

    <!-- Delete Confirmation Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Delete business category "{{ categoryToDelete?.name }}"? Brands that have this category
            selected will no longer show it.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="deletePending"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="deletePending"
              @click="handleDelete"
            >
              <Spinner v-if="deletePending" class="size-4 text-white" />
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
});

const client = useSanctumClient();
const sortableEl = ref(null);
const categories = ref([]);
const saving = ref(false);
const editing = ref(null);
const exportPending = ref(false);
const deleteDialogOpen = ref(false);
const categoryToDelete = ref(null);
const deletePending = ref(false);

const apiBase = computed(() => `/api/projects/${props.projectUsername}/business-categories`);

const categoryForm = reactive({
  name: "",
});

function resetForm() {
  categoryForm.name = "";
  editing.value = null;
}

function editCategory(category) {
  editing.value = category.id;
  categoryForm.name = category.name;
}

function cancelEdit() {
  resetForm();
}

async function fetchCategories() {
  try {
    const res = await client(apiBase.value);
    categories.value = res.data;
  } catch (e) {
    console.error("Failed to load business categories:", e);
  }
}

async function saveCategory() {
  if (!categoryForm.name) return;
  saving.value = true;

  try {
    const body = { name: categoryForm.name };

    if (editing.value) {
      await client(`${apiBase.value}/${editing.value}`, {
        method: "PUT",
        body,
      });
      toast.success("Business category updated");
    } else {
      await client(apiBase.value, {
        method: "POST",
        body,
      });
      toast.success("Business category added");
    }

    resetForm();
    await fetchCategories();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to save business category");
  } finally {
    saving.value = false;
  }
}

function openDeleteDialog(category) {
  categoryToDelete.value = category;
  deleteDialogOpen.value = true;
}

async function handleDelete() {
  if (!categoryToDelete.value) return;
  deletePending.value = true;

  try {
    await client(`${apiBase.value}/${categoryToDelete.value.id}`, {
      method: "DELETE",
    });
    toast.success("Business category deleted");
    deleteDialogOpen.value = false;
    categoryToDelete.value = null;
    await fetchCategories();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete business category");
  } finally {
    deletePending.value = false;
  }
}

async function handleExport() {
  try {
    exportPending.value = true;

    const response = await client(`${apiBase.value}/export`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `business_categories_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Business categories exported successfully");
  } catch (error) {
    console.error("Failed to export business categories:", error);
    toast.error("Failed to export business categories");
  } finally {
    exportPending.value = false;
  }
}

async function updateOrder() {
  try {
    const orders = categories.value.map((c, i) => ({
      id: c.id,
      order: i + 1,
    }));

    await client(`${apiBase.value}/reorder`, {
      method: "PUT",
      body: { orders },
    });
  } catch (e) {
    console.error("Failed to update order:", e);
  }
}

// Setup sortable
onMounted(async () => {
  await fetchCategories();

  await nextTick();

  if (sortableEl.value) {
    useSortable(sortableEl.value, categories, {
      animation: 200,
      handle: ".drag-handle",
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      onEnd: async () => {
        await nextTick();
        await updateOrder();
      },
    });
  }
});

// Re-init sortable when categories change
watch(
  () => categories.value.length,
  async () => {
    await nextTick();
    if (sortableEl.value) {
      useSortable(sortableEl.value, categories, {
        animation: 200,
        handle: ".drag-handle",
        ghostClass: "sortable-ghost",
        chosenClass: "sortable-chosen",
        onEnd: async () => {
          await nextTick();
          await updateOrder();
        },
      });
    }
  }
);
</script>
