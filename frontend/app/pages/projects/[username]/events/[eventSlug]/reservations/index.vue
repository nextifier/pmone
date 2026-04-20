<template>
  <div class="space-y-6 pb-16">
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:calendar-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Reservations</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 items-end">
      <div class="space-y-1">
        <Label class="text-xs sm:text-sm tracking-tight">Search</Label>
        <Input v-model="filters.search" placeholder="Number, name, email..." class="w-60" />
      </div>
      <div class="space-y-1">
        <Label class="text-xs sm:text-sm tracking-tight">Status</Label>
        <Select v-model="filters.status">
          <SelectTrigger class="w-48">
            <SelectValue placeholder="All" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All</SelectItem>
            <SelectItem value="pending_payment">Pending Payment</SelectItem>
            <SelectItem value="paid">Paid</SelectItem>
            <SelectItem value="voucher_sent">Voucher Sent</SelectItem>
            <SelectItem value="expired">Expired</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
            <SelectItem value="refunded">Refunded</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="!items.length" class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight">
      No reservations found.
    </div>

    <div v-else class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm tracking-tight">
        <thead class="bg-muted">
          <tr class="text-left">
            <th class="px-3 py-2">Number</th>
            <th class="px-3 py-2">Guest</th>
            <th class="px-3 py-2">Hotel</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2 text-right">Total</th>
            <th class="px-3 py-2">Created</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in items" :key="r.id" class="border-t hover:bg-muted/50">
            <td class="px-3 py-2 font-mono text-xs sm:text-sm">{{ r.reservation_number }}</td>
            <td class="px-3 py-2">
              <div>{{ r.guest_name }}</div>
              <div class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ r.guest_email }}</div>
            </td>
            <td class="px-3 py-2">{{ r.hotel?.name || "-" }}</td>
            <td class="px-3 py-2">
              <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs sm:text-sm tracking-tight', statusBadge(r.status)]">
                {{ r.status_label }}
              </span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums">Rp {{ formatRupiah(r.total_amount) }}</td>
            <td class="px-3 py-2 text-muted-foreground">{{ formatDate(r.created_at) }}</td>
            <td class="px-3 py-2">
              <NuxtLink :to="`${eventBase}/reservations/${r.ulid}`" class="text-primary hover:underline text-xs sm:text-sm">View</NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex justify-center gap-2 text-sm tracking-tight">
      <button class="border rounded px-3 py-1 disabled:opacity-50" :disabled="meta.current_page <= 1" @click="page--">Previous</button>
      <span class="px-3 py-1">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="border rounded px-3 py-1 disabled:opacity-50" :disabled="meta.current_page >= meta.last_page" @click="page++">Next</button>
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["reservations.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, { title: computed(() => `Reservations · ${props.event?.title || "Event"}`) });

const client = useSanctumClient();

const filters = reactive({ search: "", status: "all" });
const page = ref(1);

const buildQuery = () => {
  const params = new URLSearchParams();
  params.append("page", page.value);
  params.append("per_page", "20");
  if (filters.search) params.append("filter_search", filters.search);
  if (filters.status && filters.status !== "all") {
    params.append("filter_status", filters.status);
  }
  return params.toString();
};

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/reservations?${buildQuery()}`,
  { key: () => `reservations-list-${props.event?.id}`, watch: false }
);

watch([filters, page], () => refresh(), { deep: true });

const items = computed(() => data.value?.data ?? []);
const meta = computed(() => data.value?.meta);

const statusBadge = (status) => {
  const map = {
    pending_payment: "bg-warning/15 text-warning-foreground",
    paid: "bg-info/15 text-info-foreground",
    voucher_sent: "bg-success/15 text-success-foreground",
    expired: "bg-muted text-muted-foreground",
    cancelled: "bg-destructive/15 text-destructive",
    refunded: "bg-destructive/15 text-destructive",
  };
  return map[status] || "bg-muted text-muted-foreground";
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" }) : "-";

const exportPending = ref(false);
const handleExport = async () => {
  exportPending.value = true;
  try {
    const params = new URLSearchParams();
    if (filters.search) params.append("filter_search", filters.search);
    if (filters.status && filters.status !== "all") {
      params.append("filter_status", filters.status);
    }
    const blob = await client(`/api/events/${props.event.id}/reservations/export?${params.toString()}`, { responseType: "blob" });
    const url = URL.createObjectURL(new Blob([blob], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" }));
    const a = document.createElement("a");
    a.href = url;
    a.download = `reservations_${new Date().toISOString().slice(0,10)}.xlsx`;
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
