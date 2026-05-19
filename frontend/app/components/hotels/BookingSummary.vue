<script setup>
import { Button } from "@/components/ui/button";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { useBookingStore } from "@/stores/booking";
import { computed, ref } from "vue";

const bookingStore = useBookingStore();
const promoCode = computed(() => bookingStore.promoCode);
const promoDiscount = computed(() => Number(bookingStore.promoPreview?.discount_amount ?? 0));

const props = defineProps({
  step: { type: Number, default: 1 },
  hotel: { type: Object, default: null },
  checkIn: { type: [String, null], default: null },
  checkOut: { type: [String, null], default: null },
  nights: { type: Number, default: 0 },
  guestCount: { type: Number, default: 1 },
  rooms: { type: Object, default: () => ({}) },
  transfers: { type: Object, default: () => ({}) },
  roomPreviews: { type: Object, default: () => ({}) },
  taxPercentage: { type: [Number, String], default: 0 },
  servicePercentage: { type: [Number, String], default: 0 },
  ctaLabel: { type: String, default: "Continue" },
  ctaDisabled: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(["primary"]);

const showDaily = ref(false);

const fmtRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const fmtDate = (iso) => {
  if (!iso) return "-";
  const [y, m, d] = iso.split("-").map(Number);
  return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" }).format(
    new Date(y, m - 1, d)
  );
};

const roomLines = computed(() => {
  if (!props.hotel) return [];
  const result = [];
  for (const room of props.hotel.room_types ?? []) {
    const qty = Number(props.rooms[room.id]) || 0;
    if (qty <= 0) continue;
    const preview = props.roomPreviews[room.id];
    const subtotal =
      preview && Number(preview.subtotal) > 0
        ? Number(preview.subtotal) * qty
        : Number(room.base_rate) * props.nights * qty;
    result.push({
      id: room.id,
      name: room.name,
      qty,
      subtotal,
      dailyBreakdown: preview?.daily_breakdown ?? [],
    });
  }
  return result;
});

const transferLines = computed(() => {
  if (!props.hotel) return [];
  return (props.hotel.transfer_options ?? [])
    .filter((opt) => props.transfers[opt.id])
    .map((opt) => ({ id: opt.id, label: opt.label, price: opt.price }));
});

const subtotalRooms = computed(() => roomLines.value.reduce((s, l) => s + l.subtotal, 0));
const subtotalTransfer = computed(() => transferLines.value.reduce((s, l) => s + Number(l.price || 0), 0));
const subtotal = computed(() => subtotalRooms.value + subtotalTransfer.value);
const taxAmount = computed(
  () => Math.round(subtotal.value * (Number(props.taxPercentage) / 100) * 100) / 100
);
const serviceAmount = computed(
  () => Math.round(subtotal.value * (Number(props.servicePercentage) / 100) * 100) / 100
);
const total = computed(() => subtotal.value + taxAmount.value + serviceAmount.value);

// Effective values reflect applied promo (sourced from backend preview)
const displayTax = computed(() => {
  if (bookingStore.promoPreview) return Number(bookingStore.promoPreview.tax_amount ?? taxAmount.value);
  return taxAmount.value;
});
const displayService = computed(() => {
  if (bookingStore.promoPreview) return Number(bookingStore.promoPreview.service_charge_amount ?? serviceAmount.value);
  return serviceAmount.value;
});
const displayTotal = computed(() => {
  if (bookingStore.promoPreview) return Number(bookingStore.promoPreview.total_amount ?? total.value);
  return total.value;
});

const dailyAggregate = computed(() => {
  const map = {};
  for (const line of roomLines.value) {
    for (const day of line.dailyBreakdown) {
      const existing = map[day.date] ?? 0;
      map[day.date] = existing + Number(day.rate || 0) * line.qty;
    }
  }
  return Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([date, amount]) => ({ date, amount }));
});

const hasSelection = computed(() => props.checkIn && props.checkOut && roomLines.value.length > 0);
</script>

