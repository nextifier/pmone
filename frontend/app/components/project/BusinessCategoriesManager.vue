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
              @click="deleteCategory(category)"
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

      <!-- Add/Edit form -->
      <div class="border-t pt-4">
        <h5 class="mb-3 text-sm font-medium">
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
            <Button v-if="editing" size="sm" variant="ghost" @click="cancelEdit"> Cancel </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
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

async function deleteCategory(category) {
  if (
    !confirm(
      `Delete business category "${category.name}"? Brands that have this category selected will no longer show it.`
    )
  ) {
    return;
  }

  try {
    await client(`${apiBase.value}/${category.id}`, {
      method: "DELETE",
    });
    toast.success("Business category deleted");
    await fetchCategories();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete business category");
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
