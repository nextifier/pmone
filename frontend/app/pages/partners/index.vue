<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:agreement-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Partners</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <PartnerImportDialog v-if="canCreate" @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </PartnerImportDialog>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>{{ totalActiveFilters > 0 ? 'Export Selected' : 'Export All' }}</span>
        </button>

        <NuxtLink
          v-if="canDelete"
          to="/partners/trash"
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
          <span>Clear selection</span>
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
      model="partners"
      label="Partner"
      search-column="partner_name"
      search-placeholder="Search partners..."
      error-title="Error loading partners"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="false"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #add-button>
        <Button v-if="canCreate" size="sm" @click="openCreateDialog">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          Add Partner
          <KbdGroup>
            <Kbd>N</Kbd>
          </KbdGroup>
        </Button>
      </template>

      <template #filters>
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
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
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
              <div class="text-primary text-lg font-semibold tracking-tight">
                Are you sure?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will move {{ selectedRows.length }} partner(s) to trash.
              </p>

              <div v-if="deleteJob.processing.value" class="mt-3 space-y-2">
                <div class="flex items-center justify-between text-sm tracking-tight">
                  <span class="text-muted-foreground">{{ deleteJob.progress.value?.message }}</span>
                  <span class="font-medium tabular-nums">{{ deleteJob.progress.value?.percentage ?? 0 }}%</span>
                </div>
                <Progress :model-value="deleteJob.progress.value?.percentage ?? 0" indicator-class="bg-destructive" />
                <p v-if="deleteJob.progress.value?.total > 0" class="text-muted-foreground text-xs sm:text-sm tracking-tight tabular-nums">
                  {{ deleteJob.progress.value?.processed ?? 0 }} / {{ deleteJob.progress.value?.total ?? 0 }}
                </p>
              </div>

              <div v-else class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                >
                  Cancel
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
                >
                  Delete
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </template>
    </TableData>

    <!-- Create Partner Dialog -->
    <DialogResponsive
      v-model:open="formDialogOpen"
      dialog-max-width="28rem"
      :overflow-content="true"
    >
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Create new partner</h3>

          <form @submit.prevent="handleCreate" class="mt-4 space-y-4">
            <div class="space-y-2">
              <div class="space-y-1">
                <Label>Logo</Label>
                <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                  Format PNG dengan background transparan, ukuran 600x400px. Jangan pakai logo warna putih karena tidak terlihat di background terang.
                </p>
              </div>
              <InputFileImage
                v-model="logoFiles"
                container-class="relative isolate aspect-3/2 max-w-40"
              />
            </div>

            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="createForm.name" placeholder="Partner name" auto-focus required />
            </div>

            <div class="space-y-2">
              <Label>Website URL</Label>
              <Input v-model="createForm.website_url" placeholder="https://example.com" type="url" />
            </div>

            <div class="flex justify-end gap-2">
              <Button variant="outline" type="button" @click="formDialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="createSaving">
                <Icon v-if="createSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Create Partner
                <KbdGroup>
                  <Kbd>&#8984;</Kbd>
                  <Kbd>S</Kbd>
                </KbdGroup>
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import PartnerImportDialog from "@/components/partner/PartnerImportDialog.vue";
import PartnerTableItem from "@/components/partner/TableItem.vue";
import { TableData } from "@/components/ui/table-data";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import InputFileImage from "@/components/InputFileImage.vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Textarea } from "@/components/ui/textarea";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { defineComponent, resolveComponent } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["partners.read"],
  layout: "app",
});

usePageMeta(null, {
  title: "Partners",
});

const route = useRoute();
const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("partners.create"));
const canDelete = computed(() => hasPermission("partners.delete"));
const baseUrl = "/partners";

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "partner_name", desc: false }]);

