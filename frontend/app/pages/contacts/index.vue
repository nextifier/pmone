<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:contact-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Contact List</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
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
        source: false,
        created_at: false,
      }"
      :show-add-button="false"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #add-button>
        <Button v-if="canCreate" size="sm" @click="navigateTo('/contacts/create')">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          Add Contact
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
  </div>
</template>

<script setup>
import ContactImportDialog from "@/components/contact/ContactImportDialog.vue";
import ContactTableItem from "@/components/contact/ContactTableItem.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { defineComponent, resolveComponent } from "vue";
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
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("contacts.create"));
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
const sorting = ref([{ id: "name", desc: false }]);

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
const totalActiveFilters = computed(() => selectedStatuses.value.length + selectedSources.value.length);

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
    header: "Contact",
    accessorKey: "name",
    cell: ({ row }) =>
      h(ContactTableItem, {
        contact: row.original,
      }),
    size: 280,
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
    size: 180,
  },
  {
    header: "Type",
    accessorKey: "contact_types",
    cell: ({ row }) => {
      const types = row.original.contact_types || [];
      if (!types.length) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("div", { class: "flex flex-wrap gap-1" }, [
        ...types.map((t) =>
          h(
            "span",
            {
              class:
                "bg-muted text-muted-foreground inline-flex truncate rounded px-1.5 py-0.5 text-xs tracking-tight",
            },
            contactTypeLabels[t] || t
          )
        ),
      ]);
    },
    size: 140,
    enableSorting: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
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
    },
    size: 100,
  },
  {
    header: "Projects",
    accessorKey: "projects_count",
    cell: ({ row }) => {
      const count = row.getValue("projects_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 80,
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
    size: 60,
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
      const filterMapping = { name: "filter_search", status: "filter_status", source: "filter_source" };
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

// Row Actions Component
const RowActions = defineComponent({
  props: {
    contact: { type: Object, required: true },
  },
  setup(props) {
    const dialogOpen = ref(false);
    const singleDeletePending = ref(false);

    return () =>
      h("div", { class: "flex justify-end" }, [
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
                      // Edit
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `/contacts/${props.contact.ulid}/edit`,
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                              },
                              {
                                default: () => [
                                  h(resolveComponent("Icon"), {
                                    name: "lucide:pencil",
                                    class: "size-4 shrink-0",
                                  }),
                                  h("span", {}, "Edit"),
                                ],
                              }
                            ),
                        }
                      ),
                      // Delete
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
