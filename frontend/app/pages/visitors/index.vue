<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-row flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:user-multiple-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Visitors</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>
      </div>
    </div>

    <UserStatsHeader v-if="canViewSecurity" :params="{ role: 'user' }" />

    <TableData
      :clientOnly="clientOnly"
      ref="tableRef"
      :data="data"
      :columns="visibleColumns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="visitors"
      label="Visitor"
      search-column="name"
      search-placeholder="Search name, email, or username"
      error-title="Error loading visitors"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="false"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
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
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
              >
                {{ totalActiveFilters }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-48 p-3" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="['active', 'inactive']"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
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
          v-model:open="verifyDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="material-symbols:verified" label="Verify" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Verify visitors?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will verify {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "visitor" : "visitors" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="verifyDialogOpen = false"
                  :disabled="verifyPending"
                >
                  Cancel
                </button>
                <button
                  @click="handleVerifyRows(selectedRows)"
                  :disabled="verifyPending"
                  class="bg-info hover:bg-info/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="verifyPending" class="size-4 text-white" />
                  <span v-else>Verify</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>

        <DialogResponsive
          v-if="canSendEmails && selectedRows.length > 0"
          v-model:open="sendResetDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="hugeicons:key-01" label="Send reset" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Send password reset?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This emails a password reset link to {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "visitor" : "visitors" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="sendResetDialogOpen = false"
                  :disabled="sendResetPending"
                >
                  Cancel
                </button>
                <button
                  @click="handleSendResetRows(selectedRows)"
                  :disabled="sendResetPending"
                  class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="sendResetPending" class="size-4" />
                  <span v-else>Send reset</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>

        <DialogResponsive
          v-if="canDelete && selectedRows.length > 0"
          v-model:open="deleteDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="lucide:trash" label="Delete" destructive @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected {{ selectedRows.length === 1 ? "row" : "rows" }}.
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
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import TableSwitch from "@/components/TableSwitch.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import FilterSection from "@/components/user/FilterSection.vue";
import RowActions from "@/components/user/RowActions.vue";
import UserLastPageCell from "@/components/user/LastPageCell.vue";
import UserStatsHeader from "@/components/user/StatsHeader.vue";
import UserTableItem from "@/components/user/TableItem.vue";
import { useUserTable } from "@/composables/useUserTable";
import { resolveDirective, withDirectives } from "vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["users.read"],
  layout: "app",
});

defineOptions({
  name: "visitors",
});

usePageMeta(null, { title: "Visitors" });

const { hasPermission, isMaster } = usePermission();
const { currentUserId, selfPage } = useSelfCurrentPage();
const canDelete = computed(() => hasPermission("users.delete"));
const canSendEmails = computed(() => hasPermission("users.send_account_emails"));
const canViewSecurity = computed(() => hasPermission("users.view_security"));

const {
  columnFilters,
  pagination,
  sorting,
  tableRef,
  clientOnly,
  data,
  meta,
  pending,
  error,
  refresh,
  onPaginationUpdate,
  onSortingUpdate,
  onColumnFiltersUpdate,
  handleToggleStatus,
  getFilterValue,
  handleFilterChange,
  exportPending,
  handleExport,
  verifyDialogOpen,
  verifyPending,
  handleVerifyRows,
  deleteDialogOpen,
  deletePending,
  handleDeleteRows,
  sendResetDialogOpen,
  sendResetPending,
  handleSendResetRows,
  $dayjs,
} = await useUserTable({
  fetchKey: "visitors-list",
  extraParams: { role: "user" },
  filterMapping: { roles: null },
  clientOnly: false,
  defaultSorting: [{ id: "created_at", desc: true }],
  entityLabel: "Visitors",
});

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedVerified = computed(() => getFilterValue("email_verified_at"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedVerified.value.length
);

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
    cell: ({ row }) => h(UserTableItem, { user: row.original }),
    size: 280,
    enableHiding: false,
  },
  {
    header: "Verified",
    accessorKey: "email_verified_at",
    cell: ({ row }) => {
      const emailVerifiedAt = row.getValue("email_verified_at");
      const isVerified = !!emailVerifiedAt;
      const icon = h(resolveComponent("Icon"), {
        name: "material-symbols:verified",
        class: isVerified ? "text-info size-4.5 shrink-0" : "text-foreground/25 size-4.5 shrink-0",
      });
      return isVerified
        ? withDirectives(h("div", { class: "flex items-center" }, icon), [
            [resolveDirective("tippy"), $dayjs(emailVerifiedAt).format("MMMM D, YYYY [at] h:mm A")],
          ])
        : h("div", { class: "flex items-center" }, icon);
    },
    size: 80,
    enableSorting: true,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const user = row.original;
      return h(TableSwitch, {
        modelValue: user.status === "active",
        itemId: user.id,
        statusKey: "users",
        "onUpdate:modelValue": () => handleToggleStatus(user),
      });
    },
    size: 80,
    enableSorting: true,
  },
  {
    header: "Last Seen",
    accessorKey: "last_seen",
    cell: ({ row }) => {
      const isOnline = row.original.is_online;
      const lastSeen = row.getValue("last_seen");

      if (isOnline) {
        return h("div", { class: "flex items-center gap-x-1.5" }, [
          h("span", { class: "size-2 rounded-full bg-green-500" }),
          h(
            "span",
            { class: "text-sm tracking-tight text-green-600 dark:text-green-500" },
            "Online"
          ),
        ]);
      }

      if (!lastSeen) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }

      return withDirectives(
        h(
          "div",
          { class: "text-muted-foreground text-sm tracking-tight" },
          $dayjs(lastSeen).fromNow()
        ),
        [[resolveDirective("tippy"), $dayjs(lastSeen).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
    enableSorting: true,
  },
  {
    // Master-only: filtered out of `visibleColumns` for everyone else.
    id: "last_page",
    header: "Last page",
    enableSorting: false,
    cell: ({ row }) => {
      const isSelf = row.original.id === currentUserId.value;
      return h(UserLastPageCell, {
        user: row.original,
        page: isSelf ? selfPage() : row.original.last_page || null,
      });
    },
    size: 160,
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
    enableSorting: true,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(RowActions, {
        id: row.original.id,
        username: row.original.username,
        isVerified: !!row.original.email_verified_at,
        phone: row.original.phone,
        email: row.original.email,
        roles: row.original.roles,
        onRefresh: () => refresh(),
      }),
    size: 140,
    enableHiding: false,
  },
];

// The current-page column is master-only, mirroring Impersonate (RowActions).
const visibleColumns = computed(() =>
  columns.filter((column) => column.id !== "last_page" || isMaster.value)
);
</script>
