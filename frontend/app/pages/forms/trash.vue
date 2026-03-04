<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Forms Trash</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <nuxt-link
          to="/forms"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:note-edit" class="size-4 shrink-0" />
          <span>All Forms</span>
        </nuxt-link>
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
    />
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
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
const sorting = ref([{ id: "deleted_at", desc: true }]);
const clientOnly = ref(true);

const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    const sortField = sorting.value[0]?.id || "deleted_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort_by", sortField);
    params.append("sort_order", sortDirection);
  }

  return params.toString();
};

const {
  data: formsResponse,
  pending,
  error,
  refresh: fetchForms,
} = await useLazySanctumFetch(() => `/api/forms/trash?${buildQueryParams()}`, {
  key: "forms-trash-list",
  watch: false,
});

const data = computed(() => formsResponse.value?.data || []);
const meta = computed(
  () => formsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 }
);

const refresh = fetchForms;

const statusVariant = (status) => {
  switch (status) {
    case "draft":
      return "secondary";
    case "published":
      return "default";
    case "closed":
      return "destructive";
    default:
      return "outline";
  }
};

// Table columns
const columns = [
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
      return h(
        Badge,
        { variant: statusVariant(status) },
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
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
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
    cell: ({ row }) => h(RowActions, { form: row.original }),
    size: 60,
    enableHiding: false,
  },
];

const tableRef = ref();

// Restore & delete handlers
const handleRestoreSingleRow = async (formId) => {
  try {
    const client = useSanctumClient();
    await client(`/api/forms/trash/${formId}/restore`, { method: "POST" });
    await refresh();
    toast.success("Form restored successfully");
  } catch (error) {
    console.error("Failed to restore form:", error);
    toast.error("Failed to restore form", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

const handleDeleteSingleRow = async (formId) => {
  try {
    const client = useSanctumClient();
    await client(`/api/forms/trash/${formId}`, { method: "DELETE" });
    await refresh();
    toast.success("Form permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete form:", error);
    toast.error("Failed to permanently delete form", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    form: { type: Object, required: true },
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
            open: restoreDialogOpen.value,
            "onUpdate:open": (value) => (restoreDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Restore form?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will restore this form."
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
                          await handleRestoreSingleRow(props.form.id);
                          restoreDialogOpen.value = false;
                        } finally {
                          singleRestorePending.value = false;
                        }
                      },
                    },
                    singleRestorePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4" })
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
                  "This action can't be undone. This will permanently delete this form and all its responses."
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
                          await handleDeleteSingleRow(props.form.id);
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
</script>
