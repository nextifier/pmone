<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">User Trash</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <nuxt-link
          to="/users"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:user-group" class="size-4 shrink-0" />
          <span>All Users</span>
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
      model="users-trash"
      search-column="name"
      :show-add-button="false"
      search-placeholder="Search name, email, or username"
      error-title="Error loading trashed users"
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
                :options="['active', 'inactive']"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
              <div class="border-t" />
              <FilterSection
                title="Roles"
                :options="['master', 'admin', 'staff', 'writer', 'user']"
                :selected="selectedRoles"
                @change="handleFilterChange('roles', $event)"
              />
              <div class="border-t" />
              <FilterSection
                title="Verified"
                :options="[
                  { label: 'Verified', value: 'true' },
                  { label: 'Unverified', value: 'false' },
                ]"
                :selected="selectedVerified"
                @change="handleFilterChange('email_verified_at', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="selectedRows.length > 0"
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="hugeicons:undo-02" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Restore</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Restore users?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "user" : "users" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="restoreDialogOpen = false"
                  :disabled="restorePending"
                >
                  Cancel
                </button>
                <button
                  @click="handleRestoreRows(selectedRows)"
                  :disabled="restorePending"
                  class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="restorePending" class="size-4 text-white" />
                  <span v-else>Restore</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>

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
              <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Delete Permanently</span>
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
                Are you absolutely sure?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "user" : "users" }}.
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
                  <span v-else>Delete Permanently</span>
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
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import UserProfile from "@/components/user/Profile.vue";
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

defineOptions({
  name: "users-trash",
});

usePageMeta("userTrash");

const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

// Data state
// Client-only mode flag (true = client-side pagination, false = server-side)
const clientOnly = ref(true);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    // Server-side mode: add pagination, filters, and sorting
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    // Filters
    const filters = {
      name: "filter.search",
      status: "filter.status",
      roles: "filter.role",
      email_verified_at: "filter.verified",
    };

    Object.entries(filters).forEach(([columnId, paramKey]) => {
      const filter = columnFilters.value.find((f) => f.id === columnId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, value);
      }
    });

    // Sorting
    const sortField = sorting.value[0]?.id || "deleted_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch trashed users with lazy loading
const {
  data: usersResponse,
  pending,
  error,
  refresh: fetchUsers,
} = await useLazySanctumFetch(() => `/api/users/trash?${buildQueryParams()}`, {
  key: "users-trash-list",
  watch: clientOnly.value ? [] : [columnFilters, sorting, pagination],
  immediate: !clientOnly.value,
});

const data = computed(() => usersResponse.value?.data || []);
const meta = computed(() => usersResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 });

