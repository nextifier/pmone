<template>
  <div class="mx-auto flex w-full max-w-4xl flex-col gap-y-8">
    <!-- Order Form Settings -->
    <form @submit.prevent="handleSubmit" class="space-y-8">
      <!-- Order Form Settings -->
      <div class="space-y-4">
        <div class="space-y-1">
          <h3 class="text-lg font-semibold tracking-tight">Order Form Settings</h3>
          <p class="text-muted-foreground text-sm tracking-tight">
            Configure order form for exhibitors: T&C content, tax rate, and notification emails.
          </p>
        </div>

        <div class="space-y-2">
          <Label for="order_form_content">Terms & Conditions / Important Information</Label>
          <TipTapEditor
            v-model="form.order_form_content"
            model-type="App\Models\Event"
            collection="description_images"
            :sticky="false"
            min-height="150px"
            placeholder="Write terms & conditions or important information for exhibitors..."
          />
        </div>

        <div class="space-y-2">
          <Label for="notification_emails">Notification Emails</Label>
          <div class="space-y-2">
            <div
              v-for="(email, index) in notificationEmails"
              :key="index"
              class="flex items-center gap-x-2"
            >
              <Input
                v-model="notificationEmails[index]"
                type="email"
                placeholder="email@example.com"
                class="flex-1"
              />
              <button
                v-if="notificationEmails.length > 1"
                type="button"
                @click="notificationEmails.splice(index, 1)"
                class="text-muted-foreground hover:text-destructive shrink-0"
              >
                <Icon name="hugeicons:delete-02" class="size-4" />
              </button>
            </div>

            <div class="flex w-full items-center justify-between gap-2">
              <p class="text-muted-foreground text-xs tracking-tight">
                Order notifications will be sent to these emails.
              </p>
              <button
                type="button"
                @click="notificationEmails.push('')"
                class="hover:bg-border bg-muted flex items-center gap-x-1 rounded-md py-1 pr-2 pl-1 text-xs tracking-tight sm:text-sm"
              >
                <Icon name="hugeicons:add-01" class="size-3.5" />
                Add Email
              </button>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="order_form_deadline">Order Form Deadline</Label>
            <DateTimePicker
              v-model="form.order_form_deadline"
              placeholder="No deadline"
              :default-hour="23"
              :default-minute="59"
            />
            <p class="text-muted-foreground text-xs">
              Exhibitors cannot submit orders after this date.
            </p>
            <InputErrorMessage :errors="errors.order_form_deadline" />
          </div>
          <div class="space-y-2">
            <Label for="promotion_post_deadline">Promotion Post Deadline</Label>
            <DateTimePicker
              v-model="form.promotion_post_deadline"
              placeholder="No deadline"
              :default-hour="23"
              :default-minute="59"
            />
            <p class="text-muted-foreground text-xs">
              Exhibitors cannot upload promotion posts after this date.
            </p>
            <InputErrorMessage :errors="errors.promotion_post_deadline" />
          </div>
        </div>
      </div>

      <!-- Order Periods -->
      <div class="space-y-4">
        <div class="space-y-1">
          <Label class="text-base font-semibold">Order Periods</Label>
          <p class="text-muted-foreground text-xs">
            Configure normal and onsite order periods. Onsite orders can have a penalty rate
            applied.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="normal_order_opens_at">Normal Order Opens</Label>
            <DateTimePicker
              v-model="form.normal_order_opens_at"
              placeholder="Not set"
              :default-hour="0"
              :default-minute="0"
            />
            <InputErrorMessage :errors="errors.normal_order_opens_at" />
          </div>
          <div class="space-y-2">
            <Label for="normal_order_closes_at">Normal Order Closes</Label>
            <DateTimePicker
              v-model="form.normal_order_closes_at"
              placeholder="Not set"
              :default-hour="23"
              :default-minute="59"
            />
            <InputErrorMessage :errors="errors.normal_order_closes_at" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="onsite_order_opens_at">Onsite Order Opens</Label>
            <DateTimePicker
              v-model="form.onsite_order_opens_at"
              placeholder="Not set"
              :default-hour="0"
              :default-minute="0"
            />
            <InputErrorMessage :errors="errors.onsite_order_opens_at" />
          </div>
          <div class="space-y-2">
            <Label for="onsite_order_closes_at">Onsite Order Closes</Label>
            <DateTimePicker
              v-model="form.onsite_order_closes_at"
              placeholder="Not set"
              :default-hour="23"
              :default-minute="59"
            />
            <InputErrorMessage :errors="errors.onsite_order_closes_at" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-x-2 gap-y-6">
          <div class="space-y-2">
            <Label for="settings_tax_rate">Tax Rate (%)</Label>
            <Input
              id="settings_tax_rate"
              v-model.number="form.settings.tax_rate"
              type="number"
              min="0"
              max="100"
              step="1"
              placeholder="11"
            />
            <p class="text-muted-foreground text-xs">Default: 11% (PPN)</p>
          </div>

          <div class="space-y-2">
            <Label for="onsite_penalty_rate">Onsite Penalty Rate (%)</Label>
            <Input
              id="onsite_penalty_rate"
              v-model.number="form.onsite_penalty_rate"
              type="number"
              min="0"
              max="100"
              step="1"
              placeholder="50"
            />
            <p class="text-muted-foreground text-xs">
              Percentage added to order total for onsite period orders. Default: 50%.
            </p>
            <InputErrorMessage :errors="errors.onsite_penalty_rate" />
          </div>
        </div>
      </div>

      <!-- Badge & VIP Info -->
      <div class="space-y-4">
        <div class="space-y-1">
          <Label class="text-base font-semibold">Badge & VIP Information</Label>
          <p class="text-muted-foreground text-xs">
            Information about exhibitor badges, VIP passes, and related policies.
          </p>
        </div>

        <div class="space-y-2">
          <Label for="badge_vip_info">Badge & VIP Info Content</Label>
          <TipTapEditor
            v-model="form.badge_vip_info"
            model-type="App\Models\Event"
            collection="description_images"
            :sticky="false"
            min-height="150px"
            placeholder="Write badge and VIP information for exhibitors..."
          />
          <InputErrorMessage :errors="errors.badge_vip_info" />
        </div>
      </div>

      <div class="flex justify-end">
        <Button type="submit" :disabled="saving">
          <Spinner v-if="saving" />
          {{ saving ? "Saving.." : "Save Settings" }}
          <KbdGroup>
            <Kbd>{{ metaSymbol }}</Kbd>
            <Kbd>S</Kbd>
          </KbdGroup>
        </Button>
      </div>
    </form>

    <!-- Divider -->
    <div class="border-border border-t" />

    <!-- Ops Documents -->
    <div class="flex flex-col gap-y-6">
      <!-- Section Header -->
      <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
        <div class="space-y-1">
          <h3 class="text-lg font-semibold tracking-tight">Ops Documents</h3>
          <p class="text-muted-foreground text-sm tracking-tight">
            Manage event rules, required documents, and forms for exhibitors.
          </p>
        </div>

        <Button @click="openCreateDoc" size="sm">
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
          <Input v-model="docSearch" placeholder="Search documents..." class="pl-9" />
        </div>

        <Select v-model="selectedDocType">
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
        <div v-if="docLoading" class="flex items-center justify-center py-16">
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
              {{
                docSearch || selectedDocType !== "all"
                  ? "No documents match your filters"
                  : "No documents yet"
              }}
            </p>
            <p class="text-muted-foreground text-xs">
              {{
                docSearch || selectedDocType !== "all"
                  ? "Try adjusting your search or filters."
                  : "Add event rules, required documents, or forms."
              }}
            </p>
          </div>
          <Button
            v-if="!docSearch && selectedDocType === 'all'"
            @click="openCreateDoc"
            size="sm"
            variant="outline"
          >
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
                    <Icon
                      name="lucide:grip-vertical"
                      class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
                    />
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
                    :name="
                      doc.is_required ? 'hugeicons:checkmark-circle-02' : 'hugeicons:cancel-circle'
                    "
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
                      @click="openEditDoc(doc)"
                      class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                      title="Edit"
                    >
                      <Icon name="hugeicons:edit-02" class="size-4" />
                    </button>
                    <button
                      type="button"
                      @click="confirmDeleteDoc(doc)"
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
    </div>

    <!-- Add/Edit Document Dialog -->
    <DialogResponsive
      v-model:open="showDocFormDialog"
      dialog-max-width="500px"
      :overflow-content="true"
    >
      <template #sticky-header>
        <div
          class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left"
        >
          <div class="text-lg font-semibold tracking-tighter">
            {{ editingDocument ? "Edit Document" : "Add Document" }}
          </div>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            {{
              editingDocument
                ? "Update the document details below."
                : "Fill in the details to create a new document."
            }}
          </p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <EventFormEventDocument
            :document="editingDocument"
            :api-base="docApiBase"
            @success="onDocFormSuccess"
          />
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Document Confirmation Dialog -->
    <DialogResponsive v-model:open="showDocDeleteDialog" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Document</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete
            <span class="text-foreground font-medium">{{ deletingDocument?.title }}</span
            >? All submissions for this document will also be deleted. This action cannot be undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              :disabled="docDeleteLoading"
              @click="showDocDeleteDialog = false"
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="docDeleteLoading"
              @click="handleDeleteDoc"
              class="bg-destructive hover:bg-destructive/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="docDeleteLoading" class="size-4 text-white" />
              <span>{{ docDeleteLoading ? "Deleting..." : "Delete" }}</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DateTimePicker from "@/components/DateTimePicker.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import TipTapEditor from "@/components/TipTapEditor.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const client = useSanctumClient();
