<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold tracking-tight">Inquiries</h2>

      <div v-if="hasSelectedRows" class="flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear</span>
        </button>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="inquiries"
      label="Inquiry"
      search-column="subject"
      search-placeholder="Search subject, name, email..."
      error-title="Error loading inquiries"
      :show-add-button="false"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
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
          <PopoverContent class="w-auto min-w-48 p-3" align="start">
            <div class="space-y-4">
              <InboxFilterSection
                title="Status"
                :options="statusOptions"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="canDelete && selectedRows.length > 0"
          v-model:open="deleteDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
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
                This will move {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "submission" : "submissions" }} to trash.
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

    <!-- Detail Dialog -->
    <InboxDetailDialog
      v-model:open="detailDialogOpen"
      v-model:submission="detailSubmission"
      @status-updated="handleDetailStatusUpdated"
    />
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import InboxDetailDialog from "@/components/inbox/DetailDialog.vue";
import InboxFilterSection from "@/components/inbox/FilterSection.vue";
import InboxTableItem from "@/components/inbox/InboxTableItem.vue";
import StatusDropdown from "@/components/inbox/StatusDropdown.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["contact_forms.read"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Inquiries · ${props.project?.name || ""}`),
});

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

const { hasPermission } = usePermission();
const canDelete = computed(() => hasPermission("contact_forms.delete"));

// Status options
const statusOptions = [
  { label: "New", value: "new" },
  { label: "In Progress", value: "in_progress" },
  { label: "Completed", value: "completed" },
  { label: "Archived", value: "archived" },
];

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 20 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Build query params - always filter by current project
const buildQueryParams = () => {
  const params = new URLSearchParams();

  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);
  params.append("filter_project", props.project?.id);

  const searchFilter = columnFilters.value.find((f) => f.id === "subject");
  if (searchFilter?.value) {
    params.append("filter_search", searchFilter.value);
  }

  const statusFilter = columnFilters.value.find((f) => f.id === "status");
  if (statusFilter?.value && Array.isArray(statusFilter.value) && statusFilter.value.length > 0) {
    params.append("filter_status", statusFilter.value.join(","));
  }

  const sortField = sorting.value[0]?.id || "created_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch submissions
const submissionsResponse = ref(null);
const pending = ref(true);
const error = ref(null);

const data = computed(() => submissionsResponse.value?.data || []);
const meta = computed(
  () =>
    submissionsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 20, total: 0 }
);

async function fetchSubmissions() {
  pending.value = true;
  error.value = null;
  try {
    const response = await client(`/api/contact-form-submissions?${buildQueryParams()}`);
    submissionsResponse.value = response;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch inquiries:", err);
  } finally {
    pending.value = false;
  }
}

const refresh = fetchSubmissions;

watch(
  [columnFilters, sorting, pagination],
  () => {
    fetchSubmissions();
  },
  { deep: true }
);

onMounted(fetchSubmissions);

// Detail dialog
const detailDialogOpen = ref(false);
const detailSubmission = ref(null);

function openDetailDialog(submission) {
  detailSubmission.value = submission;
  detailDialogOpen.value = true;
}

function handleDetailStatusUpdated() {
  refresh();
}

// Status update (inline from table)
const statusUpdating = ref(null);

async function handleStatusUpdate(ulid, newStatus) {
  statusUpdating.value = ulid;
  try {
    await client(`/api/contact-form-submissions/${ulid}/status`, {
      method: "PATCH",
      body: { status: newStatus },
    });
    toast.success("Status updated");
    // Update detail dialog if open for this submission
    if (detailSubmission.value?.ulid === ulid) {
      detailSubmission.value = { ...detailSubmission.value, status: newStatus };
    }
    await refresh();
  } catch (err) {
    toast.error("Failed to update status");
  } finally {
    statusUpdating.value = null;
  }
}

// Table columns (no project column since it's already filtered)
const columns = [
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
    header: "Submission",
    accessorKey: "subject",
    cell: ({ row }) =>
      h(InboxTableItem, {
        submission: row.original,
        onView: () => openDetailDialog(row.original),
      }),
    size: 300,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const subject = row.original.subject?.toLowerCase() || "";
      const name = row.original.form_data_preview?.name?.toLowerCase() || "";
      const email = row.original.form_data_preview?.email?.toLowerCase() || "";
      return (
        subject.includes(searchValue) || name.includes(searchValue) || email.includes(searchValue)
      );
    },
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return withDirectives(
        h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) =>
      h(StatusDropdown, {
        status: row.getValue("status"),
        disabled: statusUpdating.value === row.original.ulid,
        onUpdate: (newStatus) => handleStatusUpdate(row.original.ulid, newStatus),
      }),
    size: 120,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { submission: row.original }),
    size: 120,
    enableHiding: false,
  },
];

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
const selectedStatuses = computed(() => {
  return columnFilters.value.find((f) => f.id === "status")?.value ?? [];
});

const totalActiveFilters = computed(() => selectedStatuses.value.length);

const handleFilterChange = (columnId, { checked, value }) => {
  const current = columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
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

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const response = await client("/api/contact-form-submissions/bulk", {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Submissions deleted", {
      description:
        response.errors?.length > 0
          ? `${response.deleted_count} deleted, ${response.errors.length} failed`
          : `${response.deleted_count} submission(s) moved to trash`,
    });
  } catch (err) {
    console.error("Failed to delete submissions:", err);
    toast.error("Failed to delete submissions", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (ulid) => {
  try {
    deletePending.value = true;
    const response = await client(`/api/contact-form-submissions/${ulid}`, { method: "DELETE" });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Submission deleted successfully");
  } catch (error) {
    console.error("Failed to delete submission:", error);
    toast.error("Failed to delete submission", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    submission: { type: Object, required: true },
  },
  setup(props) {
    const dialogOpen = ref(false);
    const singleDeletePending = ref(false);

    const phone = computed(() => props.submission.form_data_preview?.phone);
    const email = computed(() => props.submission.form_data_preview?.email);

    const whatsappLink = computed(() => {
      if (!phone.value) return null;
      const cleanPhone = phone.value.replace(/\D/g, "");
      return `https://wa.me/${cleanPhone}`;
    });

    const emailLink = computed(() => {
      if (!email.value) return null;
      return `mailto:${email.value}`;
    });

    return () =>
      h("div", { class: "flex items-center justify-end gap-1" }, [
        // WhatsApp button (only if phone exists)
        phone.value
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
        email.value
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
        // Delete button (only if user has permission)
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
                  "This will move this submission to trash."
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
                          await handleDeleteSingleRow(props.submission.ulid);
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
</script>
