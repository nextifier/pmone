<template>
  <DialogResponsive v-model:open="open" dialog-max-width="56rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Transactions</h3>
        <p class="page-description">
          <span class="capitalize">{{ gateway?.provider }}</span>
          <template v-if="gateway?.label"> · {{ gateway.label }}</template>
        </p>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <div class="relative flex min-w-44 flex-1 items-center">
          <Icon
            name="lucide:search"
            class="text-muted-foreground pointer-events-none absolute left-3 size-4"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by reference"
            class="placeholder:text-muted-foreground h-8 w-full rounded-md border bg-transparent px-9 text-sm tracking-tight focus:outline-hidden"
          />
          <button
            v-if="searchQuery"
            type="button"
            aria-label="Clear search"
            class="bg-muted hover:bg-border absolute right-2 flex size-5 items-center justify-center rounded-full"
            @click="searchQuery = ''"
          >
            <Icon name="lucide:x" class="size-3 shrink-0" />
          </button>
        </div>

        <Select v-model="filterType">
          <SelectTrigger class="h-8 w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All types</SelectItem>
            <SelectItem value="payment">Payment</SelectItem>
            <SelectItem value="disbursement">Disbursement</SelectItem>
            <SelectItem value="refund">Refund</SelectItem>
            <SelectItem value="transfer">Transfer</SelectItem>
          </SelectContent>
        </Select>

        <Select v-model="filterStatus">
          <SelectTrigger class="h-8 w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="success">Success</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="failed">Failed</SelectItem>
            <SelectItem value="voided">Voided</SelectItem>
            <SelectItem value="reversed">Reversed</SelectItem>
          </SelectContent>
        </Select>

        <div class="w-52">
          <RangeCalendarPicker v-model="dateRange" placeholder="Date range" />
        </div>

        <Button
          variant="outline"
          size="sm"
          :disabled="exporting || loading || !transactions.length"
          @click="exportTransactions"
        >
          <Spinner v-if="exporting" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </Button>
      </div>

      <div class="mt-4 max-h-[55vh] overflow-y-auto">
        <div v-if="loading && !transactions.length" class="space-y-2">
          <Skeleton v-for="i in 6" :key="i" class="h-10 w-full" />
        </div>

        <div
          v-else-if="error && !transactions.length"
          class="border-destructive/40 bg-destructive/10 flex items-start gap-x-2 rounded-lg border p-3"
        >
          <Icon
            name="hugeicons:alert-circle"
            class="text-destructive mt-0.5 size-4 shrink-0"
          />
          <div class="flex-1 space-y-2">
            <p class="text-destructive text-sm tracking-tight">{{ error }}</p>
            <Button variant="outline" size="sm" @click="fetchPage({ reset: true })">
              Try again
            </Button>
          </div>
        </div>

        <Empty v-else-if="!filteredTransactions.length" class="border">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:invoice-01" />
            </EmptyMedia>
            <EmptyTitle>No transactions</EmptyTitle>
            <EmptyDescription>
              No transactions match the current filters.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <div v-else class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="whitespace-nowrap">Date</TableHead>
                <TableHead class="whitespace-nowrap">Type</TableHead>
                <TableHead class="whitespace-nowrap">Channel</TableHead>
                <TableHead class="whitespace-nowrap">Reference</TableHead>
                <TableHead class="whitespace-nowrap">Status</TableHead>
                <TableHead class="whitespace-nowrap text-right">Amount</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="tx in filteredTransactions" :key="tx.id">
                <TableCell class="text-muted-foreground whitespace-nowrap">
                  {{ formatDateTime(tx.created_at) }}
                </TableCell>
                <TableCell class="whitespace-nowrap capitalize">
                  {{ (tx.type || "").toLowerCase() }}
                </TableCell>
                <TableCell class="whitespace-nowrap">
                  <PaymentMethodBadge :channel="tx.channel_code" size="sm" icon-only />
                </TableCell>
                <TableCell>
                  <div
                    class="no-scrollbar scroll-fade-x max-w-40 overflow-x-scroll whitespace-nowrap"
                  >
                    {{ tx.reference || "-" }}
                  </div>
                </TableCell>
                <TableCell>
                  <Badge :variant="statusVariant(tx.status)" with-icon plain>
                    {{ statusLabel(tx.status) }}
                  </Badge>
                </TableCell>
                <TableCell class="text-right font-medium whitespace-nowrap">
                  {{ formatPrice(tx.amount) }}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>

          <div v-if="hasMore" class="mt-3 flex justify-center">
            <Button
              variant="outline"
              size="sm"
              :disabled="loadingMore"
              @click="fetchPage({ reset: false })"
            >
              <Spinner v-if="loadingMore" />
              Load more
            </Button>
          </div>
        </div>
      </div>
    </div>
  </DialogResponsive>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { PaymentMethodBadge } from "@/components/ui/payment-method-badge";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
  gateway: { type: Object, default: null },
});

