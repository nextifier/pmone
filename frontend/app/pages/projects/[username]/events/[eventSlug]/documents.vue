<template>
  <div class="flex flex-col gap-y-6">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="space-y-1">
        <h3 class="text-lg font-semibold tracking-tight">Ops Documents</h3>
        <p class="text-muted-foreground text-sm tracking-tight">
          Manage event rules, required documents, and forms for exhibitors.
        </p>
      </div>

      <Button @click="openCreate" size="sm">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Document
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
          placeholder="Search documents..."
          class="pl-9"
        />
      </div>

      <Select v-model="selectedType">
        <SelectTrigger class="w-48 shrink-0">
          <SelectValue placeholder="All types" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All types</SelectItem>
          <SelectItem value="checkbox_agreement">Checkbox Agreement</SelectItem>
          <SelectItem value="file_upload">File Upload</SelectItem>
          <SelectItem value="text_input">Text Input</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Table -->
    <div class="frame overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-muted-foreground text-sm">Loading documents...</span>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="filteredDocuments.length === 0"
        class="flex flex-col items-center justify-center gap-y-3 py-16 text-center"
      >
        <div
          class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
        >
          <div class="translate-y-1.5 -rotate-6">
            <Icon name="hugeicons:file-01" />
          </div>
          <div>
            <Icon name="hugeicons:file-01" />
          </div>
          <div class="translate-y-1.5 rotate-6">
            <Icon name="hugeicons:checkmark-square-01" />
          </div>
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium">
            {{ search || selectedType !== "all" ? "No documents match your filters" : "No documents yet" }}
          </p>
          <p class="text-muted-foreground text-xs">
            {{ search || selectedType !== "all" ? "Try adjusting your search or filters." : "Add event rules, required documents, or forms." }}
          </p>
        </div>
        <Button v-if="!search && selectedType === 'all'" @click="openCreate" size="sm" variant="outline">
          <Icon name="hugeicons:add-01" class="size-4" />
          Add Document
        </Button>
      </div>

      <!-- Data Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left">
              <th class="text-muted-foreground w-10 px-4 py-3 font-medium">#</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Title</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Type</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Required</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Blocks</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Version</th>
              <th class="text-muted-foreground px-4 py-3 font-medium">Deadline</th>
              <th class="text-muted-foreground px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody ref="sortableRef">
            <tr
              v-for="(doc, index) in filteredDocuments"
              :key="doc.id"
              :data-id="doc.id"
              class="border-b last:border-0"
            >
              <td class="text-muted-foreground px-4 py-3">
                <div class="flex items-center gap-x-1">
                  <Icon name="lucide:grip-vertical" class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab" />
                  <span>{{ index + 1 }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium tracking-tight">{{ doc.title }}</div>
                <div v-if="doc.booth_types?.length" class="mt-0.5 flex flex-wrap gap-1">
                  <Badge
                    v-for="type in doc.booth_types"
                    :key="type"
                    variant="outline"
                    class="text-[10px] font-normal"
                  >
                    {{ boothTypeLabel(type) }}
                  </Badge>
                </div>
              </td>
              <td class="px-4 py-3">
                <Badge :variant="documentTypeBadgeVariant(doc.document_type)" class="font-normal">
                  {{ documentTypeLabel(doc.document_type) }}
                </Badge>
              </td>
              <td class="px-4 py-3">
                <Icon
                  :name="doc.is_required ? 'hugeicons:checkmark-circle-02' : 'hugeicons:cancel-circle'"
                  :class="doc.is_required ? 'text-green-500' : 'text-muted-foreground'"
                  class="size-4"
                />
              </td>
              <td class="px-4 py-3">
                <Icon
                  :name="doc.blocks_next_step ? 'hugeicons:alert-02' : 'hugeicons:cancel-circle'"
                  :class="doc.blocks_next_step ? 'text-amber-500' : 'text-muted-foreground'"
                  class="size-4"
                />
              </td>
              <td class="px-4 py-3">
                <Badge variant="secondary" class="font-mono text-xs font-normal">
                  v{{ doc.content_version }}
                </Badge>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span v-if="doc.submission_deadline" class="text-xs">
                  {{ formatDate(doc.submission_deadline) }}
                </span>
                <span v-else class="text-muted-foreground text-xs">No deadline</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-x-1">
                  <button
                    type="button"
                    @click="openEdit(doc)"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                    title="Edit"
                  >
                    <Icon name="hugeicons:edit-02" class="size-4" />
                  </button>
                  <button
                    type="button"
                    @click="confirmDelete(doc)"
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
          <div class="text-lg font-semibold tracking-tighter">{{ editingDocument ? "Edit Document" : "Add Document" }}</div>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            {{ editingDocument ? "Update the document details below." : "Fill in the details to create a new document." }}
          </p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <EventFormEventDocument
            :document="editingDocument"
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
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Document</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete
            <span class="text-foreground font-medium">{{ deletingDocument?.title }}</span>?
            All submissions for this document will also be deleted. This action cannot be undone.
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
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();

const documents = ref([]);
const loading = ref(true);
const search = ref("");
const selectedType = ref("all");
const showFormDialog = ref(false);
const editingDocument = ref(null);
const showDeleteDialog = ref(false);
const deletingDocument = ref(null);
const deleteLoading = ref(false);

const apiBase = computed(
  () => `/api/projects/${route.params.username}/events/${route.params.eventSlug}/documents`
);

const sortableRef = ref(null);

function initSortable() {
  if (!sortableRef.value) return;
  useSortable(sortableRef.value, documents, {
    handle: ".drag-handle",
    animation: 200,
    ghostClass: "sortable-ghost",
    chosenClass: "sortable-chosen",
    onEnd: async () => {
      await nextTick();
      const orders = documents.value.map((doc, index) => ({
        id: doc.id,
        order: index + 1,
      }));

      try {
        await client(`${apiBase.value}/reorder`, {
          method: "POST",
          body: { orders },
        });
      } catch {
        toast.error("Failed to reorder documents");
        await fetchDocuments();
      }
    },
  });
}

const filteredDocuments = computed(() => {
  let result = documents.value;

  if (search.value) {
    const q = search.value.toLowerCase();
    result = result.filter((d) => d.title?.toLowerCase().includes(q));
  }

  if (selectedType.value && selectedType.value !== "all") {
    result = result.filter((d) => d.document_type === selectedType.value);
  }

  return result;
});

const documentTypeLabels = {
  checkbox_agreement: "Checkbox",
  file_upload: "File Upload",
  text_input: "Text Input",
};

function documentTypeLabel(type) {
  return documentTypeLabels[type] || type;
}

function documentTypeBadgeVariant(type) {
  if (type === "checkbox_agreement") return "default";
  if (type === "file_upload") return "secondary";
  return "outline";
}

const boothTypeLabels = {
  raw_space: "Raw Space",
  standard_shell_scheme: "Standard Shell",
  enhanced_shell_scheme: "Enhanced Shell",
  table_chair_only: "Table & Chair",
};

function boothTypeLabel(type) {
  return boothTypeLabels[type] || type;
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

async function fetchDocuments() {
  loading.value = true;
  try {
    const res = await client(apiBase.value);
    documents.value = res.data || [];
  } catch (err) {
    toast.error("Failed to load documents", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingDocument.value = null;
  showFormDialog.value = true;
}

function openEdit(doc) {
  editingDocument.value = doc;
  showFormDialog.value = true;
}

function confirmDelete(doc) {
  deletingDocument.value = doc;
  showDeleteDialog.value = true;
}

async function handleDelete() {
  if (!deletingDocument.value) return;

  deleteLoading.value = true;
  try {
    await client(`${apiBase.value}/${deletingDocument.value.ulid}`, {
      method: "DELETE",
    });
    documents.value = documents.value.filter((d) => d.id !== deletingDocument.value.id);
    showDeleteDialog.value = false;
    deletingDocument.value = null;
    toast.success("Document deleted successfully");
  } catch (err) {
    toast.error("Failed to delete document", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deleteLoading.value = false;
  }
}

async function onFormSuccess() {
  showFormDialog.value = false;
  await fetchDocuments();
}

onMounted(async () => {
  await fetchDocuments();
  await nextTick();
  initSortable();
});

watch(
  () => documents.value.length,
  async () => {
    await nextTick();
    initSortable();
  }
);

usePageMeta(null, {
  title: computed(
    () => `Ops Documents · ${props.event?.title || route.params.eventSlug}`
  ),
});
</script>
