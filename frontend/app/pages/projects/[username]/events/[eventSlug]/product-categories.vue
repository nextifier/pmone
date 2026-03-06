<template>
  <div class="flex flex-col gap-y-6">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="space-y-1">
        <NuxtLink
          :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/products`"
          class="text-muted-foreground hover:text-foreground mb-1 flex items-center gap-x-1 text-sm tracking-tight transition"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-4" />
          <span>Back to Products</span>
        </NuxtLink>
        <h3 class="text-lg font-semibold tracking-tight">Product Categories</h3>
        <p class="text-muted-foreground text-sm tracking-tight">
          Manage product categories for this event's order form.
        </p>
      </div>

      <Button @click="openCreate" size="sm">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Product Category
      </Button>
    </div>

    <!-- Table -->
    <div class="frame overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-muted-foreground text-sm">Loading categories...</span>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="categories.length === 0"
        class="flex flex-col items-center justify-center gap-y-3 py-16 text-center"
      >
        <div
          class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
        >
          <div class="translate-y-1.5 -rotate-6">
            <Icon name="hugeicons:folder-01" />
          </div>
          <div>
            <Icon name="hugeicons:layers-01" />
          </div>
          <div class="translate-y-1.5 rotate-6">
            <Icon name="hugeicons:tag-01" />
          </div>
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium">No product categories yet</p>
          <p class="text-muted-foreground text-xs">Add categories to organize your event products.</p>
        </div>
        <Button @click="openCreate" size="sm" variant="outline">
          <Icon name="hugeicons:add-01" class="size-4" />
          Add Category
        </Button>
      </div>

      <!-- Data Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left">
              <th class="text-muted-foreground w-10 px-4 py-3 font-medium">#</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Title</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Slug</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Products</th>
              <th class="text-muted-foreground px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody ref="sortableRef">
            <tr
              v-for="(category, index) in categories"
              :key="category.id"
              :data-id="category.id"
              class="border-b last:border-0"
            >
              <td class="text-muted-foreground px-4 py-3">
                <div class="flex items-center gap-x-1">
                  <Icon name="lucide:grip-vertical" class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab" />
                  <span>{{ index + 1 }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium tracking-tight">{{ category.title }}</div>
                <div v-if="category.description" class="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                  {{ stripHtml(category.description) }}
                </div>
              </td>
              <td class="px-4 py-3">
                <code class="text-muted-foreground text-xs">{{ category.slug }}</code>
              </td>
              <td class="px-4 py-3">
                <Badge variant="secondary" class="font-normal">
                  {{ category.products?.length || 0 }} products
                </Badge>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-x-1">
                  <button
                    type="button"
                    @click="openEdit(category)"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                    title="Edit"
                  >
                    <Icon name="hugeicons:edit-02" class="size-4" />
                  </button>
                  <button
                    type="button"
                    @click="confirmDelete(category)"
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
    <DialogResponsive v-model:open="showFormDialog" dialog-max-width="500px" :overflow-content="true">
      <template #sticky-header>
        <div class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left">
          <div class="text-lg font-semibold tracking-tighter">{{ editingCategory ? "Edit Category" : "Add Category" }}</div>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            {{ editingCategory ? "Update the category details below." : "Fill in the details to create a new category." }}
          </p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <EventFormEventProductCategory
            :category="editingCategory"
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
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Category</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete
            <span class="text-foreground font-medium">{{ deletingCategory?.title }}</span>?
            Products in this category will not be deleted, but will lose their category assignment.
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
import DialogResponsive from "@/components/DialogResponsive.vue";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();

const categories = ref([]);
const loading = ref(true);
const showFormDialog = ref(false);
const editingCategory = ref(null);
const showDeleteDialog = ref(false);
const deletingCategory = ref(null);
const deleteLoading = ref(false);

const apiBase = computed(
  () => `/api/projects/${route.params.username}/events/${route.params.eventSlug}/product-categories`
);

const sortableRef = ref(null);

function initSortable() {
  if (!sortableRef.value) return;
  useSortable(sortableRef.value, categories, {
    handle: ".drag-handle",
    animation: 200,
    ghostClass: "sortable-ghost",
    chosenClass: "sortable-chosen",
    onEnd: async () => {
      await nextTick();
      const orders = categories.value.map((cat, index) => ({
        id: cat.id,
        order: index + 1,
      }));

      try {
        await client(`${apiBase.value}/reorder`, {
          method: "POST",
          body: { orders },
        });
      } catch {
        toast.error("Failed to reorder categories");
        await fetchCategories();
      }
    },
  });
}

function stripHtml(html) {
  if (!html) return "";
  return html.replace(/<[^>]*>/g, "").substring(0, 100);
}

async function fetchCategories() {
  loading.value = true;
  try {
    const res = await client(apiBase.value);
    categories.value = res.data || [];
  } catch (err) {
    toast.error("Failed to load categories", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingCategory.value = null;
  showFormDialog.value = true;
}

function openEdit(category) {
  editingCategory.value = category;
  showFormDialog.value = true;
}

function confirmDelete(category) {
  deletingCategory.value = category;
  showDeleteDialog.value = true;
}

async function handleDelete() {
  if (!deletingCategory.value) return;

  deleteLoading.value = true;
  try {
    await client(`${apiBase.value}/${deletingCategory.value.id}`, {
      method: "DELETE",
    });
    categories.value = categories.value.filter((c) => c.id !== deletingCategory.value.id);
    showDeleteDialog.value = false;
    deletingCategory.value = null;
    toast.success("Category deleted successfully");
  } catch (err) {
    toast.error("Failed to delete category", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deleteLoading.value = false;
  }
}

async function onFormSuccess() {
  showFormDialog.value = false;
  await fetchCategories();
}

onMounted(async () => {
  await fetchCategories();
  await nextTick();
  initSortable();
});

watch(
  () => categories.value.length,
  async () => {
    await nextTick();
    initSortable();
  }
);

usePageMeta(null, {
  title: computed(
    () => `Product Categories · ${props.event?.title || route.params.eventSlug}`
  ),
});
</script>
