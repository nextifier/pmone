<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title">Business Categories</div>
      <div class="frame-description">
        Define predefined business categories that brands can select from for this project. Drag to reorder.
      </div>
    </div>
    <div class="frame-panel">
      <div class="mb-4 flex flex-wrap justify-end gap-2">
        <ProjectBusinessCategoriesImportDialog
          :project-username="projectUsername"
          @imported="fetchCategories"
        >
          <template #trigger="{ open }">
            <Button variant="outline" size="sm" @click="open()">
              <Icon name="hugeicons:file-import" class="-ml-1 size-4 shrink-0" />
              Import
            </Button>
          </template>
        </ProjectBusinessCategoriesImportDialog>
        <Button
          variant="outline"
          size="sm"
          :disabled="exportPending || !categories.length"
          @click="handleExport"
        >
          <Spinner v-if="exportPending" class="-ml-1 size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="-ml-1 size-4 shrink-0" />
          Export
        </Button>
        <Button size="sm" @click="openCreateDialog">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          Add category
        </Button>
      </div>

      <div v-if="loading" class="flex justify-center py-6">
        <Spinner class="size-5" />
      </div>

      <div
        v-else-if="!categories.length"
        class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
      >
        No business categories defined yet.
      </div>

      <div v-else ref="sortableEl" class="space-y-2">
        <div
          v-for="category in categories"
          :key="category.id"
          :data-item-id="category.id"
          class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
        >
          <Icon
            name="lucide:grip-vertical"
            class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
          />

          <div class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium tracking-tight">{{ category.name }}</span>
          </div>

          <div class="flex shrink-0 items-center gap-1">
            <Button variant="ghost" size="iconSm" v-tippy="'Edit'" @click="openEditDialog(category)">
              <Icon name="hugeicons:edit-02" class="size-4" />
            </Button>
            <Button
              variant="ghost"
              size="iconSm"
              class="hover:bg-destructive/10 text-destructive"
              v-tippy="'Delete'"
              @click="confirmDelete(category)"
            >
              <Icon name="hugeicons:delete-02" class="size-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
            {{ editing ? "Edit Category" : "Add Category" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label for="business-category-name">Name</Label>
              <Input
                id="business-category-name"
                v-model="form.name"
                required
                placeholder="e.g. Foundation Models"
              />
              <FieldError :errors="errors.name" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
                {{ editing ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">Delete category?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.name || "This category" }}" will be removed from this project. Brands
            that selected it will no longer show it.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Delete" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { FieldError } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { computed, reactive, ref } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
});

const client = useSanctumClient();

const apiBase = computed(() => `/api/projects/${props.projectUsername}/business-categories`);

const sortableEl = ref(null);
const loading = ref(true);
const categories = ref([]);
const exportPending = ref(false);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive({ name: "" });

const resetForm = () => {
  form.name = "";
  errors.value = {};
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (category) => {
  editing.value = category;
  errors.value = {};
  form.name = category.name ?? "";
  dialogOpen.value = true;
};

async function fetchCategories() {
  loading.value = true;
  try {
    const res = await client(apiBase.value);
    categories.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load business categories:", e);
  } finally {
    loading.value = false;
  }
}

const handleSubmit = async () => {
  if (!form.name.trim()) {
    toast.error("Name is required");
    return;
  }

  saving.value = true;
  errors.value = {};
  try {
    const body = { name: form.name.trim() };

    if (editing.value) {
      await client(`${apiBase.value}/${editing.value.id}`, { method: "PUT", body });
      toast.success("Business category updated");
    } else {
      await client(apiBase.value, { method: "POST", body });
      toast.success("Business category added");
    }
    dialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error(err?.data?.message || "Failed to save business category");
  } finally {
    saving.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);
const deleting = ref(false);

const confirmDelete = (category) => {
  deletingItem.value = category;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${apiBase.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Business category deleted");
    deleteDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error(err?.data?.message || "Failed to delete business category");
  } finally {
    deleting.value = false;
  }
};

async function handleExport() {
  try {
    exportPending.value = true;

    const response = await client(`${apiBase.value}/export`, { responseType: "blob" });

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

// --- Drag reorder ---
useSortableList(sortableEl, categories, {
  onReorder: async () => {
    const orders = categories.value.map((c, idx) => ({ id: c.id, order: idx + 1 }));
    try {
      await client(`${apiBase.value}/reorder`, { method: "PUT", body: { orders } });
      categories.value.forEach((c, idx) => (c.order_column = idx + 1));
    } catch (e) {
      toast.error("Failed to reorder categories");
      await fetchCategories();
    }
  },
});

onMounted(fetchCategories);
</script>