const { metaSymbol } = useShortcuts();

// ==============================
// Order Form Settings
// ==============================
const saving = ref(false);
const errors = ref({});
const notificationEmails = ref([]);

const form = reactive({
  order_form_content: "",
  order_form_deadline: null,
  promotion_post_deadline: null,
  normal_order_opens_at: null,
  normal_order_closes_at: null,
  onsite_order_opens_at: null,
  onsite_order_closes_at: null,
  onsite_penalty_rate: 50,
  badge_vip_info: "",
  settings: {},
});

function populateForm(data) {
  if (!data) return;
  form.order_form_content = data.order_form_content || "";
  form.order_form_deadline = data.order_form_deadline ? new Date(data.order_form_deadline) : null;
  form.promotion_post_deadline = data.promotion_post_deadline
    ? new Date(data.promotion_post_deadline)
    : null;
  form.normal_order_opens_at = data.normal_order_opens_at
    ? new Date(data.normal_order_opens_at)
    : null;
  form.normal_order_closes_at = data.normal_order_closes_at
    ? new Date(data.normal_order_closes_at)
    : null;
  form.onsite_order_opens_at = data.onsite_order_opens_at
    ? new Date(data.onsite_order_opens_at)
    : null;
  form.onsite_order_closes_at = data.onsite_order_closes_at
    ? new Date(data.onsite_order_closes_at)
    : null;
  form.onsite_penalty_rate =
    data.onsite_penalty_rate != null ? Math.round(data.onsite_penalty_rate) : 50;
  form.badge_vip_info = data.badge_vip_info || "";
  form.settings = data.settings || {};
  const emails = data.settings?.notification_emails || [];
  notificationEmails.value = emails.length > 0 ? emails : [""];
}