const open = defineModel("open", { type: Boolean, default: false });

const client = useSanctumClient();
const { formatDateTime, formatPrice } = useFormatters();

const transactions = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const error = ref(null);
const hasMore = ref(false);
const nextCursor = ref(null);

const filterType = ref("all");
const filterStatus = ref("all");
const dateRange = ref({ start: null, end: null });
const searchQuery = ref("");
const exporting = ref(false);

function toYmd(date) {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function statusVariant(status) {
  const map = {
    SUCCESS: "success",
    PENDING: "warning",
    FAILED: "destructive",
    VOIDED: "destructive",
    REVERSED: "destructive",
  };
  return map[(status || "").toUpperCase()] || "muted";
}

function statusLabel(status) {
  const s = (status || "").toLowerCase();
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "-";
}

// Client-side substring search. Xendit's transactions API only supports exact
// reference_id matching, so partial search ("QUOK") is filtered over the loaded
// rows here instead.
const filteredTransactions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return transactions.value;
  return transactions.value.filter(
    (tx) =>
      (tx.reference || "").toLowerCase().includes(q) ||
      (tx.id || "").toLowerCase().includes(q)
  );
});

async function fetchPage({ reset }) {
  if (!props.gateway) return;

  if (reset) {
    loading.value = true;
    error.value = null;
  } else {
    loadingMore.value = true;
  }

  try {
    const query = { limit: 15 };
    if (filterType.value !== "all") query.type = filterType.value;
    if (filterStatus.value !== "all") query.status = filterStatus.value;
    const dateFrom = toYmd(dateRange.value.start);
    const dateTo = toYmd(dateRange.value.end);
    if (dateFrom) query.date_from = dateFrom;
    if (dateTo) query.date_to = dateTo;
    if (!reset && nextCursor.value) query.after_id = nextCursor.value;

    const res = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${props.gateway.id}/transactions`,
      { query }
    );

    const rows = res.data || [];
    transactions.value = reset ? rows : [...transactions.value, ...rows];
    hasMore.value = res.meta?.has_more || false;
    nextCursor.value = res.meta?.next_cursor || null;
  } catch (e) {
    const message = e?.data?.message || "Failed to load transactions.";
    if (reset) {
      error.value = message;
    } else {
      toast.error(message);
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function exportTransactions() {
  if (!props.gateway || exporting.value) return;

  exporting.value = true;
  try {
    const params = new URLSearchParams();
    if (filterType.value !== "all") params.append("type", filterType.value);
    if (filterStatus.value !== "all") params.append("status", filterStatus.value);
    const dateFrom = toYmd(dateRange.value.start);
    const dateTo = toYmd(dateRange.value.end);
    if (dateFrom) params.append("date_from", dateFrom);
    if (dateTo) params.append("date_to", dateTo);

    const blob = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${props.gateway.id}/transactions/export?${params.toString()}`,
      { responseType: "blob" }
    );
    const url = URL.createObjectURL(
      new Blob([blob], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      })
    );
    const a = document.createElement("a");
    a.href = url;
    a.download = `transactions_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Export downloaded");
  } catch (err) {
    toast.error("Export failed", { description: err?.data?.message || err?.message });
  } finally {
    exporting.value = false;
  }
}

watch([filterType, filterStatus, dateRange], () => {
  if (open.value) fetchPage({ reset: true });
});

watch(open, (isOpen) => {
  if (isOpen && props.gateway) {
    searchQuery.value = "";
    transactions.value = [];
    nextCursor.value = null;
    hasMore.value = false;
    fetchPage({ reset: true });
  }
});
</script>