const buildQueryParams = () => {
  const params = new URLSearchParams();

  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const filters = {
    partner_name: "filter_search",
    status: "filter_status",
  };

  Object.entries(filters).forEach(([columnId, paramKey]) => {
    const filter = columnFilters.value.find((f) => f.id === columnId);
    if (filter?.value) {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(paramKey, value);
    }
  });

  const sortField = sorting.value[0]?.id || "partner_name";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

const {
  data: partnersResponse,
  pending,
  error,
  refresh: fetchPartners,
} = await useLazySanctumFetch(() => `/api/partners?${buildQueryParams()}`, {
  key: "partners-list",
  watch: false,
});

const data = computed(() => partnersResponse.value?.data || []);
const meta = computed(
  () => partnersResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

watch(
  [columnFilters, sorting, pagination],
  () => {
    fetchPartners();
  },
  { deep: true }
);

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

const refresh = fetchPartners;

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
const totalActiveFilters = computed(() => selectedStatuses.value.length);

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
    header: "Partner",
    accessorKey: "partner_name",
    cell: ({ row }) =>
      h(PartnerTableItem, {
        partner: row.original,
        baseUrl: baseUrl,
        linkSuffix: "/edit",
      }),
    size: 300,
    enableHiding: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      return h(
        Badge,
        {
          variant: status === "active" ? "success" : "muted",
          withIcon: true,
          plain: true,
        },
        { default: () => (status === "active" ? "Active" : "Inactive") }
      );
    },
    size: 100,
  },
  {
    header: "Website",
    accessorKey: "website_url",
    cell: ({ row }) => {
      const url = row.getValue("website_url");
      if (!url) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "a",
        {
          href: url,
          target: "_blank",
          class: "text-sm tracking-tight text-primary hover:underline truncate block max-w-48",
        },
        url.replace(/^https?:\/\//, "")
      );
    },
    size: 200,
    enableSorting: false,
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
    cell: ({ row }) => h(RowActions, { partner: row.original }),
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
      const filterMapping = { partner_name: "filter_search", status: "filter_status" };
      const paramKey = filterMapping[filter.id];
      if (paramKey && filter.value) {
        const paramValue = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, paramValue);
      }
    });

    const sortField = sorting.value[0]?.id || "partner_name";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

    const response = await client(`/api/partners/export?${params.toString()}`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `partners_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Partners exported successfully");
  } catch (err) {
    console.error("Failed to export partners:", err);
    toast.error("Failed to export partners", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Bulk delete
const deleteDialogOpen = ref(false);
const deleteJob = useJobProgress();

watch(deleteDialogOpen, (open) => {
  if (!open && deleteJob.processing.value) {
    deleteDialogOpen.value = true;
  }
});

watch(
  () => deleteJob.progress.value?.status,
  (status) => {
    if (status === "completed") {
      toast.success(deleteJob.progress.value?.message || "Partners deleted");
      deleteDialogOpen.value = false;
      deleteJob.reset();
      refresh();
      if (tableRef.value) {
        tableRef.value.resetRowSelection();
      }
    }

    if (status === "failed") {
      toast.error("Failed to delete partners", {
        description: deleteJob.progress.value?.error_message || "An error occurred",
      });
      deleteJob.reset();
    }
  },
);

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    await deleteJob.startJob("/api/partners/bulk", {
      method: "DELETE",
      body: { ids },
    });
  } catch (err) {
    toast.error("Failed to delete partners", {
      description: err?.data?.message || err?.message,
    });
    deleteJob.reset();
  }
};

const handleDeleteSingleRow = async (partnerSlug) => {
  try {
    await client(`/api/partners/${partnerSlug}`, { method: "DELETE" });
    await refresh();
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success("Partner deleted");
  } catch (err) {
    toast.error("Failed to delete partner", {
      description: err?.data?.message || err?.message,
    });
  }
};

// Create Partner Dialog
const formDialogOpen = ref(false);
const createSaving = ref(false);
const createForm = reactive({
  name: "",
  website_url: "",
  description: "",
});
const logoFiles = ref([]);

const openCreateDialog = () => {
  createForm.name = "";
  createForm.website_url = "";
  createForm.description = "";
  logoFiles.value = [];
  formDialogOpen.value = true;
};

const handleCreate = async () => {
  createSaving.value = true;
  try {
    const body = {
      name: createForm.name,
      website_url: createForm.website_url || null,
      description: createForm.description || null,
    };

    const logoValue = logoFiles.value?.[0];
    if (logoValue && logoValue.startsWith("tmp-")) {
      body.tmp_partner_logo = logoValue;
    }

    await client("/api/partners", {
      method: "POST",
      body,
    });
    toast.success("Partner created");
    formDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Failed to create partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    createSaving.value = false;
  }
};

// Keyboard shortcuts
defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) {
        openCreateDialog();
      }
    },
    whenever: [computed(() => route.path === "/partners")],
  },
  meta_s: {
    handler: (e) => {
      e.preventDefault();
      if (formDialogOpen.value && !createSaving.value) {
        handleCreate();
      }
    },
  },
});

// Row Actions Component
const RowActions = defineComponent({
  props: {
    partner: { type: Object, required: true },
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
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `${baseUrl}/${props.partner.partner_slug}/edit`,
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
                  "This will move this partner to trash."
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
                          await handleDeleteSingleRow(props.partner.partner_slug);
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
