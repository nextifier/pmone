<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <BackButton destination="/contacts" :show-label="true" />
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title flex items-center justify-between">
          <span>Business Categories</span>
          <div class="flex items-center gap-1">
            <ContactBusinessCategoriesImportDialog @imported="fetchCategories">
              <template #trigger="{ open }">
                <button
                  @click="open()"
                  class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
                >
                  <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
                  <span>Import</span>
                </button>
              </template>
            </ContactBusinessCategoriesImportDialog>
            <button
              @click="handleExport"
              :disabled="exportPending || !categories.length"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="exportPending" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
              <span>Export</span>
            </button>
            <Button size="sm" @click="openCreateDialog">
              <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
              New Category
              <KbdGroup>
                <Kbd>N</Kbd>
              </KbdGroup>
            </Button>
          </div>
        </div>
      </div>
      <div class="frame-panel">
        <p class="text-muted-foreground mb-4 text-sm tracking-tight">
          Define predefined business categories that contacts can select from.
        </p>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-10">
          <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
        </div>

        <template v-else>
          <!-- Existing categories list -->
          <div v-if="categories.length" ref="sortableEl" class="space-y-2">
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
                <span class="text-sm font-medium tracking-tight">{{ category.name }}</span>
              </div>

              <div class="flex items-center gap-x-1">
                <button
                  type="button"
                  @click="openEditDialog(category)"
                  class="text-muted-foreground hover:text-foreground rounded p-1 transition"
                >
                  <Icon name="hugeicons:edit-03" class="size-4" />
                </button>
                <button
                  type="button"
                  @click="openDeleteDialog(category)"
                  class="text-muted-foreground hover:text-destructive rounded p-1 transition"
                >
                  <Icon name="hugeicons:delete-01" class="size-4" />
                </button>
              </div>
            </div>
          </div>

          <div v-else class="text-muted-foreground py-6 text-center text-sm tracking-tight">
            No business categories defined yet.
          </div>
        </template>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="400px">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="space-y-6">
            <div>
              <h2 class="text-primary text-lg font-semibold tracking-tight">
                {{ editingCategory ? "Edit Category" : "New Category" }}
              </h2>
              <p class="text-muted-foreground mt-1 text-sm tracking-tight">
                {{ editingCategory ? "Update the category name." : "Add a new business category." }}
              </p>
            </div>

            <div class="space-y-2">
              <Label for="bc_name">Name</Label>
              <Input
                id="bc_name"
                ref="nameInputRef"
                v-model="categoryForm.name"
                placeholder="e.g. Building Materials"
                @keydown.enter.prevent="saveCategory"
              />
            </div>

            <div class="flex justify-end gap-2">
              <button
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="closeDialog"
              >
                Cancel
              </button>
              <button
                @click="saveCategory"
                :disabled="!categoryForm.name || saving"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Spinner v-if="saving" class="mr-2 inline size-4" />
                <span>{{ editingCategory ? "Update" : "Add" }}</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Confirmation Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Delete business category "{{ categoryToDelete?.name }}"?
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
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["contacts.read"],
  layout: "app",
});

usePageMeta(null, {
  title: "Business Categories",
});

const client = useSanctumClient();
const route = useRoute();
const sortableEl = ref(null);
const categories = ref([]);
const loading = ref(true);
const saving = ref(false);
const exportPending = ref(false);

const dialogOpen = ref(false);
const editingCategory = ref(null);
const nameInputRef = ref(null);
const deleteDialogOpen = ref(false);
const categoryToDelete = ref(null);
const deletePending = ref(false);

const apiBase = "/api/contacts-business-categories";

const categoryForm = reactive({
  name: "",
});

function openCreateDialog() {
  editingCategory.value = null;
  categoryForm.name = "";
  dialogOpen.value = true;
  nextTick(() => nameInputRef.value?.$el?.focus());
}

function openEditDialog(category) {
  editingCategory.value = category;
  categoryForm.name = category.name;
  dialogOpen.value = true;
  nextTick(() => nameInputRef.value?.$el?.focus());
}

function closeDialog() {
  dialogOpen.value = false;
  editingCategory.value = null;
  categoryForm.name = "";
}

async function fetchCategories() {
  try {
    const res = await client(apiBase);
    categories.value = res.data;
  } catch (e) {
    console.error("Failed to load business categories:", e);
  }
  loading.value = false;
}

async function saveCategory() {
  if (!categoryForm.name) return;
  saving.value = true;

  try {
    const body = { name: categoryForm.name };

    if (editingCategory.value) {
      await client(`${apiBase}/${editingCategory.value.id}`, {
        method: "PUT",
        body,
      });
      toast.success("Business category updated");
    } else {
      await client(apiBase, {
        method: "POST",
        body,
      });
      toast.success("Business category added");
    }

    closeDialog();
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
    await client(`${apiBase}/${categoryToDelete.value.id}`, {
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

    const response = await client(`${apiBase}/export`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `contact_business_categories_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
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

    await client(`${apiBase}/reorder`, {
      method: "PUT",
      body: { orders },
    });
  } catch (e) {
    console.error("Failed to update order:", e);
  }
}

// Keyboard shortcut
defineShortcuts({
  n: {
    handler: () => {
      openCreateDialog();
    },
    whenever: [computed(() => route.path === "/contacts/business-categories")],
  },
});

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
