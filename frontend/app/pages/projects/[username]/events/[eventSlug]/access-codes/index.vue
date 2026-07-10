<template>
  <div class="space-y-6 pb-16">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:ticket-star" class="size-5 sm:size-6" />
        <h1 class="page-title">Ticket Access Codes</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button v-if="canExport" variant="outline" size="sm" :disabled="exportPending" @click="handleExport">
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </Button>
        <Button v-if="canGenerate" size="sm" @click="generateOpen = true">
          <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
          <span>Generate</span>
        </Button>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="showEmptyState"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6"><Icon name="hugeicons:ticket-star" /></div>
        <div><Icon name="hugeicons:mail-01" /></div>
        <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:lock-key" /></div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No access codes yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Generate shared or invitation codes to gate VIP, press, presale or sponsor tickets.
        </p>
        <div v-if="canGenerate" class="pt-2">
          <Button size="sm" @click="generateOpen = true">
            <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
            <span>Generate codes</span>
          </Button>
        </div>
      </div>
    </div>

    <TableData
      v-else
      ref="tableRef"
      :data="items"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="access_codes"
      label="Access code"
      :client-only="false"
      :show-add-button="false"
      search-column="code"
      search-placeholder="Search code, email, phone"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="(v) => (pagination = v)"
      @update:sorting="(v) => (sorting = v)"
      @update:column-filters="(v) => (columnFilters = v)"
      @refresh="refresh"
    >
      <template #filters>
        <ClientOnly>
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
            <PopoverContent class="w-auto min-w-52 space-y-4 p-3" align="start">
              <div v-for="group in filterGroups" :key="group.id" class="space-y-2">
                <div class="text-muted-foreground text-xs font-medium">{{ group.label }}</div>
                <div class="space-y-2">
                  <div v-for="opt in group.options" :key="opt.value" class="flex items-center gap-2">
                    <Checkbox
                      :id="`ac-${group.id}-${opt.value}`"
                      :model-value="selectedFilter(group.id).includes(opt.value)"
                      @update:model-value="(c) => handleFilterToggle(group.id, { checked: !!c, value: opt.value })"
                    />
                    <Label :for="`ac-${group.id}-${opt.value}`" class="grow cursor-pointer font-normal tracking-tight">
                      {{ opt.label }}
                    </Label>
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </ClientOnly>
      </template>
    </TableData>

    <AccessCodeBatchGenerateDialog
      v-if="event?.id"
      v-model:open="generateOpen"
      :event="event"
      @generated="refresh"
    />

    <AccessCodeRedemptionsDialog
      v-if="event?.id"
      v-model:open="redemptionsOpen"
      :event="event"
      :code="redemptionsCode"
    />
  </div>
</template>

