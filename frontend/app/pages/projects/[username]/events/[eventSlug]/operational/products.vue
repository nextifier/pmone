<template>
  <div class="flex flex-col gap-y-6">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="space-y-1">
        <h3 class="text-lg font-semibold tracking-tight">Products</h3>
        <p class="text-muted-foreground text-sm tracking-tight">
          Manage product catalog for this event.
        </p>
      </div>

      <div class="ml-auto flex flex-wrap gap-2">
        <!-- Categories -->
        <NuxtLink
          :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/product-categories`"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:layers-01" class="size-4 shrink-0" />
          <span>Categories</span>
        </NuxtLink>

        <!-- Export -->
        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export {{ activeSearch || selectedCategory !== "all" ? "selected" : "all" }}</span>
        </button>

        <template v-if="event?.can_edit">
          <EventProductImportDialog
            :username="route.params.username"
            :event-slug="route.params.eventSlug"
            @imported="onImported"
          >
            <template #trigger="{ open }">
              <Button @click="open" size="sm" variant="outline">
                <Icon name="hugeicons:upload-03" class="size-4" />
                Import
              </Button>
            </template>
          </EventProductImportDialog>

          <Button @click="openCreate" size="sm">
            <Icon name="hugeicons:add-01" class="size-4" />
            Add Product
          </Button>
        </template>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :client-only="true"
      :data="categoryFilteredProducts"
      :columns="columns"
      :meta="meta"
      :pending="loading"
      model="products"
      label="Product"
      :show-add-button="false"
      :initial-sorting="[]"
      search-column="name"
      search-placeholder="Search products..."
      @update:column-filters="onColumnFiltersChange"
    >
      <template #filters>
        <Select v-model="selectedCategory">
          <SelectTrigger class="w-48 shrink-0">
            <SelectValue placeholder="All categories" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            <SelectItem v-for="cat in categories" :key="cat.id" :value="String(cat.id)">
              {{ cat.title }}
            </SelectItem>
          </SelectContent>
        </Select>
      </template>

      <template #actions="{ selectedRows }">
        <!-- Bulk Delete -->
        <DialogResponsive
          v-if="selectedRows.length > 0 && event?.can_edit"
          v-model:open="bulkDeleteDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="lucide:trash" label="Delete" destructive @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "product" : "products" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="bulkDeleteDialogOpen = false"
                  :disabled="bulkDeleting"
                >
                  Cancel
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  :disabled="bulkDeleting"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="bulkDeleting" class="size-4 text-white" />
                  <span v-else>Delete</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </template>
    </TableData>

    <!-- Add/Edit Dialog -->
    <DialogResponsive v-model:open="showFormDialog" dialog-max-width="500px" :overflow-content="true">
      <template #sticky-header>
        <div class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left">
          <div class="text-lg font-semibold tracking-tighter">{{ editingProduct ? "Edit Product" : "Add Product" }}</div>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            {{ editingProduct ? "Update the product details below." : "Fill in the details to create a new product." }}
          </p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <EventFormEventProduct
            :product="editingProduct"
            :api-base="apiBase"
            @success="onFormSuccess"
          />
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Confirmation Dialog -->
    <DialogResponsive v-model:open="showDeleteDialog" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Product</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete
            <span class="font-medium text-foreground">{{ deletingProduct?.name }}</span>?
            This action cannot be undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              :disabled="deleteLoading"
              @click="showDeleteDialog = false"
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="deleteLoading"
              @click="handleDelete"
              class="bg-destructive hover:bg-destructive/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="deleteLoading" class="size-4 text-white" />
              <span>{{ deleteLoading ? "Deleting..." : "Delete" }}</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import EventProductImportDialog from "@/components/event/EventProductImportDialog.vue";
import { h, resolveComponent } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();

// State
const products = ref([]);
const categories = ref([]);
const loading = ref(true);
const selectedCategory = ref("all");
const showFormDialog = ref(false);
const editingProduct = ref(null);
const showDeleteDialog = ref(false);
const deletingProduct = ref(null);
const deleteLoading = ref(false);
const togglingId = ref(null);
const tableRef = ref();

// Mirror TableData's client-side free-text search so the server-side export
// can apply the same filter as the displayed table.
const activeSearch = ref("");
const onColumnFiltersChange = (filters) => {
  const nameFilter = (filters || []).find((f) => f.id === "name");
  activeSearch.value = nameFilter?.value ?? "";
};

// Computed
const apiBase = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/products`
);

