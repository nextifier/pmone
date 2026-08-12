<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Forms Trash</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button variant="outline" size="sm" to="/forms">
          <Icon name="hugeicons:resize-field-rectangle" class="size-4 shrink-0" />
          <span>All forms</span>
        </Button>
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
      model="forms-trash"
      search-column="title"
      :show-add-button="false"
      search-placeholder="Search forms"
      error-title="Error loading trashed forms"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
      <template #actions="{ selectedRows }">
        <ResponsiveDialog
          v-if="selectedRows.length > 0"
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="hugeicons:undo-02" label="Restore" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Restore forms?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "form" : "forms" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <Button variant="outline" :disabled="restorePending" @click="restoreDialogOpen = false">
                  Cancel
                </Button>
                <Button :disabled="restorePending" @click="handleRestoreRows(selectedRows)">
                  <Spinner v-if="restorePending" class="size-4" />
                  <span>Restore</span>
                </Button>
              </div>
            </div>
          </template>
        </ResponsiveDialog>

        <ResponsiveDialog
          v-if="selectedRows.length > 0"
          v-model:open="deleteDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction
              icon="hugeicons:delete-01"
              label="Delete Permanently"
              destructive
              @click="open()"
            />
          </template>
          <template #default>
            <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">
                Are you absolutely sure?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This can't be undone. It permanently deletes {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "form" : "forms" }}, along with every response and
                field on {{ selectedRows.length === 1 ? "it" : "them" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <Button variant="outline" :disabled="deletePending" @click="deleteDialogOpen = false">
                  Cancel
                </Button>
                <Button variant="destructive" :disabled="deletePending" @click="handleDeleteRows(selectedRows)">
                  <Spinner v-if="deletePending" class="size-4" />
                  <span>Delete Permanently</span>
                </Button>
              </div>
            </div>
          </template>
        </ResponsiveDialog>
      </template>
    </TableData>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import TrashRowActions from "@/components/form-builder/TrashRowActions.vue";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { TableBulkAction, TableData } from "@/components/ui/table-data";
import { formStatusBadge } from "@/lib/formBuilderStatus";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["forms.delete"],
  layout: "app",
});

defineOptions({
  name: "forms-trash",
});

usePageMeta(null, {
  title: "Forms Trash",
});

const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "created_at", desc: true }]);

const {
  data: formsResponse,
  pending,
  error,
  refresh: fetchForms,
} = await useLazySanctumFetch(() => `/api/forms/trash?client_only=true`, {
  key: "forms-trash-list",
  watch: false,
});

const data = computed(() => formsResponse.value?.data || []);
const meta = computed(
  () => formsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 }
);

const refresh = fetchForms;

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
    cell: ({ row }) => {
      return h("div", { class: "text-sm font-medium tracking-tight" }, row.original.title);
    },
    size: 250,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const title = row.original.title?.toLowerCase() || "";
      return title.includes(searchValue);
    },
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
    size: 100,
  },
  {
    header: "Responses",
    accessorKey: "responses_count",
    cell: ({ row }) => {
      const count = row.getValue("responses_count") || 0;
      return h("div", { class: "text-sm tracking-tight tabular-nums" }, count.toLocaleString());
    },
    size: 80,
  },
  {
    header: "Created By",
    accessorKey: "creator.name",
    cell: ({ row }) => {
      const creator = row.original.creator;
      if (!creator) {
        return h("div", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      return h("div", { class: "text-sm tracking-tight" }, creator.name);
    },
    size: 120,
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
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(TrashRowActions, {
        form: row.original,
        onRestore: handleRestoreSingleRow,
        onDelete: handleDeleteSingleRow,
      }),
    size: 60,
    enableHiding: false,
  },
];

const tableRef = ref();

// Restore & delete handlers
const handleRestoreSingleRow = async (form) => {
  try {
    const client = useSanctumClient();
    await client(`/api/forms/trash/${form.id}/restore`, { method: "POST" });
    await refresh();
    toast.success("Form restored");
  } catch (error) {
    console.error("Failed to restore form:", error);
    toast.error("Failed to restore form", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

/* ----- Bulk actions ----- */
const restoreDialogOpen = ref(false);
const restorePending = ref(false);
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleRestoreRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  restorePending.value = true;
  try {
    const client = useSanctumClient();
    await client("/api/forms/trash/restore/bulk", { method: "POST", body: { ids } });
    await refresh();
    restoreDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(`${ids.length} form(s) restored`);
  } catch (error) {
    console.error("Failed to restore forms:", error);
    toast.error("Failed to restore forms", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  deletePending.value = true;
  try {
    const client = useSanctumClient();
    await client("/api/forms/trash/bulk", { method: "DELETE", body: { ids } });
    await refresh();
    deleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(`${ids.length} form(s) permanently deleted`);
  } catch (error) {
    console.error("Failed to permanently delete forms:", error);
    toast.error("Failed to permanently delete forms", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (form) => {
  try {
    const client = useSanctumClient();
    await client(`/api/forms/trash/${form.id}`, { method: "DELETE" });
    await refresh();
    toast.success("Form permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete form:", error);
    toast.error("Failed to permanently delete form", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};
</script>
