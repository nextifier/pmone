<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:resize-field-rectangle" class="size-5 sm:size-6" />
        <h1 class="page-title">Form Builder</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <nuxt-link
          v-if="canDelete"
          to="/forms/trash"
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
      clientOnly
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="forms"
      label="Forms"
      search-column="title"
      search-placeholder="Search forms"
      error-title="Error loading forms"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="canCreate"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
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
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4.5 translate-x-1/2 -translate-y-1/2 items-center justify-center text-xs font-medium tracking-tight tabular-nums"
              >
                {{ totalActiveFilters }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-48 p-3" align="start">
            <div class="space-y-4">
              <TableFilterSection
                title="Status"
                :options="[
                  { label: 'Draft', value: 'draft' },
                  { label: 'Published', value: 'published' },
                  { label: 'Closed', value: 'closed' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
              <TableFilterSection
                title="Active"
                :options="[
                  { label: 'Active', value: 'active' },
                  { label: 'Inactive', value: 'inactive' },
                ]"
                :selected="selectedActiveStatuses"
                @change="handleFilterChange('is_active', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <ConfirmDialog
          v-if="canDelete && selectedRows.length > 0"
          v-model:open="deleteDialogOpen"
          class="h-full"
          title="Delete selected forms?"
          :description="`This will move ${selectedRows.length} selected ${selectedRows.length === 1 ? 'form' : 'forms'} to trash. You can restore them later.`"
          confirm-label="Delete"
          variant="destructive"
          :pending="deletePending"
          @confirm="handleDeleteRows(selectedRows)"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="lucide:trash" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Delete</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-xs font-medium tabular-nums"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
        </ConfirmDialog>
      </template>
    </TableData>
  </div>
</template>

<script setup>
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import TableFilterSection from "@/components/TableFilterSection.vue";
import FormRowActions from "@/components/form-builder/FormRowActions.vue";
import FormTableItem from "@/components/form-builder/FormTableItem.vue";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { TableData } from "@/components/ui/table-data";
import { TableSwitch } from "@/components/ui/table-switch";
import { formStatusBadge } from "@/lib/formBuilderStatus";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["forms.read"],
  layout: "app",
});

defineOptions({
  name: "forms",
});

const title = "Form Builder";
const description = "Create and manage forms.";

usePageMeta(null, {
  title: title,
  description: description,
});

const { $dayjs } = useNuxtApp();
const { getRefreshSignal, clearRefreshSignal } = useDataRefresh();
const { hasPermission } = usePermission();

const canCreate = computed(() => hasPermission("forms.create"));
const canDelete = computed(() => hasPermission("forms.delete"));

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "created_at", desc: true }]);

const {
  data: formsResponse,
  pending,
  error,
  refresh: fetchForms,
} = await useLazySanctumFetch(() => `/api/forms?client_only=true`, {
  key: "forms-list",
  watch: false,
});

const data = computed(() => formsResponse.value?.data || []);
const meta = computed(
  () => formsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 }
);

const isPageActive = ref(true);
onActivated(async () => {
  isPageActive.value = true;
  const refreshSignal = getRefreshSignal("forms-list");
  if (refreshSignal > 0) {
    await fetchForms();
    clearRefreshSignal("forms-list");
  }
});
onDeactivated(() => {
  isPageActive.value = false;
});

const refresh = fetchForms;

