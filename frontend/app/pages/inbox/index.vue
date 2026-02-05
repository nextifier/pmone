<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:mail-open-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Inbox</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <InboxImportDialog @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </InboxImportDialog>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export {{ totalActiveFilters > 0 ? "filtered" : "all" }}</span>
        </button>

        <nuxt-link
          to="/inbox/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </nuxt-link>
      </div>

      <div v-else class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear selection</span>
        </button>
      </div>
    </div>

    <TableData
      :clientOnly="clientOnly"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="inbox"
      label="Submission"
      search-column="subject"
      search-placeholder="Search subject, name, email..."
      error-title="Error loading submissions"
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
        <!-- Filter Popover -->
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
            >
              <Icon name="lucide:list-filter" class="size-4 shrink-0" />
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
              <FilterSection
                title="Status"
                :options="[
                  { label: 'New', value: 'new' },
                  { label: 'In Progress', value: 'in_progress' },
                  { label: 'Completed', value: 'completed' },
                  { label: 'Archived', value: 'archived' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />

              <FilterSection
                v-if="projects.length > 0"
                title="Project"
                :options="projectOptions"
                :selected="selectedProjects"
                @change="handleFilterChange('project_id', $event)"
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
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import InboxImportDialog from "@/components/inbox/ImportDialog.vue";
import InboxTableItem from "@/components/inbox/InboxTableItem.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["contact_forms.read"],
  layout: "app",
});

defineOptions({
  name: "inbox",
});

usePageMeta("inbox");

const { $dayjs } = useNuxtApp();

// Permission checking
const { hasPermission } = usePermission();
const canDelete = computed(() => hasPermission("contact_forms.delete"));

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 20 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Data state
const clientOnly = ref(false);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    const filters = {
      subject: "filter_search",
      status: "filter_status",
      project_id: "filter_project",
    };

    Object.entries(filters).forEach(([columnId, paramKey]) => {
      const filter = columnFilters.value.find((f) => f.id === columnId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, value);
      }
    });

    const sortField = sorting.value[0]?.id || "created_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch projects for filter
const { data: projectsResponse } = await useLazySanctumFetch("/api/projects?client_only=true", {
  key: "inbox-projects",
});

const projects = computed(() => {
  const response = projectsResponse.value;
  if (Array.isArray(response)) {
    return response;
  } else if (response && response.data) {
    return Array.isArray(response.data) ? response.data : [];
  }
  return [];
});

const projectOptions = computed(() =>
  projects.value.map((p) => ({ label: p.name, value: p.id.toString() }))
);

// Fetch submissions with lazy loading
const {
  data: submissionsResponse,
  pending,
  error,
  refresh: fetchSubmissions,
} = await useLazySanctumFetch(() => `/api/contact-form-submissions?${buildQueryParams()}`, {
  key: "inbox-list",
  watch: false,
});

const data = computed(() => submissionsResponse.value?.data || []);
const meta = computed(
  () => submissionsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 }
);

// Global state for refresh tracking
const needsRefresh = useState("inbox-needs-refresh", () => false);

// Refresh when component is activated from KeepAlive
onActivated(async () => {
  if (needsRefresh.value) {
    await fetchSubmissions();
    needsRefresh.value = false;
  }
});

const refresh = fetchSubmissions;

// Status badge config
const getStatusConfig = (status) => {
  const configs = {
    new: {
      label: "New",
      color: "bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400",
    },
    in_progress: {
      label: "In Progress",
      color: "bg-yellow-500/10 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-400",
    },
    completed: {
      label: "Completed",
      color: "bg-green-500/10 text-green-600 dark:bg-green-500/20 dark:text-green-400",
    },
    archived: {
      label: "Archived",
      color: "bg-gray-500/10 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400",
    },
  };
  return configs[status] || configs.new;
};

