<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:contact-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Contact List</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex flex-wrap gap-x-1.5 gap-y-2.5">
        <ContactImportDialog v-if="canCreate" @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </ContactImportDialog>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>{{ totalActiveFilters > 0 ? "Export Filtered" : "Export All" }}</span>
        </button>

        <NuxtLink
          to="/contacts/business-categories"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:tag-01" class="size-4 shrink-0" />
          <span>Business Categories</span>
        </NuxtLink>

        <button
          v-if="canDelete"
          @click="openDedupeDialog"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:filter-remove" class="size-4 shrink-0" />
          <span>Remove Duplicates</span>
        </button>

        <NuxtLink
          v-if="canDelete"
          to="/contacts/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
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

    <TableData
      ref="tableRef"
      :client-only="false"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="contacts"
      label="contact"
      search-column="name"
      search-placeholder="Search contacts..."
      error-title="Failed to load contacts"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :initial-column-visibility="{
        emails: false,
        phones: false,
        projects: false,
        source: false,
        created_at: true,
        created_by_name: false,
      }"
      :show-add-button="false"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #add-button>
        <Button v-if="canCreate" size="sm" @click="openCreateDialog">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          Add Contact
          <KbdGroup>
            <Kbd>N</Kbd>
          </KbdGroup>
        </Button>
      </template>

      <template #filters="{ table }">
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
            >
              <Icon name="hugeicons:filter-horizontal" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Filter</span>
              <span
                v-if="totalActiveFilters > 0"
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
              >
                {{ totalActiveFilters }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-48 p-3 pb-4.5" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Active', value: 'active' },
                  { label: 'Inactive', value: 'inactive' },
                  { label: 'Archived', value: 'archived' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
              <FilterSection
                title="Source"
                :options="[
                  { label: 'Event', value: 'event' },
                  { label: 'Referral', value: 'referral' },
                  { label: 'Website', value: 'website' },
                  { label: 'Import', value: 'import' },
                  { label: 'Manual', value: 'manual' },
                ]"
                :selected="selectedSources"
                @change="handleFilterChange('source', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <!-- Bulk Status Dropdown -->
        <DropdownMenu v-if="canUpdate && selectedRows.length > 0">
          <DropdownMenuTrigger asChild>
            <button
              :disabled="bulkUpdating"
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="bulkUpdating" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:task-edit-01" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Status</span>
              <Icon name="lucide:chevron-down" class="size-3 opacity-60" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" class="w-40">
            <DropdownMenuItem
              v-for="s in contactStatuses"
              :key="s.value"
              :disabled="bulkUpdating"
              class="gap-x-2"
              @click="handleBulkStatusUpdate(selectedRows, s.value)"
            >
              <span :class="s.dot" class="size-2 rounded-full" />
              {{ s.label }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DialogResponsive
          v-if="selectedRows.length > 0"
          v-model:open="deleteDialogOpen"
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
                {{ selectedRows.length === 1 ? "contact" : "contacts" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                  :disabled="deletePending"
                >
                  Cancel
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  :disabled="deletePending"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="deletePending" class="size-4 text-white" />
                  <span v-else>Delete</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </template>
    </TableData>

    <!-- Deduplicate Dialog -->
    <DialogResponsive v-model:open="dedupeDialogOpen" dialog-max-width="560px">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tighter">Remove Duplicates</div>

          <!-- Step 1: Explanation -->
          <div v-if="dedupeStep === 'explain'" class="mt-3">
            <p class="text-body tracking-tight">
              Fitur ini akan mendeteksi dan menghapus contact yang duplikat berdasarkan kriteria
              berikut:
            </p>

            <div class="mt-3 space-y-2">
              <div class="font-medium tracking-tight">Dua contact dianggap duplikat jika:</div>
              <ol class="text-body list-decimal space-y-1.5 pl-5 tracking-tight">
                <li>
                  <span class="text-primary font-medium">Nama sama</span>
                  (case-insensitive)
                </li>
                <li>
                  DAN salah satu dari:
                  <ul class="text-body mt-1 list-disc space-y-1 pl-5">
                    <li><span class="text-primary font-medium">Company</span> sama</li>
                    <li>
                      Ada <span class="text-primary font-medium">email</span> yang sama (minimal 1)
                    </li>
                    <li>
                      Ada <span class="text-primary font-medium">phone</span> yang sama
                      (dibandingkan digit-only)
                    </li>
                  </ul>
                </li>
              </ol>
            </div>

            <div class="bg-muted/50 mt-3 rounded-lg p-2.5">
              <div class="tracking-tight">
                <span class="text-primary font-medium">Yang dipertahankan:</span>
                <span class="text-body"> contact paling lama (created_at terkecil)</span>
              </div>
              <div class="mt-1 tracking-tight">
                <span class="text-primary font-medium">Data di-merge:</span>
                <span class="text-body">
                  emails, phones, tags, dan projects dari duplikat akan digabungkan ke contact yang
                  dipertahankan</span
                >
              </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
              <button
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="dedupeDialogOpen = false"
              >
                Cancel
              </button>
              <Button size="sm" @click="handleScanDuplicates"> Continue </Button>
            </div>
          </div>

          <!-- Step 2: Scanning -->
          <div v-else-if="dedupeStep === 'scanning'" class="mt-4 flex items-center gap-x-2">
            <Spinner class="size-4" />
            <span class="text-body text-sm tracking-tight">Scanning for duplicates...</span>
          </div>

          <!-- Step 3: No duplicates found -->
          <div v-else-if="dedupeStep === 'empty'" class="mt-3">
            <p class="text-body text-sm tracking-tight">No duplicates found.</p>
            <div class="mt-4 flex justify-end">
              <button
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="dedupeDialogOpen = false"
              >
                Close
              </button>
            </div>
          </div>

          <!-- Step 4: Preview duplicates -->
          <div v-else-if="dedupeStep === 'preview'" class="mt-3">
            <p class="text-body text-sm tracking-tight">
              Found
              <span class="text-primary font-medium">{{ dedupeScanResult.duplicate_count }}</span>
              duplicate contact(s) in
              <span class="text-primary font-medium">{{ dedupeScanResult.group_count }}</span>
              group(s).
            </p>

            <!-- Preview groups -->
            <div class="mt-3 max-h-64 space-y-2.5 overflow-y-auto">
              <div
                v-for="(group, idx) in dedupeScanResult.groups.slice(0, 10)"
                :key="idx"
                class="border-border rounded-lg border p-2.5"
              >
                <div
                  class="text-primary flex items-center gap-x-1 text-xs font-medium tracking-tight"
                >
                  <Icon name="lucide:check" class="size-3 shrink-0 text-green-500" />
                  {{ group.keep.name }}
                  <span v-if="group.keep.company_name" class="text-muted-foreground font-normal">
                    - {{ group.keep.company_name }}
                  </span>
                </div>
                <div
                  v-for="dup in group.duplicates"
                  :key="dup.id"
                  class="text-muted-foreground mt-1 flex items-center gap-x-1 text-xs tracking-tight"
                >
                  <Icon name="lucide:trash" class="text-destructive-foreground size-3 shrink-0" />
                  {{ dup.name }}
                  <span v-if="dup.company_name"> - {{ dup.company_name }}</span>
                </div>
              </div>
              <p
                v-if="dedupeScanResult.groups.length > 10"
                class="text-muted-foreground text-xs tracking-tight"
              >
                ... and {{ dedupeScanResult.groups.length - 10 }} more group(s)
              </p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
              <button
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="dedupeDialogOpen = false"
                :disabled="dedupeRemovePending"
              >
                Cancel
              </button>
              <button
                @click="handleRemoveDuplicates"
                :disabled="dedupeRemovePending"
                class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Spinner v-if="dedupeRemovePending" class="size-4 text-white" />
                <span v-else>Remove {{ dedupeScanResult.duplicate_count }} Duplicate(s)</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Create/Edit Dialog -->
    <DialogResponsive
      v-model:open="formDialogOpen"
      dialog-max-width="600px"
      :prevent-close="formDirty"
      :overflow-content="true"
      @close-prevented="showUnsavedWarning = true"
    >
      <template #sticky-header>
        <div
          class="border-border sticky top-0 z-10 -mt-4 border-b px-4 pb-2 text-center md:mt-0 md:px-6 md:py-3.5 md:text-left"
        >
          <div class="text-lg font-semibold tracking-tighter">
            {{ editingContact || editLoading ? "Edit Contact" : "New Contact" }}
          </div>
        </div>
      </template>
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:pb-5">
          <div v-if="editLoading" class="mt-4 space-y-4">
            <!-- Person Name -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-24" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Company Name -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-28" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Job Title -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-16" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Emails -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-14" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Phones -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-14" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Website -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-16" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Business Categories & Tags -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-32" />
              <Skeleton class="h-9 w-full" />
            </div>
            <div class="space-y-2">
              <Skeleton class="h-4 w-10" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Country -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-16" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Street Address -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-24" />
              <Skeleton class="h-16 w-full" />
            </div>
            <!-- Notes -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-12" />
              <Skeleton class="h-16 w-full" />
            </div>
            <!-- Projects -->
            <div class="space-y-2">
              <Skeleton class="h-4 w-16" />
              <Skeleton class="h-9 w-full" />
            </div>
            <!-- Status / Source / Contact Type -->
            <div class="grid grid-cols-2 gap-x-2 gap-y-4 sm:grid-cols-3">
              <div class="space-y-2">
                <Skeleton class="h-4 w-12" />
                <Skeleton class="h-9 w-full" />
              </div>
              <div class="space-y-2">
                <Skeleton class="h-4 w-14" />
                <Skeleton class="h-9 w-full" />
              </div>
              <div class="space-y-2">
                <Skeleton class="h-4 w-24" />
                <Skeleton class="h-9 w-full" />
              </div>
            </div>
            <!-- Buttons -->
            <div class="flex justify-end gap-2">
              <Skeleton class="h-9 w-20" />
              <Skeleton class="h-9 w-32" />
            </div>
          </div>
          <FormContact
            v-else
            ref="formRef"
            :contact="editingContact"
            :api-url="editingContact ? `/api/contacts/${editingContact.ulid}` : '/api/contacts'"
            :method="editingContact ? 'PUT' : 'POST'"
            :submit-label="editingContact ? 'Update Contact' : 'Create Contact'"
            :contact-type-options="contactTypeOptions"
            :business-category-options="businessCategoryOptions"
            :project-options="projectOptions"
            @saved="handleSaved"
            @cancel="formDialogOpen = false"
          />
        </div>
      </template>
    </DialogResponsive>

    <!-- Unsaved Changes Warning -->
    <DialogResponsive v-model:open="showUnsavedWarning">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Unsaved changes</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            You have unsaved changes. Are you sure you want to close?
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="showUnsavedWarning = false"
            >
              Cancel
            </button>
            <button
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
              @click="
                showUnsavedWarning = false;
                formDirty = false;
                formDialogOpen = false;
              "
            >
              Discard
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import ButtonCopy from "@/components/ButtonCopy.vue";
import ContactImportDialog from "@/components/contact/ContactImportDialog.vue";
import ContactTableItem from "@/components/contact/ContactTableItem.vue";
import FormContact from "@/components/contact/FormContact.vue";
import ContactStatusDropdown from "@/components/contact/StatusDropdown.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { defineComponent, resolveComponent, resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["contacts.read"],
  layout: "app",
});

usePageMeta(null, {
  title: "Contact List",
});

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const route = useRoute();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("contacts.create"));
const canUpdate = computed(() => hasPermission("contacts.update"));
const canDelete = computed(() => hasPermission("contacts.delete"));

const contactTypeLabels = {
  exhibitor: "Exhibitor",
  "media-partner": "Media Partner",
  sponsor: "Sponsor",
  speaker: "Speaker",
  vendor: "Vendor",
  visitor: "Visitor",
  other: "Other",
};

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Build query params for server-side pagination
const buildQueryParams = () => {
  const params = new URLSearchParams();

  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  // Filters
  const filters = {
    name: "filter_search",
    status: "filter_status",
    source: "filter_source",
  };

  Object.entries(filters).forEach(([columnId, paramKey]) => {
    const filter = columnFilters.value.find((f) => f.id === columnId);
    if (filter?.value) {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(paramKey, value);
    }
  });

  // Sorting
  const sortField = sorting.value[0]?.id || "name";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch contacts with lazy loading
const {
  data: contactsResponse,
  pending,
  error,
  refresh: fetchContacts,
} = await useLazySanctumFetch(() => `/api/contacts?${buildQueryParams()}`, {
  key: "contacts-list",
  watch: false,
});

const data = computed(() => contactsResponse.value?.data || []);
const meta = computed(
  () => contactsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

// Watch for changes and refetch
watch(
  [columnFilters, sorting, pagination],
  () => {
    fetchContacts();
  },
  { deep: true }
);

// Update handlers
const onPaginationUpdate = (newValue) => {
  pagination.value.pageIndex = newValue.pageIndex;
  pagination.value.pageSize = newValue.pageSize;
};

const onSortingUpdate = (newValue) => {
  sorting.value = newValue;
};

const onColumnFiltersUpdate = (newValue) => {
  columnFilters.value = newValue;
};

const refresh = fetchContacts;

// Table ref
const tableRef = ref();

const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});

const clearSelection = () => {
  if (tableRef.value) {
    tableRef.value.resetRowSelection();
  }
};

// Filter helpers
const getFilterValue = (columnId) => {
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedSources = computed(() => getFilterValue("source"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedSources.value.length
);

const handleFilterChange = (columnId, { checked, value }) => {
  const current = getFilterValue(columnId);
  const updated = checked ? [...current, value] : current.filter((item) => item !== value);

  const existingIndex = columnFilters.value.findIndex((f) => f.id === columnId);
  if (updated.length) {
    if (existingIndex >= 0) {
      columnFilters.value[existingIndex].value = updated;
    } else {
      columnFilters.value.push({ id: columnId, value: updated });
    }
  } else {
    if (existingIndex >= 0) {
      columnFilters.value.splice(existingIndex, 1);
    }
  }
  pagination.value.pageIndex = 0;
};

// Contact statuses for bulk update dropdown
const contactStatuses = [
  { value: "active", label: "Active", dot: "bg-green-500" },
  { value: "inactive", label: "Inactive", dot: "bg-yellow-500" },
  { value: "archived", label: "Archived", dot: "bg-gray-400" },
];

// Bulk status update
const bulkUpdating = ref(false);

async function handleBulkStatusUpdate(selectedRows, newStatus) {
  const ulids = selectedRows.map((row) => row.original.ulid);
  bulkUpdating.value = true;
  try {
    await Promise.all(
      ulids.map((ulid) =>
        client(`/api/contacts/${ulid}/status`, {
          method: "PATCH",
          body: { status: newStatus },
        })
      )
    );
    toast.success(`${ulids.length} contact(s) status updated`);
    await refresh();
  } catch (err) {
    toast.error("Failed to update status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkUpdating.value = false;
  }
}

// Status update (inline from table)
const statusUpdating = ref(null);

async function handleStatusUpdate(ulid, newStatus) {
  statusUpdating.value = ulid;
  try {
    await client(`/api/contacts/${ulid}/status`, {
      method: "PATCH",
      body: { status: newStatus },
    });
    toast.success("Status updated");
    await refresh();
  } catch (err) {
    toast.error("Failed to update status");
  } finally {
    statusUpdating.value = null;
  }
}

// Create/Edit Dialog
const formDialogOpen = ref(false);
const editingContact = ref(null);
const editLoading = ref(false);
const formDirty = ref(false);
const showUnsavedWarning = ref(false);
const formRef = ref();
const contactTypeOptions = ref([]);
const businessCategoryOptions = ref([]);
const projectOptions = ref([]);

// Preload options once on mount
onMounted(async () => {
  contactTypeOptions.value = [
    { value: "exhibitor", label: "Exhibitor" },
    { value: "media-partner", label: "Media Partner" },
    { value: "sponsor", label: "Sponsor" },
    { value: "speaker", label: "Speaker" },
    { value: "vendor", label: "Vendor" },
    { value: "visitor", label: "Visitor" },
    { value: "other", label: "Other" },
  ];

  try {
    const catRes = await client("/api/contacts-business-categories");
    businessCategoryOptions.value = (catRes.data || []).map((c) => c.name);
  } catch {
    // Silent fail
  }

  try {
    const projectRes = await client("/api/projects");
    projectOptions.value = projectRes.data || [];
  } catch {
    // Projects may not be accessible
  }
});

const openCreateDialog = () => {
  editingContact.value = null;
  formDirty.value = false;
  formDialogOpen.value = true;
};

const openEditDialog = async (contact) => {
  editingContact.value = null;
  formDirty.value = false;
  editLoading.value = true;
  formDialogOpen.value = true;

  try {
    const res = await client(`/api/contacts/${contact.ulid}`);
    editingContact.value = res.data;

    if (res.business_category_options?.length) {
      businessCategoryOptions.value = res.business_category_options;
    }
  } catch (e) {
    toast.error("Failed to load contact");
    formDialogOpen.value = false;
  }

  editLoading.value = false;
};

const handleSaved = () => {
  formDirty.value = false;
  formDialogOpen.value = false;
  editingContact.value = null;
  refresh();
};

// Keyboard shortcut
defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) {
        openCreateDialog();
      }
    },
    whenever: [computed(() => route.path === "/contacts")],
  },
});

