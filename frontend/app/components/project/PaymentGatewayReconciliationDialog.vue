<template>
  <DialogResponsive v-model:open="open" dialog-max-width="56rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Reconciliation</h3>
        <p class="page-description">
          Match successful <span class="capitalize">{{ gateway?.provider }}</span> payments
          against PM One reservations to surface missed webhooks and amount gaps.
        </p>
      </div>

      <div class="mt-4 flex flex-wrap items-end gap-2">
        <div class="space-y-1.5">
          <Label class="text-muted-foreground text-xs tracking-tight">Date range</Label>
          <div class="w-52">
            <RangeCalendarPicker v-model="dateRange" placeholder="Date range" />
          </div>
        </div>
        <Button
          size="sm"
          :disabled="loading || !dateRange.start || !dateRange.end"
          @click="runReconciliation"
        >
          <Spinner v-if="loading" />
          <Icon v-else name="hugeicons:checkmark-circle-02" class="size-4" />
          {{ loading ? "Reconciling..." : "Run reconciliation" }}
        </Button>
      </div>

      <div class="mt-4">
        <div
          v-if="!report && !loading && !error"
          class="text-muted-foreground rounded-lg border border-dashed py-10 text-center text-sm tracking-tight"
        >
          Pick a date range and run the reconciliation.
        </div>

        <div
          v-else-if="error"
          class="border-destructive/40 bg-destructive/10 flex items-start gap-x-2 rounded-lg border p-3"
        >
          <Icon name="hugeicons:alert-circle" class="text-destructive mt-0.5 size-4 shrink-0" />
          <p class="text-destructive text-sm tracking-tight">{{ error }}</p>
        </div>

        <div v-else-if="report" class="space-y-4">
          <div class="grid grid-cols-3 gap-2">
            <div class="bg-muted/40 rounded-lg p-3">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Checked</p>
              <p class="mt-0.5 text-lg font-semibold tracking-tighter">
                {{ report.transaction_count }}
              </p>
            </div>
            <div class="bg-muted/40 rounded-lg p-3">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Matched</p>
              <p class="mt-0.5 text-lg font-semibold tracking-tighter">
                {{ report.matched_count }}
              </p>
              <p class="text-muted-foreground text-xs tracking-tight">
                {{ formatPrice(report.matched_amount) }}
              </p>
            </div>
            <div class="bg-muted/40 rounded-lg p-3">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Discrepancies</p>
              <p
                class="mt-0.5 text-lg font-semibold tracking-tighter"
                :class="report.discrepancy_count ? 'text-destructive' : ''"
              >
                {{ report.discrepancy_count }}
              </p>
            </div>
          </div>

          <p
            v-if="report.truncated"
            class="text-warning-foreground bg-warning/10 border-warning/20 rounded-lg border px-3 py-2 text-xs tracking-tight sm:text-sm"
          >
            Too many transactions in this range - the report is partial. Narrow the date range
            for a complete reconciliation.
          </p>

          <div
            v-if="!report.discrepancy_count"
            class="border-success/30 bg-success/10 flex items-center gap-x-2 rounded-lg border p-3"
          >
            <Icon
              name="hugeicons:checkmark-circle-02"
              class="text-success-foreground size-4 shrink-0"
            />
            <p class="text-success-foreground text-sm tracking-tight">
              Every checked payment reconciles cleanly with its reservation.
            </p>
          </div>

          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="whitespace-nowrap">Issue</TableHead>
                  <TableHead class="whitespace-nowrap">Reference</TableHead>
                  <TableHead class="whitespace-nowrap text-right">Xendit</TableHead>
                  <TableHead class="whitespace-nowrap text-right">Reservation</TableHead>
                  <TableHead class="whitespace-nowrap">Detail</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(d, i) in report.discrepancies" :key="i">
                  <TableCell>
                    <span
                      class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium tracking-tight"
                      :class="typeClass(d.type)"
                    >
                      {{ typeLabel(d.type) }}
                    </span>
                  </TableCell>
                  <TableCell class="max-w-44 truncate font-mono text-xs sm:text-sm">
                    {{ d.reference_id || "-" }}
                  </TableCell>
                  <TableCell class="text-right whitespace-nowrap">
                    {{ formatPrice(d.transaction_amount) }}
                  </TableCell>
                  <TableCell class="text-right whitespace-nowrap">
                    <template v-if="d.reservation_amount != null">
                      {{ formatPrice(d.reservation_amount) }}
                      <span class="text-muted-foreground block text-xs tracking-tight">
                        {{ d.reservation_status }}
                      </span>
                    </template>
                    <span v-else class="text-muted-foreground">-</span>
                  </TableCell>
                  <TableCell class="text-muted-foreground max-w-64 text-xs tracking-tight sm:text-sm">
                    {{ d.note }}
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </div>
      </div>
    </div>
  </DialogResponsive>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

const props = defineProps({
  projectUsername: { type: String, required: true },
  gateway: { type: Object, default: null },
});

const open = defineModel("open", { type: Boolean, default: false });

const client = useSanctumClient();
const { formatPrice } = useFormatters();

const dateRange = ref({
  start: new Date(Date.now() - 7 * 86400000),
  end: new Date(),
});
const report = ref(null);
const loading = ref(false);
const error = ref(null);

function toYmd(date) {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function typeLabel(type) {
  return {
    orphan: "Orphan payment",
    status_mismatch: "Status mismatch",
    amount_mismatch: "Amount mismatch",
  }[type] || type;
}

function typeClass(type) {
  if (type === "orphan") {
    return "bg-destructive/10 text-destructive border-destructive/20";
  }
  return "bg-warning/10 text-warning-foreground border-warning/20";
}

async function runReconciliation() {
  if (!props.gateway || !dateRange.value.start || !dateRange.value.end || loading.value) {
    return;
  }

  loading.value = true;
  error.value = null;
  try {
    const res = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${props.gateway.id}/reconciliation`,
      {
        query: {
          date_from: toYmd(dateRange.value.start),
          date_to: toYmd(dateRange.value.end),
        },
      }
    );
    report.value = res.data;
  } catch (e) {
    error.value = e?.data?.message || "Failed to run reconciliation.";
    report.value = null;
  } finally {
    loading.value = false;
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    report.value = null;
    error.value = null;
  }
});
</script>