<script setup>
import AccessCodeBatchGenerateDialog from "@/components/accessCode/AccessCodeBatchGenerateDialog.vue";
import AccessCodeRedemptionsDialog from "@/components/accessCode/AccessCodeRedemptionsDialog.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Spinner } from "@/components/ui/spinner";
import { TableData } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { computed, defineComponent, h, ref, resolveComponent, resolveDirective, watch, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["access_codes.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const { $dayjs } = useNuxtApp();

usePageMeta(null, {
  title: computed(() => `Access Codes · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canGenerate = computed(() => hasPermission("access_codes.generate"));
const canRevoke = computed(() => hasPermission("access_codes.revoke"));
const canDelete = computed(() => hasPermission("access_codes.delete"));
const canExport = computed(() => hasPermission("access_codes.export"));
const canSendInvites = computed(() => hasPermission("access_codes.send_invites"));

const kindOptions = [
  { label: "Shared", value: "shared" },
  { label: "Invitation", value: "invitation" },
];
const statusOptions = [
  { label: "Active", value: "active" },
  { label: "Revoked", value: "revoked" },
];

const filterParams = [
  { id: "kind", param: "kind" },
  { id: "status", param: "status" },
];

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 25 });
const sorting = ref([{ id: "created_at", desc: true }]);

const selectedFilter = (id) => {
  const f = columnFilters.value.find((x) => x.id === id);
  return Array.isArray(f?.value) ? f.value : [];
};

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const search = columnFilters.value.find((f) => f.id === "code");
  if (search?.value) params.append("search", search.value);

  for (const { id, param } of filterParams) {
    const values = selectedFilter(id);
    if (values.length) params.append(param, values[0]);
  }

  return params.toString();
};

const { data, pending, error, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/access-codes?${buildQueryParams()}`,
  {
    key: () => `access-codes-list-${props.event?.id}`,
    watch: false,
  }
);

const items = computed(() => data.value?.data ?? []);
const meta = computed(
  () => data.value?.meta || { current_page: 1, last_page: 1, per_page: 25, total: 0 }
);

const showEmptyState = computed(
  () => !pending.value && !error.value && items.value.length === 0 && columnFilters.value.length === 0
);

watch([columnFilters, sorting, pagination], () => refresh(), { deep: true });

const filterGroups = [
  { id: "kind", label: "Kind", options: kindOptions },
  { id: "status", label: "Status", options: statusOptions },
];

const totalActiveFilters = computed(() =>
  filterParams.reduce((sum, { id }) => sum + selectedFilter(id).length, 0)
);

const handleFilterToggle = (id, { checked, value }) => {
  const current = selectedFilter(id);
  const updated = checked ? [...current, value] : current.filter((v) => v !== value);
  const existingIndex = columnFilters.value.findIndex((f) => f.id === id);
  if (updated.length) {
    if (existingIndex >= 0) columnFilters.value[existingIndex].value = updated;
    else columnFilters.value.push({ id, value: updated });
  } else if (existingIndex >= 0) {
    columnFilters.value.splice(existingIndex, 1);
  }
  pagination.value.pageIndex = 0;
};

const generateOpen = ref(false);
const redemptionsOpen = ref(false);
const redemptionsCode = ref(null);
const tableRef = ref();

function openRedemptions(code) {
  redemptionsCode.value = code;
  redemptionsOpen.value = true;
}

async function revoke(code) {
  try {
    await client(`/api/events/${props.event.id}/access-codes/${code.ulid}/revoke`, { method: "POST" });
    await refresh();
    toast.success("Access code revoked");
  } catch (err) {
    toast.error("Could not revoke code", { description: err?.data?.message });
  }
}

async function sendInvite(code) {
  try {
    await client(`/api/events/${props.event.id}/access-codes/${code.ulid}/send-invite`, { method: "POST" });
    toast.success("Invite queued for delivery");
  } catch (err) {
    toast.error("Could not send invite", { description: err?.data?.message });
  }
}

async function destroy(code) {
  try {
    await client(`/api/events/${props.event.id}/access-codes/${code.ulid}`, { method: "DELETE" });
    await refresh();
    toast.success("Access code deleted");
  } catch (err) {
    toast.error("Could not delete code", { description: err?.data?.message });
  }
}

const priceEffectLabel = {
  none: "Gate only",
  set_price: "Set price",
  percentage: "% off",
  amount: "Amount off",
};

const RowActions = defineComponent({
  props: { code: { type: Object, required: true } },
  setup(p) {
    return () =>
      h("div", { class: "flex justify-end" }, [
        h(Popover, {}, {
          default: () => [
            h(PopoverTrigger, { asChild: true }, {
              default: () =>
                h("button", {
                  class: "hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md",
                }, [h(resolveComponent("Icon"), { name: "lucide:ellipsis", class: "size-4" })]),
            }),
            h(PopoverContent, { align: "end", class: "w-44 p-1" }, {
              default: () =>
                h("div", { class: "flex flex-col" }, [
                  h(PopoverClose, { asChild: true }, {
                    default: () =>
                      h("button", {
                        class: "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                        onClick: () => openRedemptions(p.code),
                      }, [
                        h(resolveComponent("Icon"), { name: "hugeicons:chart-histogram", class: "size-4 shrink-0" }),
                        h("span", {}, "Redemptions"),
                      ]),
                  }),
                  canSendInvites.value && (p.code.bind_email || p.code.bind_phone)
                    ? h(PopoverClose, { asChild: true }, {
                        default: () =>
                          h("button", {
                            class: "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                            onClick: () => sendInvite(p.code),
                          }, [
                            h(resolveComponent("Icon"), { name: "hugeicons:mail-send-01", class: "size-4 shrink-0" }),
                            h("span", {}, "Send invite"),
                          ]),
                      })
                    : null,
                  canRevoke.value && p.code.status === "active"
                    ? h(PopoverClose, { asChild: true }, {
                        default: () =>
                          h("button", {
                            class: "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                            onClick: () => revoke(p.code),
                          }, [
                            h(resolveComponent("Icon"), { name: "hugeicons:cancel-circle", class: "size-4 shrink-0" }),
                            h("span", {}, "Revoke"),
                          ]),
                      })
                    : null,
                  canDelete.value
                    ? h(PopoverClose, { asChild: true }, {
                        default: () =>
                          h("button", {
                            class: "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                            onClick: () => destroy(p.code),
                          }, [
                            h(resolveComponent("Icon"), { name: "lucide:trash", class: "size-4 shrink-0" }),
                            h("span", {}, "Delete"),
                          ]),
                      })
                    : null,
                ]),
            }),
          ],
        }),
      ]);
  },
});

const columns = [
  {
    header: "Code",
    accessorKey: "code",
    enableSorting: false,
    cell: ({ row }) => {
      const c = row.original;
      return h("div", { class: "min-w-0 flex flex-col gap-0.5" }, [
        h("div", { class: "font-mono text-sm font-medium tracking-tight truncate" }, c.code),
        c.bind_email || c.bind_phone
          ? h("div", { class: "text-muted-foreground text-xs tracking-tight truncate" }, c.bind_email || c.bind_phone)
          : null,
      ]);
    },
    size: 200,
  },
  {
    header: "Kind",
    accessorKey: "kind",
    enableSorting: false,
    cell: ({ row }) =>
      h(Badge, { variant: "muted", plain: true }, { default: () => (row.original.kind === "invitation" ? "Invitation" : "Shared") }),
    size: 110,
  },
  {
    header: "Unlocks",
    id: "unlocks",
    enableSorting: false,
    cell: ({ row }) =>
      h("span", { class: "text-muted-foreground text-sm tracking-tight" }, `${row.original.unlocks_count ?? 0} ticket(s)`),
    size: 110,
  },
  {
    header: "Price effect",
    accessorKey: "price_effect",
    enableSorting: false,
    cell: ({ row }) => {
      const e = row.original.price_effect;
      if (!e || e === "none") return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      return h(Badge, { variant: "info", plain: true }, { default: () => priceEffectLabel[e] || e });
    },
    size: 120,
  },
  {
    header: "Usage",
    id: "usage",
    enableSorting: false,
    cell: ({ row }) => {
      const c = row.original;
      const max = c.max_uses === null || c.max_uses === undefined ? "∞" : c.max_uses;
      return h("span", { class: "text-sm tracking-tight tabular-nums" }, `${c.used_count} / ${max}`);
    },
    size: 90,
  },
  {
    header: "Status",
    accessorKey: "status",
    enableSorting: false,
    cell: ({ row }) =>
      h(Badge, { variant: row.original.status === "active" ? "success" : "destructive", plain: true }, {
        default: () => (row.original.status === "active" ? "Active" : "Revoked"),
      }),
    size: 100,
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.original.created_at;
      if (!date) return h("span", { class: "text-muted-foreground" }, "-");
      return withDirectives(
        h("div", { class: "text-muted-foreground text-sm tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 110,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(resolveComponent("ClientOnly"), {}, { default: () => h(RowActions, { code: row.original }) }),
    size: 56,
    enableHiding: false,
  },
];

const exportPending = ref(false);
const handleExport = async () => {
  exportPending.value = true;
  try {
    const blob = await client(`/api/events/${props.event.id}/access-codes/export`, { responseType: "blob" });
    const url = URL.createObjectURL(
      new Blob([blob], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" })
    );
    const a = document.createElement("a");
    a.href = url;
    a.download = `access_codes_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Export downloaded");
  } catch (err) {
    toast.error("Export failed", { description: err?.data?.message || err?.message });
  } finally {
    exportPending.value = false;
  }
};
</script>