watch(
  () => props.event,
  (val) => populateForm(val),
  { immediate: true }
);

function formatDateTimeForBackend(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

async function handleSubmit() {
  saving.value = true;
  errors.value = {};

  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}`, {
      method: "PUT",
      body: {
        order_form_content: form.order_form_content || null,
        order_form_deadline: formatDateTimeForBackend(form.order_form_deadline),
        promotion_post_deadline: formatDateTimeForBackend(form.promotion_post_deadline),
        normal_order_opens_at: formatDateTimeForBackend(form.normal_order_opens_at),
        normal_order_closes_at: formatDateTimeForBackend(form.normal_order_closes_at),
        onsite_order_opens_at: formatDateTimeForBackend(form.onsite_order_opens_at),
        onsite_order_closes_at: formatDateTimeForBackend(form.onsite_order_closes_at),
        onsite_penalty_rate: form.onsite_penalty_rate ?? 50,
        badge_vip_info: form.badge_vip_info || null,
        settings: {
          ...form.settings,
          notification_emails: notificationEmails.value.filter((e) => e.trim()),
        },
      },
    });

    toast.success("Order form settings saved");
    await refreshNuxtData(`event-${route.params.username}-${route.params.eventSlug}`);
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to save settings");
    }
  } finally {
    saving.value = false;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});

// ==============================
// Ops Documents
// ==============================
const documents = ref([]);
const docLoading = ref(true);
const docSearch = ref("");
const selectedDocType = ref("all");
const showDocFormDialog = ref(false);
const editingDocument = ref(null);
const showDocDeleteDialog = ref(false);
const deletingDocument = ref(null);
const docDeleteLoading = ref(false);

const docApiBase = computed(
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
        await client(`${docApiBase.value}/reorder`, {
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

  if (docSearch.value) {
    const q = docSearch.value.toLowerCase();
    result = result.filter((d) => d.title?.toLowerCase().includes(q));
  }

  if (selectedDocType.value && selectedDocType.value !== "all") {
    result = result.filter((d) => d.document_type === selectedDocType.value);
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
  docLoading.value = true;
  try {
    const res = await client(docApiBase.value);
    documents.value = res.data || [];
  } catch (err) {
    toast.error("Failed to load documents", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    docLoading.value = false;
  }
}

function openCreateDoc() {
  editingDocument.value = null;
  showDocFormDialog.value = true;
}

function openEditDoc(doc) {
  editingDocument.value = doc;
  showDocFormDialog.value = true;
}

function confirmDeleteDoc(doc) {
  deletingDocument.value = doc;
  showDocDeleteDialog.value = true;
}

async function handleDeleteDoc() {
  if (!deletingDocument.value) return;

  docDeleteLoading.value = true;
  try {
    await client(`${docApiBase.value}/${deletingDocument.value.ulid}`, {
      method: "DELETE",
    });
    documents.value = documents.value.filter((d) => d.id !== deletingDocument.value.id);
    showDocDeleteDialog.value = false;
    deletingDocument.value = null;
    toast.success("Document deleted successfully");
  } catch (err) {
    toast.error("Failed to delete document", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    docDeleteLoading.value = false;
  }
}

async function onDocFormSuccess() {
  showDocFormDialog.value = false;
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
  title: computed(() => `Order Form Settings · ${props.event?.title || route.params.eventSlug}`),
});
</script>
