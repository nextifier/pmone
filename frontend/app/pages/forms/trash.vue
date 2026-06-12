<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
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
          <span>All forms</span>
        </nuxt-link>
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
    />
  </div>
</template>

<script setup>
import TrashRowActions from "@/components/form-builder/TrashRowActions.vue";
import { TableData } from "@/components/ui/table-data";
import { Badge } from "@/components/ui/badge";
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
