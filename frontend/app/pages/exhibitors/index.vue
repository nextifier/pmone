<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:location-user-04" class="size-5 sm:size-6" />
        <h1 class="page-title">Exhibitor PICs</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <ImportDialog
          v-if="canCreate"
          title="Import Exhibitors"
          description="Upload an Excel file to import multiple exhibitors at once"
          template-endpoint="/api/users/import/template?default_role=exhibitor&filename_prefix=exhibitors"
          import-endpoint="/api/users/import"
          template-filename="exhibitors_import_template.xlsx"
          entity-label="Exhibitors"
          default-role="exhibitor"
          :extra-body="{ default_role: 'exhibitor' }"
          @imported="refresh"
        >
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </ImportDialog>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export {{ columnFilters?.length ? "selected" : "all" }}</span>
        </button>

        <nuxt-link
          v-if="canDelete"
          to="/exhibitors/trash"
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
      model="exhibitors"
      label="Exhibitor"
      search-column="name"
      search-placeholder="Search name, email, or username"
      error-title="Error loading exhibitors"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="canCreate"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #filters="{ table }">
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
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="material-symbols:verified" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Verify</span>
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
                Verify exhibitors?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will verify {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "exhibitor" : "exhibitors" }}.
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
          v-if="selectedRows.length > 0"
          v-model:open="unverifyDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon
                name="material-symbols:verified"
                class="text-muted-foreground size-4 shrink-0"
              />
              <span class="text-sm tracking-tight">Unverify</span>
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
                Unverify exhibitors?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will unverify {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "exhibitor" : "exhibitors" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="unverifyDialogOpen = false"
                  :disabled="unverifyPending"
                >
                  Cancel
                </button>
                <button
                  @click="handleUnverifyRows(selectedRows)"
                  :disabled="unverifyPending"
                  class="bg-muted-foreground hover:bg-muted-foreground/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="unverifyPending" class="size-4 text-white" />
                  <span v-else>Unverify</span>
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
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import TableSwitch from "@/components/TableSwitch.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import FilterSection from "@/components/user/FilterSection.vue";
import ImportDialog from "@/components/user/ImportDialog.vue";
import RowActions from "@/components/user/RowActions.vue";
import UserTableItem from "@/components/user/TableItem.vue";
import { useUserTable } from "@/composables/useUserTable";
import { resolveDirective, withDirectives } from "vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["users.read"],
  layout: "app",
});

defineOptions({
  name: "exhibitors",
});

usePageMeta(null, { title: "Exhibitors" });

const { hasPermission } = usePermission();

const canCreate = computed(() => hasPermission("users.create"));
const canDelete = computed(() => hasPermission("users.delete"));

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
  hasSelectedRows,
  clearSelection,
  getFilterValue,
  handleFilterChange,
  exportPending,
  handleExport,
  verifyDialogOpen,
  verifyPending,
  handleVerifyRows,
  unverifyDialogOpen,
  unverifyPending,
  handleUnverifyRows,
  deleteDialogOpen,
  deletePending,
  handleDeleteRows,
  $dayjs,
} = await useUserTable({
  fetchKey: "exhibitors-list",
  extraParams: { role: "exhibitor", with_brands_count: "1" },
  filterMapping: { roles: null },
  entityLabel: "Exhibitors",
});

// Filter computed values (no Roles filter for exhibitors)
const selectedStatuses = computed(() => getFilterValue("status"));
const selectedVerified = computed(() => getFilterValue("email_verified_at"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedVerified.value.length
);

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
    cell: ({ row }) => h(UserTableItem, { user: row.original }),
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
    header: "Brands",
    accessorKey: "brands_count",
    cell: ({ row }) => {
      const brandsCount = row.getValue("brands_count") || 0;
      const username = row.original.username;
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `/brands?user=${username}`,
          class: "text-sm tracking-tight hover:underline inline-flex items-center gap-x-1",
        },
        {
          default: () => [h("span", {}, brandsCount.toString())],
        }
      );
    },
    size: 80,
    enableSorting: true,
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
      const user = row.original;
      return h(TableSwitch, {
        modelValue: user.status === "active",
        itemId: user.id,
        statusKey: "users",
        "onUpdate:modelValue": () => handleToggleStatus(user),
      });
    },
    size: 80,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const status = row.getValue(columnId);
      return filterValue.includes(status);
    },
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
        return h("span", { class: "text-sm text-muted-foreground tracking-tight" }, "-");
      }

      return withDirectives(
        h(
          "div",
          { class: "text-sm text-muted-foreground tracking-tight" },
          $dayjs(lastSeen).fromNow()
        ),
        [[resolveDirective("tippy"), $dayjs(lastSeen).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
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
    cell: ({ row }) =>
      h(RowActions, {
        username: row.original.username,
        isVerified: !!row.original.email_verified_at,
        onRefresh: () => refresh(),
      }),
    size: 60,
    enableHiding: false,
  },
];
</script>