// Table columns
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
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      const config = getStatusConfig(status);
      return h(
        "span",
        {
          class: `inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${config.color}`,
        },
        config.label
      );
    },
    size: 100,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Project",
    accessorKey: "project.name",
    cell: ({ row }) => {
      const project = row.original.project;
      if (!project) {
        return h("div", { class: "text-sm text-muted-foreground tracking-tight" }, "-");
      }
      return h("div", { class: "flex items-center gap-2" }, [
        h(Avatar, {
          model: project,
          size: "sm",
          class: "size-6 rounded-full",
          rounded: "rounded-full",
        }),
        h("span", { class: "text-sm tracking-tight truncate" }, project.name),
      ]);
    },
    size: 200,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const projectId = row.original.project?.id?.toString();
      return filterValue.includes(projectId);
    },
    accessorFn: (row) => row.project?.id?.toString(),
    id: "project_id",
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
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { submission: row.original }),
    size: 120,
    enableHiding: false,
  },
];

// Table ref
const tableRef = ref();

// Check if there are any selected rows
const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});

// Clear selection
const clearSelection = () => {
  if (tableRef.value) {
    tableRef.value.resetRowSelection();
  }
};

// Filter helpers
const getFilterValue = (columnId) => {
  if (clientOnly.value && tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedProjects = computed(() => getFilterValue("project_id"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedProjects.value.length
);

const handleFilterChange = (columnId, { checked, value }) => {
  if (clientOnly.value && tableRef.value?.table) {
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    tableRef.value.table.setPageIndex(0);
  } else {
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
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);
const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
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
  } catch (error) {
    console.error("Failed to delete submissions:", error);
    toast.error("Failed to delete submissions", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (ulid) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
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

// Export handler
const exportPending = ref(false);
const handleExport = async () => {
  try {
    exportPending.value = true;

    // Build query params
    const params = new URLSearchParams();

    // Add search filter if present
    const searchFilter = columnFilters.value.find((f) => f.id === "subject");
    if (searchFilter?.value) {
      params.append("filter_search", searchFilter.value);
    }

    // Add status filter if present
    if (selectedStatuses.value.length > 0) {
      params.append("filter_status", selectedStatuses.value.join(","));
    }

    // Add project filter if present
    if (selectedProjects.value.length > 0) {
      params.append("filter_project", selectedProjects.value.join(","));
    }

    // Add sorting
    const sortField = sorting.value[0]?.id || "created_at";
    const sortDirection = sorting.value[0]?.desc ? "-" : "";
    params.append("sort", `${sortDirection}${sortField}`);

    const client = useSanctumClient();

    // Fetch the file as blob
    const response = await client(`/api/contact-form-submissions/export?${params.toString()}`, {
      responseType: "blob",
    });

    // Create a download link and trigger download
    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `inbox_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Inbox exported successfully");
  } catch (error) {
    console.error("Failed to export inbox:", error);
    toast.error("Failed to export inbox", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    submission: { type: Object, required: true },
  },
  setup(props) {
    const router = useRouter();
    const dialogOpen = ref(false);
    const singleDeletePending = ref(false);

    const phone = computed(() => props.submission.form_data_preview?.phone);
    const email = computed(() => props.submission.form_data_preview?.email);

    // Format phone for WhatsApp (remove non-digits)
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
        h(
          Popover,
          {},
          {
            default: () => [
              h(
                PopoverTrigger,
                { asChild: true },
                {
                  default: () =>
                    h(
                      "button",
                      {
                        class:
                          "hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md",
                      },
                      [h(resolveComponent("Icon"), { name: "lucide:ellipsis", class: "size-4" })]
                    ),
                }
              ),
              h(
                PopoverContent,
                { align: "end", class: "w-40 p-1" },
                {
                  default: () =>
                    h("div", { class: "flex flex-col" }, [
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              "button",
                              {
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: () => router.push(`/inbox/${props.submission.ulid}`),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "lucide:eye",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "View"),
                              ]
                            ),
                        }
                      ),
                      // Delete button (only if user has permission)
                      ...(canDelete.value
                        ? [
                            h(
                              PopoverClose,
                              { asChild: true },
                              {
                                default: () =>
                                  h(
                                    "button",
                                    {
                                      class:
                                        "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                      onClick: () => (dialogOpen.value = true),
                                    },
                                    [
                                      h(resolveComponent("Icon"), {
                                        name: "lucide:trash",
                                        class: "size-4 shrink-0",
                                      }),
                                      h("span", {}, "Delete"),
                                    ]
                                  ),
                              }
                            ),
                          ]
                        : []),
                    ]),
                }
              ),
            ],
          }
        ),
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

// Import PopoverClose
import { PopoverClose } from "reka-ui";
</script>