const refresh = fetchUsers;

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
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) => h(UserProfile, { user: row.original }),
    size: 280,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const name = row.original.name?.toLowerCase() || "";
      const email = row.original.email?.toLowerCase() || "";
      const username = row.original.username?.toLowerCase() || "";
      return (
        name.includes(searchValue) || email.includes(searchValue) || username.includes(searchValue)
      );
    },
  },
  {
    header: "Roles",
    accessorKey: "roles",
    cell: ({ row }) => {
      const roles = row.getValue("roles") || [];
      return h("div", { class: "text-sm tracking-tight capitalize" }, roles.join(", "));
    },
    size: 80,
    enableSorting: true,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const userRoles = row.getValue(columnId) || [];
      return filterValue.some((role) => userRoles.includes(role));
    },
  },
  {
    header: "Verified",
    accessorKey: "email_verified_at",
    cell: ({ row }) => {
      const emailVerifiedAt = row.getValue("email_verified_at");
      const isVerified = !!emailVerifiedAt;
      const icon = h(resolveComponent("Icon"), {
        name: "material-symbols:verified",
        class: isVerified ? "text-info size-4.5 shrink-0" : "text-primary/25 size-4.5 shrink-0",
      });
      return isVerified
        ? withDirectives(h("div", { class: "flex items-center" }, icon), [
            [resolveDirective("tippy"), $dayjs(emailVerifiedAt).format("MMMM D, YYYY [at] h:mm A")],
          ])
        : h("div", { class: "flex items-center" }, icon);
    },
    size: 80,
    enableSorting: true,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const isVerified = !!row.getValue(columnId);
      return filterValue.some((value) => {
        if (value === "true") return isVerified;
        if (value === "false") return !isVerified;
        return false;
      });
    },
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      const statusColors = {
        active: "bg-success",
        inactive: "bg-destructive",
        pending: "bg-warning",
      };
      return h("div", { class: "flex items-center gap-x-1.5 capitalize text-sm tracking-tight" }, [
        h("span", { class: ["rounded-full size-2", statusColors[status.toLowerCase()]] }),
        status,
      ]);
    },
    size: 80,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const status = row.getValue(columnId);
      return filterValue.includes(status);
    },
  },
  {
    header: "Deleted By",
    accessorKey: "deleter",
    cell: ({ row }) => {
      const deleter = row.getValue("deleter");
      if (!deleter) {
        return h("div", { class: "text-sm text-muted-foreground tracking-tight" }, "-");
      }
      return h("div", { class: "text-sm tracking-tight" }, deleter.name);
    },
    size: 120,
  },
  {
    header: "Deleted At",
    accessorKey: "deleted_at",
    cell: ({ row }) => {
      const date = row.getValue("deleted_at");
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
    cell: ({ row }) => h(RowActions, { userId: row.original.id }),
    size: 60,
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

// Filter helpers - handle both client and server mode
const getFilterValue = (columnId) => {
  if (clientOnly.value && tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedRoles = computed(() => getFilterValue("roles"));
const selectedVerified = computed(() => getFilterValue("email_verified_at"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedRoles.value.length + selectedVerified.value.length
);

const handleFilterChange = (columnId, { checked, value }) => {
  if (clientOnly.value && tableRef.value?.table) {
    // Client-side mode: use table instance
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    // Reset to first page when filter changes
    tableRef.value.table.setPageIndex(0);
  } else {
    // Server-side mode: update columnFilters ref
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
    // Reset to first page when filter changes (server-side)
    pagination.value.pageIndex = 0;
  }
};

// Restore handlers
const restoreDialogOpen = ref(false);
const restorePending = ref(false);
const handleRestoreRows = async (selectedRows) => {
  const userIds = selectedRows.map((row) => row.original.id);
  try {
    restorePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/users/trash/restore/bulk", {
      method: "POST",
      body: { ids: userIds },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value?.table) {
      tableRef.value.table.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "Users restored successfully", {
      description:
        response.errors?.length > 0
          ? `${response.restored_count} restored, ${response.errors.length} failed`
          : `${response.restored_count} user(s) restored`,
    });
  } catch (error) {
    console.error("Failed to restore users:", error);
    toast.error("Failed to restore users", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

const handleRestoreSingleRow = async (userId) => {
  try {
    restorePending.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/users/trash/${userId}/restore`, { method: "POST" });
    await refresh();

    // Reset row selection after restore
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "User restored successfully");
  } catch (error) {
    console.error("Failed to restore user:", error);
    toast.error("Failed to restore user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);
const handleDeleteRows = async (selectedRows) => {
  const userIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/users/trash/bulk", {
      method: "DELETE",
      body: { ids: userIds },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value?.table) {
      tableRef.value.table.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "Users permanently deleted", {
      description:
        response.errors?.length > 0
          ? `${response.deleted_count} deleted, ${response.errors.length} failed`
          : `${response.deleted_count} user(s) permanently deleted`,
    });
  } catch (error) {
    console.error("Failed to permanently delete users:", error);
    toast.error("Failed to permanently delete users", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (userId) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/users/trash/${userId}`, { method: "DELETE" });
    await refresh();

    // Reset row selection after delete
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "User permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete user:", error);
    toast.error("Failed to permanently delete user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    userId: { type: Number, required: true },
  },
  setup(props) {
    const restoreDialogOpen = ref(false);
    const deleteDialogOpen = ref(false);
    const singleRestorePending = ref(false);
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
                              "button",
                              {
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: () => (restoreDialogOpen.value = true),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "lucide:undo-2",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "Restore"),
                              ]
                            ),
                        }
                      ),
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
                                onClick: () => (deleteDialogOpen.value = true),
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
                    ]),
                }
              ),
            ],
          }
        ),
        h(
          DialogResponsive,
          {
            open: restoreDialogOpen.value,
            "onUpdate:open": (value) => (restoreDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Restore user?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will restore this user."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (restoreDialogOpen.value = false),
                      disabled: singleRestorePending.value,
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50",
                      disabled: singleRestorePending.value,
                      onClick: async () => {
                        singleRestorePending.value = true;
                        try {
                          await handleRestoreSingleRow(props.userId);
                          restoreDialogOpen.value = false;
                        } finally {
                          singleRestorePending.value = false;
                        }
                      },
                    },
                    singleRestorePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Restore"
                  ),
                ]),
              ]),
          }
        ),
        h(
          DialogResponsive,
          {
            open: deleteDialogOpen.value,
            "onUpdate:open": (value) => (deleteDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you absolutely sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This action can't be undone. This will permanently delete this user."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (deleteDialogOpen.value = false),
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
                          await handleDeleteSingleRow(props.userId);
                          deleteDialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Delete Permanently"
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