// Toggle active status
const handleToggleStatus = async (form) => {
  const newStatus = !form.is_active;
  const originalStatus = form.is_active;

  form.is_active = newStatus;

  try {
    const client = useSanctumClient();
    const response = await client(`/api/forms/${form.slug}`, {
      method: "PUT",
      body: { is_active: newStatus },
    });

    if (response.data) {
      const updatedForm = data.value.find((f) => f.id === form.id);
      if (updatedForm) {
        updatedForm.is_active = response.data.is_active;
      }
    }

    toast.success(`Form ${newStatus ? "activated" : "deactivated"}`);
  } catch (error) {
    form.is_active = originalStatus;
    console.error("Failed to update form status:", error);
    toast.error("Failed to update status", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
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
    header: "Title",
    accessorKey: "title",
    cell: ({ row }) => h(FormTableItem, { form: row.original }),
    size: 320,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const title = row.original.title?.toLowerCase() || "";
      return title.includes(searchValue);
    },
  },
  {
    header: "Project",
    accessorKey: "project",
    cell: ({ row }) => {
      const project = row.original.project;
      if (!project?.name) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `/projects/${project.username}`,
          class: "text-muted-foreground text-sm tracking-tight transition hover:underline",
        },
        { default: () => project.name }
      );
    },
    size: 150,
    enableSorting: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      const badge = formStatusBadge(status);
      return h(
        Badge,
        { variant: badge.variant, icon: badge.icon },
        { default: () => status.charAt(0).toUpperCase() + status.slice(1) }
      );
    },
    size: 110,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Responses",
    accessorKey: "responses_count",
    cell: ({ row }) => {
      const count = row.getValue("responses_count") || 0;
      return h("div", { class: "text-sm tracking-tight tabular-nums" }, count.toLocaleString());
    },
    size: 80,
    enableSorting: true,
  },
  {
    header: "Active",
    accessorKey: "is_active",
    cell: ({ row }) => {
      const form = row.original;
      return h(TableSwitch, {
        modelValue: form.is_active,
        itemId: form.id,
        statusKey: "forms",
        "onUpdate:modelValue": () => handleToggleStatus(form),
      });
    },
    size: 80,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const isActive = row.getValue(columnId);
      return filterValue.some((value) => {
        if (value === "active") return isActive;
        if (value === "inactive") return !isActive;
        return false;
      });
    },
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return withDirectives(
        h("div", { class: "text-muted-foreground text-sm tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    header: "Created By",
    accessorKey: "creator.name",
    cell: ({ row }) => {
      const creator = row.original.creator;
      if (!creator) {
        return h("div", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      return h("div", { class: "text-sm tracking-tight overflow-hidden" }, creator.name);
    },
    size: 120,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(FormRowActions, {
        form: row.original,
        canDuplicate: canCreate.value,
        onDuplicate: handleDuplicate,
        onDelete: handleDeleteSingleRow,
      }),
    size: 60,
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

// Filter helpers (client-side filtering through TanStack column filters)
const getFilterValue = (columnId) => {
  if (tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedActiveStatuses = computed(() => getFilterValue("is_active"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedActiveStatuses.value.length
);

const handleFilterChange = (columnId, { checked, value }) => {
  const column = tableRef.value?.table?.getColumn(columnId);
  if (!column) return;

  const current = column.getFilterValue() ?? [];
  const updated = checked ? [...current, value] : current.filter((item) => item !== value);

  column.setFilterValue(updated.length > 0 ? updated : undefined);
  tableRef.value.table.setPageIndex(0);
};

// Duplicate handler
const handleDuplicate = async (form) => {
  try {
    const client = useSanctumClient();
    const response = await client(`/api/forms/${form.slug}/duplicate`, { method: "POST" });

    toast.success("Form duplicated");

    if (response.data?.slug) {
      navigateTo(`/forms/${response.data.slug}`);
    } else {
      await refresh();
    }
  } catch (error) {
    toast.error("Failed to duplicate form", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteSingleRow = async (form) => {
  try {
    const client = useSanctumClient();
    await client(`/api/forms/${form.slug}`, { method: "DELETE" });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success("Form moved to trash");
  } catch (error) {
    console.error("Failed to delete form:", error);
    toast.error("Failed to delete form", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

const handleDeleteRows = async (selectedRows) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();

    // Delete one by one since there's no bulk endpoint
    const promises = selectedRows.map((row) =>
      client(`/api/forms/${row.original.slug}`, { method: "DELETE" })
    );
    await Promise.all(promises);

    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(
      `${selectedRows.length} ${selectedRows.length === 1 ? "form" : "forms"} moved to trash`
    );
  } catch (error) {
    console.error("Failed to delete forms:", error);
    toast.error("Failed to delete forms", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) {
        navigateTo("/forms/create");
      }
    },
    whenever: [isPageActive],
  },
});
</script>
