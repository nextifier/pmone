<template>
  <DialogResponsive v-model:open="open" dialog-max-width="48rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Settlement</h3>
        <p class="page-description">
          How much collected money is still pending transfer to the merchant bank, and when
          it is expected to land.
        </p>
      </div>

      <div class="mt-4 flex flex-wrap items-end gap-2">
        <div class="space-y-1.5">
          <Label class="text-muted-foreground text-xs tracking-tight">Date range</Label>
          <div class="w-52">
            <DatePicker mode="range" v-model="dateRange" size="sm" placeholder="Date range" />
          </div>
        </div>
      </div>

      <div class="mt-4">
        <div v-if="loading" class="space-y-2">
          <Skeleton class="h-20 w-full" />
          <Skeleton class="h-10 w-full" />
        </div>

        <div
          v-else-if="error"
          class="border-destructive/40 bg-destructive/10 flex items-start gap-x-2 rounded-lg border p-3"
        >
          <Icon name="hugeicons:alert-circle" class="text-destructive mt-0.5 size-4 shrink-0" />
          <p class="text-destructive text-sm tracking-tight">{{ error }}</p>
        </div>

        <div v-else-if="summary" class="space-y-4">
          <div class="grid grid-cols-2 gap-2">
            <div class="bg-muted/40 rounded-lg p-3">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Pending settlement
              </p>
              <p class="mt-0.5 text-lg font-semibold tracking-tighter">
                {{ formatPrice(summary.pending_amount) }}
              </p>
              <p class="text-muted-foreground text-xs tracking-tight">
                {{ summary.pending_count }} payment{{ summary.pending_count === 1 ? "" : "s" }}
              </p>
            </div>
            <div class="bg-muted/40 rounded-lg p-3">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Settled</p>
              <p class="mt-0.5 text-lg font-semibold tracking-tighter">
                {{ formatPrice(summary.settled_amount) }}
              </p>
              <p class="text-muted-foreground text-xs tracking-tight">
                {{ summary.settled_count }} payment{{ summary.settled_count === 1 ? "" : "s" }}
              </p>
            </div>
          </div>

          <p
            v-if="summary.truncated"
            class="text-warning-foreground bg-warning/10 border-warning/20 rounded-lg border px-3 py-2 text-xs tracking-tight sm:text-sm"
          >
            Too many transactions in this range - the summary is partial. Narrow the date range
            for a complete picture.
          </p>

          <div>
            <p class="text-sm font-medium tracking-tight">Upcoming settlements</p>
            <div
              v-if="!summary.upcoming.length"
              class="text-muted-foreground mt-2 rounded-lg border border-dashed py-6 text-center text-sm tracking-tight"
            >
              No pending settlements in this range.
            </div>
            <div v-else class="mt-2 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="whitespace-nowrap">Estimated date</TableHead>
                    <TableHead class="whitespace-nowrap text-right">Payments</TableHead>
                    <TableHead class="whitespace-nowrap text-right">Amount</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="(bucket, i) in summary.upcoming" :key="i">
                    <TableCell class="whitespace-nowrap">
                      {{ bucket.date ? formatDate(bucket.date) : "Not scheduled" }}
                    </TableCell>
                    <TableCell class="text-right">{{ bucket.count }}</TableCell>
                    <TableCell class="text-right font-medium whitespace-nowrap">
                      {{ formatPrice(bucket.amount) }}
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DialogResponsive>
</template>

<script setup>
import { Label } from "@/components/ui/label";
import { DatePicker } from "@/components/ui/date-picker";
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
const { formatPrice, formatDate } = useFormatters();

const dateRange = ref({
  start: new Date(Date.now() - 30 * 86400000),
  end: new Date(),
});
const summary = ref(null);
const loading = ref(false);
const error = ref(null);

function toYmd(date) {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

async function fetchSummary() {
  if (!props.gateway) return;

  loading.value = true;
  error.value = null;
  try {
    const res = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${props.gateway.id}/settlement`,
      {
        query: {
          date_from: toYmd(dateRange.value.start),
          date_to: toYmd(dateRange.value.end),
        },
      }
    );
    summary.value = res.data;
  } catch (e) {
    error.value = e?.data?.message || "Failed to load settlement summary.";
    summary.value = null;
  } finally {
    loading.value = false;
  }
}

watch(dateRange, () => {
  if (open.value) fetchSummary();
});

watch(open, (isOpen) => {
  if (isOpen && props.gateway) {
    summary.value = null;
    fetchSummary();
  }
});
</script>