// Table columns
const columns = computed(() => [
  ...(canDelete.value
    ? [
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
      ]
    : []),
  {
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) =>
      h(ContactTableItem, {
        contact: row.original,
        class: canUpdate.value ? "cursor-pointer" : "",
        onClick: () => canUpdate.value && openEditDialog(row.original),
      }),
    size: 220,
    enableHiding: false,
  },
  {
    header: "Company",
    accessorKey: "company_name",
    cell: ({ row }) => {
      const company = row.getValue("company_name");
      if (!company) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("span", { class: "text-sm tracking-tight" }, company);
    },
    size: 160,
  },
  {
    header: "Type",
    accessorKey: "contact_types",
    cell: ({ row }) => {
      const types = row.original.contact_types || [];
      if (!types.length) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "span",
        { class: "text-sm tracking-tight" },
        types.map((t) => contactTypeLabels[t] || t).join(", ")
      );
    },
    size: 140,
    enableSorting: false,
  },
  {
    header: "Email",
    accessorKey: "emails",
    cell: ({ row }) => h(EmailCell, { emails: row.original.emails }),
    size: 220,
    enableSorting: false,
  },
  {
    header: "Phone",
    accessorKey: "phones",
    cell: ({ row }) => h(PhoneCell, { phones: row.original.phones }),
    size: 180,
    enableSorting: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      if (!canUpdate.value) {
        const status = row.original.status;
        const colorMap = {
          green: "text-success-foreground",
          yellow: "text-warning-foreground",
          gray: "text-muted-foreground",
        };
        return h(
          "span",
          {
            class: `inline-flex items-center text-sm tracking-tight ${colorMap[status?.color] || "text-muted-foreground"}`,
          },
          status?.label || status?.value || "-"
        );
      }
      return h(ContactStatusDropdown, {
        status: row.original.status?.value || "active",
        disabled: statusUpdating.value === row.original.ulid,
        onUpdate: (newStatus) => handleStatusUpdate(row.original.ulid, newStatus),
      });
    },
    size: 120,
  },
  {
    header: "Projects",
    accessorKey: "projects",
    cell: ({ row }) => h(ProjectsCell, { projects: row.original.projects }),
    size: 120,
    enableSorting: false,
  },
  {
    header: "Source",
    accessorKey: "source",
    cell: ({ row }) => {
      const source = row.getValue("source");
      if (!source) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("span", { class: "text-sm tracking-tight capitalize" }, source);
    },
    size: 100,
  },
  {
    header: "Created By",
    accessorKey: "created_by_name",
    cell: ({ row }) => {
      const name = row.getValue("created_by_name");
      if (!name) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("span", { class: "text-sm tracking-tight" }, name);
    },
    size: 120,
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "div",
        { class: "text-muted-foreground text-sm tracking-tight" },
        $dayjs(date).fromNow()
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { contact: row.original }),
    size: 120,
    enableHiding: false,
  },
]);

