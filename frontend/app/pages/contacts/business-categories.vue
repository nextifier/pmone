<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <BackButton destination="/contacts" :show-label="true" />
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Business Categories</div>
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
                <span class="text-sm font-medium tracking-tight">{{ category.name }}</span>
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
                  @click="deleteCategory(category)"
                  class="text-muted-foreground hover:text-destructive rounded p-1 transition"
                >
                  <Icon name="lucide:trash-2" class="size-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div v-else class="text-muted-foreground mb-6 py-6 text-center text-sm tracking-tight">
            No business categories defined yet.
          </div>

          <!-- Add/Edit form -->
          <div class="border-t pt-4">
            <h5 class="mb-3 text-sm font-medium tracking-tight">
              {{ editing ? "Edit Category" : "Add New Category" }}
            </h5>
            <div class="flex items-end gap-x-3">
              <div class="flex-1 space-y-2">
                <Label for="bc_name">Name</Label>
                <Input
                  id="bc_name"
                  v-model="categoryForm.name"
                  placeholder="e.g. Building Materials"
                  @keydown.enter.prevent="saveCategory"
                />
              </div>

              <div class="flex items-center gap-x-2">
                <Button size="sm" :disabled="!categoryForm.name || saving" @click="saveCategory">
                  <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                  {{ editing ? "Update" : "Add" }}
                </Button>
                <Button v-if="editing" size="sm" variant="ghost" @click="cancelEdit">
                  Cancel
                </Button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
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
const sortableEl = ref(null);
const categories = ref([]);
const loading = ref(true);
const saving = ref(false);
const editing = ref(null);

const apiBase = "/api/contacts-business-categories";

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

    if (editing.value) {
      await client(`${apiBase}/${editing.value}`, {
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

    resetForm();
    await fetchCategories();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to save business category");
  } finally {
    saving.value = false;
  }
}

async function deleteCategory(category) {
  if (!confirm(`Delete business category "${category.name}"?`)) {
    return;
  }

  try {
    await client(`${apiBase}/${category.id}`, {
      method: "DELETE",
    });
    toast.success("Business category deleted");
    await fetchCategories();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete business category");
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