// Category filter is applied here (page-level) so TableData receives a list
// pre-filtered by category. The free-text search is handled by TableData via
// the "name" column's custom filterFn (matches name / category / description).
const categoryFilteredProducts = computed(() => {
  if (selectedCategory.value && selectedCategory.value !== "all") {
    return products.value.filter(
      (p) => String(p.category_id) === selectedCategory.value
    );
  }
  return products.value;
});

// clientOnly TableData still requires a meta prop.
const meta = computed(() => ({
  current_page: 1,
  last_page: 1,
  per_page: categoryFilteredProducts.value.length || 10,
  total: categoryFilteredProducts.value.length,
}));

// Booth type label map
const boothTypeLabels = {
  raw_space: "Raw Space",
  standard_shell_scheme: "Standard Shell Scheme",
  enhanced_shell_scheme: "Enhanced Shell Scheme",
  table_chair_only: "Table & Chair Only",
};

function boothTypeLabel(type) {
  return boothTypeLabels[type] || type;
}

// Indonesian Rupiah formatter
function formatPrice(price) {
  if (price === null || price === undefined) return "—";
  return (
    "Rp" +
    Number(price)
      .toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 })
  );
}

// Table columns
const columns = computed(() => {
  const cols = [
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
      header: "Name",
      accessorKey: "name",
      cell: ({ row }) => {
        const product = row.original;
        return h("div", {}, [
          h("div", { class: "font-medium tracking-tight" }, product.name),
          product.description
            ? h(
                "div",
                {
                  class:
                    "text-muted-foreground mt-0.5 line-clamp-1 text-xs sm:text-sm tracking-tight",
                },
                product.description
              )
            : null,
        ]);
      },
      size: 280,
      enableHiding: false,
      filterFn: (row, columnId, filterValue) => {
        const q = String(filterValue || "").toLowerCase();
        if (!q) return true;
        const p = row.original;
        // Coerce to a strict boolean: optional chaining on null fields yields
        // `undefined`, which TanStack does not treat as "exclude".
        return Boolean(
          p.name?.toLowerCase().includes(q) ||
            p.category?.toLowerCase().includes(q) ||
            p.description?.toLowerCase().includes(q)
        );
      },
    },
    {
      header: "Category",
      accessorKey: "category",
      cell: ({ row }) => {
        const category = row.original.category;
        if (!category) {
          return h("span", { class: "text-muted-foreground" }, "—");
        }
        return h(
          Badge,
          { variant: "outline", class: "font-normal" },
          { default: () => category }
        );
      },
      size: 160,
    },
    {
      header: "Price",
      accessorKey: "price",
      cell: ({ row }) => {
        const price = row.original.price;
        if (price === null || price === undefined) {
          return h("span", { class: "text-muted-foreground" }, "—");
        }
        return h(
          "span",
          { class: "font-medium tracking-tight tabular-nums whitespace-nowrap" },
          formatPrice(price)
        );
      },
      size: 140,
    },
    {
      header: "Unit",
      accessorKey: "unit",
      cell: ({ row }) => {
        const unit = row.original.unit;
        if (!unit) {
          return h("span", { class: "text-muted-foreground" }, "—");
        }
        return h(
          "span",
          { class: "text-muted-foreground text-xs sm:text-sm tracking-tight" },
          unit
        );
      },
      size: 110,
    },
    {
      header: "Booth Types",
      accessorKey: "booth_types",
      enableSorting: false,
      cell: ({ row }) => {
        const types = row.original.booth_types;
        if (!types || !types.length) {
          return h("span", { class: "text-muted-foreground" }, "—");
        }
        return h(
          "div",
          { class: "flex flex-wrap gap-1" },
          types.map((type) =>
            h(
              Badge,
              { key: type, variant: "muted", class: "font-normal" },
              { default: () => boothTypeLabel(type) }
            )
          )
        );
      },
      size: 200,
    },
    {
      header: "Active",
      accessorKey: "is_active",
      cell: ({ row }) => {
        const product = row.original;
        return h(Switch, {
          modelValue: product.is_active,
          disabled: togglingId.value === product.id || !props.event?.can_edit,
          "onUpdate:modelValue": () => handleToggleActive(product),
        });
      },
      size: 90,
    },
  ];

  if (props.event?.can_edit) {
    cols.push({
      id: "actions",
      header: () => h("span", { class: "sr-only" }, "Actions"),
      cell: ({ row }) => {
        const product = row.original;
        const Icon = resolveComponent("Icon");
        return h("div", { class: "flex items-center justify-end gap-x-1" }, [
          h(
            "button",
            {
              type: "button",
              title: "Edit",
              class:
                "text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition",
              onClick: () => openEdit(product),
            },
            [h(Icon, { name: "hugeicons:edit-02", class: "size-4" })]
          ),
          h(
            "button",
            {
              type: "button",
              title: "Delete",
              class:
                "text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md p-1.5 transition",
              onClick: () => confirmDelete(product),
            },
            [h(Icon, { name: "hugeicons:delete-02", class: "size-4" })]
          ),
        ]);
      },
      size: 80,
      enableSorting: false,
      enableHiding: false,
    });
  }

  return cols;
});

