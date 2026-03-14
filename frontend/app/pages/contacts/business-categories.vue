<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <BackButton destination="/contacts" :show-label="true" />
    </div>

    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:tag-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Business Categories</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <ContactBusinessCategoriesImportDialog @imported="fetchCategories">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </ContactBusinessCategoriesImportDialog>
        <button
          @click="handleExport"
          :disabled="exportPending || !categories.length"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>
      </div>

      <div v-else class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear Selection</span>
        </button>
      </div>
    </div>

    <div ref="tableWrapper">
      <TableData
        ref="tableRef"
        :client-only="true"
        :data="categories"
        :columns="columns"
        :meta="tableMeta"
        :pending="loading"
        model="business-categories"
        label="category"
        :searchable="true"
        search-column="name"
        search-placeholder="Search categories..."
        :column-toggle="false"
        :show-add-button="false"
        :show-refresh-button="false"
        :page-sizes="[50, 100]"
        :initial-pagination="{ pageIndex: 0, pageSize: 100 }"
        :initial-sorting="[]"
        @update:column-filters="onColumnFiltersUpdate"
      >
        <template #add-button>
          <Button size="sm" @click="openCreateDialog">
            <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
            New Category
            <KbdGroup>
              <Kbd>N</Kbd>
            </KbdGroup>
          </Button>
        </template>

        <template #actions="{ selectedRows }">
          <DialogResponsive
            v-if="selectedRows.length > 0"
            v-model:open="bulkDeleteDialogOpen"
            class="h-full"
          >
            <template #trigger="{ open }">
              <button
                class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
                @click="open()"
              >
                <Icon name="lucide:trash" class="size-4 shrink-0" />
                <span class="text-sm tracking-tight">Delete</span>
                <span
                  class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
                >
                  {{ selectedRows.length }}
                </span>
              </button>
            </template>
            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
                <p class="text-body mt-1.5 text-sm tracking-tight">
                  This will delete {{ selectedRows.length }}
                  {{ selectedRows.length === 1 ? "category" : "categories" }}.
                </p>
                <div class="mt-3 flex justify-end gap-2">
                  <button
                    class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                    @click="bulkDeleteDialogOpen = false"
                    :disabled="bulkDeletePending"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handleBulkDelete(selectedRows)"
                    :disabled="bulkDeletePending"
                    class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <Spinner v-if="bulkDeletePending" class="size-4 text-white" />
                    <span v-else>Delete</span>
                  </button>
                </div>
              </div>
            </template>
          </DialogResponsive>
        </template>
      </TableData>
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

    <!-- Single Delete Confirmation Dialog -->
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
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { resolveComponent } from "vue";
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
const categories = ref([]);
const loading = ref(true);
const saving = ref(false);
const exportPending = ref(false);

// Dialog state
const dialogOpen = ref(false);
const editingCategory = ref(null);
const nameInputRef = ref(null);
const deleteDialogOpen = ref(false);
const categoryToDelete = ref(null);
const deletePending = ref(false);
const bulkDeleteDialogOpen = ref(false);
const bulkDeletePending = ref(false);

const apiBase = "/api/contacts-business-categories";

const categoryForm = reactive({
  name: "",
});

// Table
const tableRef = ref();
const tableWrapper = ref(null);
const sortableEl = ref(null);
const activeFilters = ref([]);

const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});

const clearSelection = () => {
  tableRef.value?.resetRowSelection();
};

const onColumnFiltersUpdate = (filters) => {
  activeFilters.value = filters;
};

const isFiltered = computed(() => activeFilters.value.some((f) => f.value));

const tableMeta = computed(() => ({
  current_page: 1,
  last_page: 1,
  per_page: 100,
  total: categories.value.length,
}));

// Columns
const columns = computed(() => [
  {
    id: "select",
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && "indeterminate"),
        "onUpdate:modelValue": (value) => table.toggleAllPageRowsSelected(!!value),
        "aria-label": "Select all",
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        "onUpdate:modelValue": (value) => row.toggleSelected(!!value),
        "aria-label": "Select row",
      }),
    size: 28,
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: "drag",
    header: () => h("span", { class: "sr-only" }, "Reorder"),
    cell: () =>
      h(resolveComponent("Icon"), {
        name: "lucide:grip-vertical",
        class: `drag-handle text-muted-foreground size-4 shrink-0 ${isFiltered.value ? "invisible" : "cursor-grab"}`,
      }),
    size: 32,
    enableSorting: false,
    enableHiding: false,
  },
  {
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) =>
      h("span", { class: "text-sm font-medium tracking-tight" }, row.getValue("name")),
    size: 500,
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h("div", { class: "flex items-center justify-end gap-x-1" }, [
        h(
          "button",
          {
            type: "button",
            class: "text-muted-foreground hover:text-foreground rounded p-1 transition",
            onClick: () => openEditDialog(row.original),
          },
          [h(resolveComponent("Icon"), { name: "hugeicons:edit-03", class: "size-4" })]
        ),
        h(
          "button",
          {
            type: "button",
            class: "text-muted-foreground hover:text-destructive rounded p-1 transition",
            onClick: () => openDeleteDialog(row.original),
          },
          [h(resolveComponent("Icon"), { name: "hugeicons:delete-01", class: "size-4" })]
        ),
      ]),
    size: 80,
    enableSorting: false,
    enableHiding: false,
  },
]);

// CRUD operations
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

async function handleBulkDelete(selectedRows) {
  const ids = selectedRows.map((row) => row.original.id);
  bulkDeletePending.value = true;
  try {
    await Promise.all(ids.map((id) => client(`${apiBase}/${id}`, { method: "DELETE" })));
    toast.success(`${ids.length} ${ids.length === 1 ? "category" : "categories"} deleted`);
    bulkDeleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    await fetchCategories();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete categories");
  } finally {
    bulkDeletePending.value = false;
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

// Sortable with proper instance lifecycle (fixes mobile touch)
const sortableEnabled = computed(() => !isFiltered.value);

const { initialize: initializeSortable } = useSortableList(sortableEl, categories, {
  onReorder: updateOrder,
  enabled: sortableEnabled,
});

onMounted(async () => {
  await fetchCategories();
  await nextTick();
  sortableEl.value = tableWrapper.value?.querySelector("tbody");
  initializeSortable();
});
</script>
