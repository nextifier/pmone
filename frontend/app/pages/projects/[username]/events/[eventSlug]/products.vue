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

      <Button @click="openCreate" size="sm" class="ml-auto shrink-0">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Product
      </Button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-2">
      <div class="relative min-w-48 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input
          v-model="search"
          placeholder="Search products..."
          class="pl-9"
        />
      </div>

      <Select v-model="selectedCategory">
        <SelectTrigger class="w-48 shrink-0">
          <SelectValue placeholder="All categories" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All categories</SelectItem>
          <SelectItem v-for="cat in categories" :key="cat" :value="cat">
            {{ cat }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Table -->
    <div class="frame overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-muted-foreground text-sm">Loading products...</span>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="!loading && filteredProducts.length === 0"
        class="flex flex-col items-center justify-center gap-y-3 py-16 text-center"
      >
        <div
          class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
        >
          <div class="translate-y-1.5 -rotate-6">
            <Icon name="hugeicons:shopping-cart-01" />
          </div>
          <div>
            <Icon name="hugeicons:package-01" />
          </div>
          <div class="translate-y-1.5 rotate-6">
            <Icon name="hugeicons:tag-01" />
          </div>
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium">
            {{ search || selectedCategory !== "all" ? "No products match your filters" : "No products yet" }}
          </p>
          <p class="text-muted-foreground text-xs">
            {{ search || selectedCategory !== "all" ? "Try adjusting your search or filters." : "Add your first product to get started." }}
          </p>
        </div>
        <Button v-if="!search && selectedCategory === 'all'" @click="openCreate" size="sm" variant="outline">
          <Icon name="hugeicons:add-01" class="size-4" />
          Add Product
        </Button>
      </div>

      <!-- Data Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left">
              <th class="text-muted-foreground px-4 py-3 font-medium">Name</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Category</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Price</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Unit</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Booth Types</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Active</th>
              <th class="text-muted-foreground px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="product in filteredProducts"
              :key="product.id"
              class="border-b last:border-0"
            >
              <!-- Name -->
              <td class="px-4 py-3">
                <div class="font-medium tracking-tight">{{ product.name }}</div>
                <div v-if="product.description" class="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                  {{ product.description }}
                </div>
              </td>

              <!-- Category -->
              <td class="px-4 py-3">
                <Badge v-if="product.category" variant="outline" class="font-normal">
                  {{ product.category }}
                </Badge>
                <span v-else class="text-muted-foreground">—</span>
              </td>

              <!-- Price -->
              <td class="px-4 py-3 whitespace-nowrap">
                <span v-if="product.price !== null" class="font-medium">
                  {{ formatPrice(product.price) }}
                </span>
                <span v-else class="text-muted-foreground">—</span>
              </td>

              <!-- Unit -->
              <td class="px-4 py-3">
                <span v-if="product.unit" class="text-muted-foreground text-xs">
                  {{ product.unit }}
                </span>
                <span v-else class="text-muted-foreground">—</span>
              </td>

              <!-- Booth Types -->
              <td class="px-4 py-3">
                <div v-if="product.booth_types && product.booth_types.length" class="flex flex-wrap gap-1">
                  <Badge
                    v-for="type in product.booth_types"
                    :key="type"
                    variant="secondary"
                    class="text-xs font-normal"
                  >
                    {{ boothTypeLabel(type) }}
                  </Badge>
                </div>
                <span v-else class="text-muted-foreground">—</span>
              </td>

              <!-- Active Toggle -->
              <td class="px-4 py-3">
                <Switch
                  :model-value="product.is_active"
                  :disabled="togglingId === product.id"
                  @update:model-value="handleToggleActive(product)"
                />
              </td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-x-1">
                  <button
                    type="button"
                    @click="openEdit(product)"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                    title="Edit"
                  >
                    <Icon name="hugeicons:edit-02" class="size-4" />
                  </button>
                  <button
                    type="button"
                    @click="confirmDelete(product)"
                    class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md p-1.5 transition"
                    title="Delete"
                  >
                    <Icon name="hugeicons:delete-02" class="size-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Dialog -->
    <Dialog v-model:open="showFormDialog">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ editingProduct ? "Edit Product" : "Add Product" }}</DialogTitle>
          <DialogDescription>
            {{ editingProduct ? "Update the product details below." : "Fill in the details to create a new product." }}
          </DialogDescription>
        </DialogHeader>
        <EventFormEventProduct
          :product="editingProduct"
          :api-base="apiBase"
          :category-suggestions="categories"
          @success="onFormSuccess"
        />
      </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent class="max-w-sm">
        <DialogHeader>
          <DialogTitle>Delete Product</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete
            <span class="font-medium text-foreground">{{ deletingProduct?.name }}</span>?
            This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
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
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();

// State
const products = ref([]);
const categories = ref([]);
const loading = ref(true);
const search = ref("");
const selectedCategory = ref("all");
const showFormDialog = ref(false);
const editingProduct = ref(null);
const showDeleteDialog = ref(false);
const deletingProduct = ref(null);
const deleteLoading = ref(false);
const togglingId = ref(null);

// Computed
const apiBase = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/products`
);

const filteredProducts = computed(() => {
  let result = products.value;

  if (search.value) {
    const q = search.value.toLowerCase();
    result = result.filter(
      (p) =>
        p.name?.toLowerCase().includes(q) ||
        p.category?.toLowerCase().includes(q) ||
        p.description?.toLowerCase().includes(q)
    );
  }

  if (selectedCategory.value && selectedCategory.value !== "all") {
    result = result.filter((p) => p.category === selectedCategory.value);
  }

  return result;
});

// Booth type label map
const boothTypeLabels = {
  raw_space: "Raw Space",
  standard_shell_scheme: "Standard Shell Scheme",
  enhanced_shell_scheme: "Enhanced Shell Scheme",
};

function boothTypeLabel(type) {
  return boothTypeLabels[type] || type;
}

// Indonesian Rupiah formatter
function formatPrice(price) {
  if (price === null || price === undefined) return "—";
  return (
    "Rp " +
    Number(price)
      .toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 })
  );
}

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

function openCreate() {
  editingProduct.value = null;
  showFormDialog.value = true;
}

function openEdit(product) {
  editingProduct.value = product;
  showFormDialog.value = true;
}

async function onFormSuccess() {
  showFormDialog.value = false;
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