// Export
const exportPending = ref(false);

const handleExport = async () => {
  try {
    exportPending.value = true;

    const params = new URLSearchParams();

    columnFilters.value.forEach((filter) => {
      const filterMapping = {
        name: "filter_search",
        status: "filter_status",
        source: "filter_source",
      };
      const paramKey = filterMapping[filter.id];
      if (paramKey && filter.value) {
        const paramValue = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, paramValue);
      }
    });

    const sortField = sorting.value[0]?.id || "name";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

    const response = await client(`/api/contacts/export?${params.toString()}`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `contacts_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Contacts exported successfully");
  } catch (err) {
    console.error("Failed to export contacts:", err);
    toast.error("Failed to export contacts", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Delete
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

// Deduplicate
const dedupeDialogOpen = ref(false);
const dedupeStep = ref("explain"); // 'explain' | 'scanning' | 'empty' | 'preview'
const dedupeRemovePending = ref(false);
const dedupeScanResult = ref(null);

const openDedupeDialog = () => {
  dedupeStep.value = "explain";
  dedupeScanResult.value = null;
  dedupeDialogOpen.value = true;
};

const handleScanDuplicates = async () => {
  dedupeStep.value = "scanning";
  try {
    const res = await client("/api/contacts/duplicates/scan");
    dedupeScanResult.value = res;
    dedupeStep.value = res.duplicate_count > 0 ? "preview" : "empty";
  } catch (err) {
    toast.error("Failed to scan duplicates", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
    dedupeDialogOpen.value = false;
  }
};

const handleRemoveDuplicates = async () => {
  dedupeRemovePending.value = true;
  try {
    const res = await client("/api/contacts/duplicates/remove", { method: "POST" });
    dedupeDialogOpen.value = false;
    await refresh();
    toast.success(res.message || `${res.removed_count} duplicate(s) removed`);
  } catch (err) {
    toast.error("Failed to remove duplicates", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    dedupeRemovePending.value = false;
  }
};

const handleDeleteRows = async (selectedRows) => {
  const ulids = selectedRows.map((row) => row.original.ulid);
  try {
    deletePending.value = true;
    await Promise.all(ulids.map((ulid) => client(`/api/contacts/${ulid}`, { method: "DELETE" })));
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(`${ulids.length} contact(s) deleted`);
  } catch (err) {
    toast.error("Failed to delete contacts", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (contactUlid) => {
  try {
    deletePending.value = true;
    await client(`/api/contacts/${contactUlid}`, { method: "DELETE" });
    await refresh();
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success("Contact deleted");
  } catch (err) {
    toast.error("Failed to delete contact", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deletePending.value = false;
  }
};

// Email Cell Component
const EmailCell = defineComponent({
  props: {
    emails: { type: Array, default: () => [] },
  },
  setup(props) {
    const emails = computed(() => (props.emails || []).filter((e) => e));
    return () => {
      if (!emails.value.length) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return h(
        "div",
        { class: "flex flex-col" },
        emails.value.map((email) =>
          h("div", { class: "flex items-center gap-x-0.5" }, [
            h(ButtonCopy, { text: email, class: "size-5 shrink-0" }),
            h(
              "a",
              {
                href: `mailto:${email}`,
                class: "text-sm tracking-tight truncate hover:underline",
              },
              email
            ),
          ])
        )
      );
    };
  },
});

// Phone Cell Component
const PhoneCell = defineComponent({
  props: {
    phones: { type: Array, default: () => [] },
  },
  setup(props) {
    const phones = computed(() => (props.phones || []).filter((p) => p));
    return () => {
      if (!phones.value.length) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return h(
        "div",
        { class: "flex flex-col" },
        phones.value.map((phone) => {
          const cleanPhone = phone.replace(/\D/g, "");
          return h("div", { class: "flex items-center gap-x-0.5" }, [
            h(ButtonCopy, { text: phone, class: "size-5 shrink-0" }),
            h(
              "a",
              {
                href: `https://wa.me/${cleanPhone}`,
                target: "_blank",
                rel: "noopener noreferrer",
                class: "text-sm tracking-tight truncate hover:underline",
              },
              phone
            ),
          ]);
        })
      );
    };
  },
});

