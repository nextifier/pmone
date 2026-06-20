<template>
  <div class="space-y-6 pb-16">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:delete-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Attendees Trash</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button variant="outline" size="sm" as-child>
          <NuxtLink :to="`${eventBase}/attendees`">
            <Icon name="hugeicons:arrow-left-02" class="size-4 shrink-0" />
            <span>All Attendees</span>
          </NuxtLink>
        </Button>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :data="items"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="attendees-trash"
      label="Attendee"
      :client-only="false"
      :show-add-button="false"
      search-column="name"
      search-placeholder="Search name, email, order…"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="(v) => (pagination = v)"
      @update:sorting="(v) => (sorting = v)"
      @update:column-filters="(v) => (columnFilters = v)"
      @refresh="refresh"
    >
      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="selectedRows.length > 0"
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="hugeicons:undo-02" label="Restore" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">
                Restore attendees?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "attendee" : "attendees" }}.
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
            <TableBulkAction
              icon="hugeicons:delete-02"
              label="Delete Permanently"
              destructive
              @click="open()"
            />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">
                Are you absolutely sure?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "attendee" : "attendees" }}.
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
                  @click="handleForceDeleteRows(selectedRows)"
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Spinner } from "@/components/ui/spinner";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  computed,
  defineComponent,
  h,
  ref,
  resolveComponent,
  resolveDirective,
  watch,
  withDirectives,
} from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["attendees.delete"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const { $dayjs } = useNuxtApp();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, {
  title: computed(() => `Trash · Attendees · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

const appendFilters = (params) => {
  const search = columnFilters.value.find((f) => f.id === "name");
  if (search?.value) params.append("filter_search", search.value);
};

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  appendFilters(params);

  const sortField = sorting.value[0]?.id || "deleted_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

const { data, pending, error, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/attendees/trash?${buildQueryParams()}`,
  {
    key: () => `attendees-trash-${props.event?.id}`,
    watch: false,
  }
);

const items = computed(() => data.value?.data ?? []);
const meta = computed(
  () =>
    data.value?.meta || {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    }
);

watch([columnFilters, sorting, pagination], () => refresh(), { deep: true });

const tableRef = ref();

const restoreDialogOpen = ref(false);
const restorePending = ref(false);

const handleRestoreRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    restorePending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/trash/restore/bulk`, {
      method: "POST",
      body: { ids },
    });
    await refresh();
    restoreDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendees restored", {
      description: `${result.restored_count} attendee(s) restored`,
    });
  } catch (err) {
    toast.error("Failed to restore attendees", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

const handleRestoreSingleRow = async (id) => {
  try {
    restorePending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/trash/${id}/restore`, {
      method: "POST",
    });
    await refresh();
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendee restored");
  } catch (err) {
    toast.error("Failed to restore attendee", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleForceDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/trash/bulk`, {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendees permanently deleted", {
      description: `${result.deleted_count} attendee(s) permanently deleted`,
    });
  } catch (err) {
    toast.error("Failed to permanently delete attendees", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleForceDeleteSingleRow = async (id) => {
  try {
    deletePending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/trash/${id}`, {
      method: "DELETE",
    });
    await refresh();
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendee permanently deleted");
  } catch (err) {
    toast.error("Failed to permanently delete attendee", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const RowActions = defineComponent({
  props: { attendee: { type: Object, required: true } },
  setup(p) {
    const restoreOpen = ref(false);
    const deleteOpen = ref(false);
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
                { align: "end", class: "w-44 p-1" },
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
                                onClick: () => (restoreOpen.value = true),
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
                                onClick: () => (deleteOpen.value = true),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "lucide:trash",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "Delete Permanently"),
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
            open: restoreOpen.value,
            "onUpdate:open": (value) => (restoreOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Restore attendee?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will restore this attendee."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (restoreOpen.value = false),
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
                          await handleRestoreSingleRow(p.attendee.id);
                          restoreOpen.value = false;
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
            open: deleteOpen.value,
            "onUpdate:open": (value) => (deleteOpen.value = value),
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
                  "This action can't be undone. This will permanently delete this attendee."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (deleteOpen.value = false),
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
                          await handleForceDeleteSingleRow(p.attendee.id);
                          deleteOpen.value = false;
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
    cell: ({ row }) =>
      h("div", { class: "min-w-0 flex flex-col gap-0.5" }, [
        h("div", { class: "text-sm tracking-tight truncate" }, row.original.name || "Unnamed"),
        h(
          "div",
          { class: "text-muted-foreground text-xs tracking-tight truncate" },
          row.original.email || "-"
        ),
      ]),
    size: 230,
    enableHiding: false,
  },
  {
    header: "Ticket",
    accessorKey: "ticket",
    enableSorting: false,
    cell: ({ row }) =>
      h("span", { class: "text-sm tracking-tight" }, row.original.ticket?.title || "-"),
    size: 200,
  },
  {
    header: "Order #",
    id: "order_number",
    enableSorting: false,
    cell: ({ row }) =>
      row.original.order?.number
        ? h(
            "span",
            { class: "font-mono text-xs sm:text-sm tracking-tight" },
            row.original.order.number
          )
        : h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-"),
    size: 165,
  },
  {
    header: "Check-in",
    accessorKey: "is_checked_in",
    cell: ({ row }) =>
      h(
        Badge,
        {
          variant: row.original.is_checked_in ? "success" : "muted",
          withIcon: true,
          plain: true,
        },
        { default: () => (row.original.is_checked_in ? "Checked in" : "Not checked in") }
      ),
    size: 140,
  },
  {
    header: "Deleted",
    accessorKey: "deleted_at",
    cell: ({ row }) => {
      const date = row.getValue("deleted_at");
      if (!date) return h("span", { class: "text-muted-foreground" }, "-");
      return withDirectives(
        h(
          "div",
          { class: "text-muted-foreground text-sm tracking-tight" },
          $dayjs(date).fromNow()
        ),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 120,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(
        resolveComponent("ClientOnly"),
        {},
        { default: () => h(RowActions, { attendee: row.original }) }
      ),
    size: 56,
    enableHiding: false,
  },
];
</script>