<template>
  <aside class="bg-background sticky top-20 rounded-2xl border p-4 lg:p-5">
    <div class="flex items-center justify-between">
      <h2 class="text-base font-medium tracking-tight">Booking Summary</h2>
      <span v-if="hasSelection" class="text-muted-foreground inline-flex items-center gap-1 text-xs">
        <Icon name="hugeicons:lock" class="size-3" />
        Saved
      </span>
    </div>

    <div class="mt-4 space-y-3">
      <div v-if="checkIn && checkOut" class="text-sm tracking-tight">
        <div class="text-muted-foreground text-xs sm:text-sm">Stay Dates</div>
        <div class="mt-0.5 font-medium">{{ fmtDate(checkIn) }} → {{ fmtDate(checkOut) }}</div>
        <div class="text-muted-foreground text-xs sm:text-sm">
          {{ nights }} night{{ nights > 1 ? "s" : "" }}, {{ guestCount }} guest{{ guestCount > 1 ? "s" : "" }}
        </div>
      </div>
      <div v-else class="text-muted-foreground rounded-lg border border-dashed p-3 text-sm tracking-tight">
        Select check-in and check-out dates to continue.
      </div>

      <div v-if="roomLines.length" class="space-y-1.5 border-t pt-3 text-sm tracking-tight">
        <div v-for="line in roomLines" :key="line.id" class="flex justify-between gap-3">
          <span class="text-foreground">{{ line.name }} × {{ line.qty }}</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(line.subtotal) }}</span>
        </div>
      </div>

      <div v-if="transferLines.length" class="space-y-1.5 border-t pt-3 text-sm tracking-tight">
        <div v-for="line in transferLines" :key="line.id" class="flex justify-between gap-3">
          <span>{{ line.label }}</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(line.price) }}</span>
        </div>
      </div>

      <Collapsible v-if="dailyAggregate.length > 1" v-model:open="showDaily">
        <CollapsibleTrigger
          class="text-muted-foreground hover:text-foreground inline-flex w-full items-center justify-between text-xs sm:text-sm tracking-tight"
        >
          <span>Daily breakdown</span>
          <Icon
            name="hugeicons:arrow-down-01"
            class="size-3.5 transition-transform"
            :class="{ 'rotate-180': showDaily }"
          />
        </CollapsibleTrigger>
        <CollapsibleContent class="mt-2 space-y-1 border-t pt-2">
          <div
            v-for="day in dailyAggregate"
            :key="day.date"
            class="text-muted-foreground flex justify-between text-xs tabular-nums"
          >
            <span>{{ fmtDate(day.date) }}</span>
            <span>Rp{{ fmtRupiah(day.amount) }}</span>
          </div>
        </CollapsibleContent>
      </Collapsible>

      <div v-if="subtotal > 0" class="space-y-1.5 border-t pt-3 text-sm tracking-tight">
        <div v-if="promoDiscount > 0" class="text-success-foreground flex justify-between">
          <span>Promo {{ promoCode }}</span>
          <span class="tabular-nums">-Rp{{ fmtRupiah(promoDiscount) }}</span>
        </div>
        <div class="text-muted-foreground flex justify-between">
          <span>Tax {{ taxPercentage }}%</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(displayTax) }}</span>
        </div>
        <div v-if="displayService > 0" class="text-muted-foreground flex justify-between">
          <span>Service {{ servicePercentage }}%</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(displayService) }}</span>
        </div>
        <div class="flex justify-between border-t pt-1.5 text-base font-semibold">
          <span>Total</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(displayTotal) }}</span>
        </div>
      </div>

      <Button
        class="w-full"
        :disabled="ctaDisabled || submitting"
        @click="emit('primary')"
      >
        <Icon v-if="submitting" name="svg-spinners:180-ring" class="size-4" />
        <span>{{ ctaLabel }}</span>
      </Button>
    </div>
  </aside>
</template>