// Projects Cell Component
const ProjectsCell = defineComponent({
  props: {
    projects: { type: Array, default: () => [] },
  },
  setup(props) {
    return () => {
      const projects = props.projects || [];
      if (!projects.length) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return h(
        "div",
        { class: "flex items-center -space-x-1" },
        projects.map((project) =>
          withDirectives(
            h(Avatar, {
              model: project,
              size: "sm",
              class: "size-7 rounded-full ring-2 ring-background",
              rounded: "rounded-full",
            }),
            [[resolveDirective("tippy"), project.name]]
          )
        )
      );
    };
  },
});

// Row Actions Component
const RowActions = defineComponent({
  props: {
    contact: { type: Object, required: true },
  },
  setup(props) {
    const dialogOpen = ref(false);
    const singleDeletePending = ref(false);

    const primaryPhone = computed(
      () => props.contact.primary_phone || (props.contact.phones || [])[0]
    );
    const primaryEmail = computed(
      () => props.contact.primary_email || (props.contact.emails || [])[0]
    );

    const whatsappLink = computed(() => {
      if (!primaryPhone.value) return null;
      const cleanPhone = primaryPhone.value.replace(/\D/g, "");
      return `https://wa.me/${cleanPhone}`;
    });

    const emailLink = computed(() => {
      if (!primaryEmail.value) return null;
      return `mailto:${primaryEmail.value}`;
    });

    return () =>
      h("div", { class: "flex items-center justify-end gap-x-3" }, [
        // Edit button
        ...(canUpdate.value
          ? [
              withDirectives(
                h(
                  "button",
                  {
                    class:
                      "hover:bg-muted inline-flex size-8 items-center justify-center rounded-md",
                    onClick: () => openEditDialog(props.contact),
                  },
                  [h(resolveComponent("Icon"), { name: "hugeicons:edit-03", class: "size-4" })]
                ),
                [[resolveDirective("tippy"), "Edit"]]
              ),
            ]
          : []),
        // WhatsApp button
        primaryPhone.value
          ? withDirectives(
              h(
                "a",
                {
                  href: whatsappLink.value,
                  target: "_blank",
                  rel: "noopener noreferrer",
                  class:
                    "hover:bg-muted inline-flex size-8 items-center justify-center rounded-md text-success-foreground",
                },
                [h(resolveComponent("Icon"), { name: "hugeicons:whatsapp", class: "size-4" })]
              ),
              [[resolveDirective("tippy"), "WhatsApp"]]
            )
          : null,
        // Email button
        primaryEmail.value
          ? withDirectives(
              h(
                "a",
                {
                  href: emailLink.value,
                  class:
                    "hover:bg-muted inline-flex size-8 items-center justify-center rounded-md text-info-foreground",
                },
                [h(resolveComponent("Icon"), { name: "hugeicons:mail-01", class: "size-4" })]
              ),
              [[resolveDirective("tippy"), "Email"]]
            )
          : null,
        // Delete button
        ...(canDelete.value
          ? [
              withDirectives(
                h(
                  "button",
                  {
                    class:
                      "hover:bg-destructive/10 text-destructive-foreground inline-flex size-8 items-center justify-center rounded-md",
                    onClick: () => (dialogOpen.value = true),
                  },
                  [h(resolveComponent("Icon"), { name: "hugeicons:delete-01", class: "size-4" })]
                ),
                [[resolveDirective("tippy"), "Delete"]]
              ),
            ]
          : []),
        h(
          DialogResponsive,
          {
            open: dialogOpen.value,
            "onUpdate:open": (value) => (dialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This contact will be deleted."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (dialogOpen.value = false),
                      disabled: singleDeletePending.value,
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-destructive text-white hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50",
                      disabled: singleDeletePending.value,
                      onClick: async () => {
                        singleDeletePending.value = true;
                        try {
                          await handleDeleteSingleRow(props.contact.ulid);
                          dialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Delete"
                  ),
                ]),
              ]),
          }
        ),
      ]);
  },
});

// Filter Section Component
const FilterSection = defineComponent({
  props: {
    title: String,
    options: Array,
    selected: Array,
  },
  emits: ["change"],
  setup(props, { emit }) {
    return () =>
      h("div", { class: "space-y-2" }, [
        h("div", { class: "text-muted-foreground text-xs font-medium" }, props.title),
        h(
          "div",
          { class: "space-y-2" },
          props.options.map((option, i) => {
            const value = typeof option === "string" ? option : option.value;
            const label = typeof option === "string" ? option : option.label;
            return h("div", { key: value, class: "flex items-center gap-2" }, [
              h(Checkbox, {
                id: `${props.title}-${i}`,
                modelValue: props.selected.includes(value),
                "onUpdate:modelValue": (checked) => emit("change", { checked: !!checked, value }),
              }),
              h(
                Label,
                {
                  for: `${props.title}-${i}`,
                  class: "grow cursor-pointer font-normal tracking-tight capitalize",
                },
                { default: () => label }
              ),
            ]);
          })
        ),
      ]);
  },
});
</script>