// Methods
async function fetchProducts() {
  loading.value = true;
  try {
    const res = await client(apiBase.value);
    products.value = res.data || [];
  } catch (err) {
    toast.error("Failed to load products", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    loading.value = false;
  }
}

async function fetchCategories() {
  try {
    const res = await client(`${apiBase.value}/categories`);
    categories.value = res.data || [];
  } catch {
    // non-critical
  }
}

async function handleToggleActive(product) {
  togglingId.value = product.id;
  try {
    await client(`${apiBase.value}/${product.id}`, {
      method: "PUT",
      body: { is_active: !product.is_active },
    });
    product.is_active = !product.is_active;
    toast.success(`Product ${product.is_active ? "activated" : "deactivated"}`);
  } catch (err) {
    toast.error("Failed to update product", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    togglingId.value = null;
  }
}

function confirmDelete(product) {
  deletingProduct.value = product;
  showDeleteDialog.value = true;
}

async function handleDelete() {
  if (!deletingProduct.value) return;

  deleteLoading.value = true;
  try {
    await client(`${apiBase.value}/${deletingProduct.value.id}`, {
      method: "DELETE",
    });
    products.value = products.value.filter((p) => p.id !== deletingProduct.value.id);
    showDeleteDialog.value = false;
    deletingProduct.value = null;
    toast.success("Product deleted successfully");
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to delete product", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deleteLoading.value = false;
  }
}

// Bulk delete handlers
const bulkDeleteDialogOpen = ref(false);
const bulkDeleting = ref(false);

async function handleDeleteRows(selectedRows) {
  const ids = selectedRows.map((row) => row.original.id);
  bulkDeleting.value = true;
  try {
    await Promise.all(
      ids.map((id) => client(`${apiBase.value}/${id}`, { method: "DELETE" }))
    );
    await fetchProducts();
    await fetchCategories();
    bulkDeleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(`${ids.length} product(s) deleted successfully`);
  } catch (err) {
    toast.error("Failed to delete product(s)", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkDeleting.value = false;
  }
}

function openCreate() {
  editingProduct.value = null;
  showFormDialog.value = true;
}

function openEdit(product) {
  editingProduct.value = product;
  showFormDialog.value = true;
}

// Export
const exportPending = ref(false);

const handleExport = async () => {
  try {
    exportPending.value = true;

    const params = new URLSearchParams();

    if (selectedCategory.value && selectedCategory.value !== "all") {
      params.append("filter_category_id", selectedCategory.value);
    }

    if (activeSearch.value) {
      params.append("filter_search", activeSearch.value);
    }

    const response = await client(
      `${apiBase.value}/export?${params.toString()}`,
      { responseType: "blob" }
    );

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `products_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Products exported successfully");
  } catch (err) {
    console.error("Failed to export products:", err);
    toast.error("Failed to export products", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

async function onFormSuccess() {
  showFormDialog.value = false;
  await fetchProducts();
  await fetchCategories();
}

async function onImported() {
  await fetchProducts();
  await fetchCategories();
}

onMounted(() => {
  fetchProducts();
  fetchCategories();
});

usePageMeta(null, {
  title: computed(
    () => `Products · ${props.event?.title || route.params.eventSlug}`
  ),
});
</script>
