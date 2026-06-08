<script setup>
import TermsCheckbox from "./TermsCheckbox.vue";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { InputErrorMessage } from "../ui/input-error-message";
import { useBookingStore } from "../../stores/booking";
import { useTimeoutFn } from "@vueuse/core";
import { computed, ref, watch } from "vue";
import { toast } from "vue-sonner";

const bookingStore = useBookingStore();
const promoErrorMessages = usePromoCodeErrors();

const props = defineProps({
  hotel: { type: Object, default: null },
  checkIn: { type: [String, null], default: null },
  checkOut: { type: [String, null], default: null },
  nights: { type: Number, default: 0 },
  guestCount: { type: Number, default: 1 },
  rooms: { type: Object, default: () => ({}) },
  roomNotes: { type: Object, default: () => ({}) },
  transfers: { type: Object, default: () => ({}) },
  transferNotes: { type: Object, default: () => ({}) },
  guest: { type: Object, required: true },
  roomPreviews: { type: Object, default: () => ({}) },
  acceptTerms: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
});

const emit = defineEmits(["accept-terms", "edit", "submit"]);

const fmtRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
const fmtDate = (iso) => {
  if (!iso) return "-";
  const [y, m, d] = iso.split("-").map(Number);
  return new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "long", year: "numeric" }).format(
    new Date(y, m - 1, d)
  );
};

const roomLines = computed(() => {
  if (!props.hotel) return [];
  const lines = [];
  for (const room of props.hotel.room_types ?? []) {
    const qty = Number(props.rooms[room.id]) || 0;
    if (qty <= 0) continue;
    const preview = props.roomPreviews[room.id];
    const subtotal =
      preview && Number(preview.subtotal) > 0
        ? Number(preview.subtotal) * qty
        : Number(room.base_rate) * props.nights * qty;
    lines.push({
      id: room.id,
      name: room.name,
      qty,
      subtotal,
      notes: props.roomNotes[room.id] ?? "",
      dailyBreakdown: preview?.daily_breakdown ?? [],
    });
  }
  return lines;
});

const transferLines = computed(() => {
  if (!props.hotel) return [];
  return (props.hotel.transfer_options ?? [])
    .filter((opt) => props.transfers[opt.id])
    .map((opt) => ({
      id: opt.id,
      label: opt.label,
      price: opt.price,
      direction: opt.direction,
      notes: props.transferNotes[opt.id] ?? "",
    }));
});

const subtotalRooms = computed(() => roomLines.value.reduce((s, l) => s + l.subtotal, 0));
const subtotalTransfer = computed(() =>
  transferLines.value.reduce((s, l) => s + Number(l.price || 0), 0)
);
const subtotal = computed(() => subtotalRooms.value + subtotalTransfer.value);
const taxRate = computed(() => Number(props.hotel?.tax_percentage || 0));
const serviceRate = computed(() => Number(props.hotel?.service_charge_percentage || 0));
const taxAmount = computed(() => Math.round(subtotal.value * (taxRate.value / 100) * 100) / 100);
const serviceAmount = computed(
  () => Math.round(subtotal.value * (serviceRate.value / 100) * 100) / 100
);
const total = computed(() => subtotal.value + taxAmount.value + serviceAmount.value);

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

// --- Promo code state ---
const promoInput = ref(bookingStore.promoCode ?? "");
const promoApplying = ref(false);
const promoError = ref("");
const appliedPromo = computed(() => bookingStore.promoCode);
const promoDiscount = computed(() => Number(bookingStore.promoPreview?.discount_amount ?? 0));
// Bonus items (e.g. buy-x-get-y) - shown in cart as informational; allocated at
// final reservation create. previewDiscount stays 0 because bonus subtotal is
// not yet added to the cart.
const promoBonusItems = ref([]);
const totalBonusQty = computed(() =>
  promoBonusItems.value.reduce((sum, b) => sum + Number(b.bonus_qty || 0), 0),
);

// Effective totals account for applied promo discount
const effectiveTax = computed(() => {
  if (bookingStore.promoPreview) {
    return Number(bookingStore.promoPreview.tax_amount ?? taxAmount.value);
  }
  return taxAmount.value;
});
const effectiveService = computed(() => {
  if (bookingStore.promoPreview) {
    return Number(bookingStore.promoPreview.service_charge_amount ?? serviceAmount.value);
  }
  return serviceAmount.value;
});
const effectiveTotal = computed(() => {
  if (bookingStore.promoPreview) {
    return Number(bookingStore.promoPreview.total_amount ?? total.value);
  }
  return total.value;
});

async function applyPromo() {
  const code = promoInput.value?.trim();
  if (!code || !props.guest?.email) {
    promoError.value = "Please fill in your email first before applying a promo code.";
    return;
  }
  promoApplying.value = true;
  promoError.value = "";

  const items = [];
  for (const room of props.hotel?.room_types ?? []) {
    const qty = Number(props.rooms[room.id]) || 0;
    if (qty <= 0) continue;
    items.push({
      room_type_id: room.id,
      check_in_date: props.checkIn,
      check_out_date: props.checkOut,
      qty,
    });
  }

  const transfers = [];
  for (const opt of props.hotel?.transfer_options ?? []) {
    if (!props.transfers[opt.id]) continue;
    transfers.push({
      transfer_option_id: opt.id,
      direction: opt.direction,
      pax_count: 1,
    });
  }

  try {
    const eventId = props.hotel.event?.id ?? bookingStore.eventId ?? null;
    const validation = await $fetch("/api/hotels/promo/validate", {
      method: "POST",
      body: {
        code,
        email: props.guest.email,
        target_type: "Reservation",
        payload: {
          hotel_id: props.hotel.id,
          event_id: eventId,
          guest_email: props.guest.email,
          items,
          transfers,
        },
      },
    });

    if (validation?.data?.valid) {
      // Fetch preview with promo to get final pricing
      const preview = await $fetch("/api/hotels/preview-pricing", {
        method: "POST",
        body: {
          hotel_id: props.hotel.id,
          event_id: eventId,
          guest_email: props.guest.email,
          items,
          transfers,
          promo_code: code,
        },
      });

      bookingStore.setPromo(code, preview?.data?.pricing ?? null);
      promoError.value = "";
      promoBonusItems.value = validation?.data?.bonus_items ?? [];

      const discountAmount = Number(preview?.data?.pricing?.discount_amount ?? 0);
      const bonusQty = promoBonusItems.value.reduce((sum, b) => sum + Number(b.bonus_qty || 0), 0);

      let description;
      if (bonusQty > 0) {
        description = `${bonusQty} bonus ${bonusQty === 1 ? "item" : "items"} added`;
      } else if (discountAmount > 0) {
        description = `Discount: -Rp${fmtRupiah(discountAmount)}`;
      }

      toast.success(`Code "${code}" applied`, { description });
    } else {
      const errCode = validation?.data?.error_code || "INVALID_CODE";
      promoError.value = promoErrorMessages[errCode] ?? validation?.data?.message ?? "Invalid promo code.";
      bookingStore.clearPromo();
    }
  } catch (err) {
    const status = err?.response?.status ?? err?.statusCode;
    const errData = err?.data?.data ?? err?.data;
    const errCode = errData?.error_code;
    promoError.value = (errCode && promoErrorMessages[errCode]) || errData?.message || err?.statusMessage || "Failed to validate promo code.";
    bookingStore.clearPromo();
  } finally {
    promoApplying.value = false;
  }
}

function removePromo() {
  bookingStore.clearPromo();
  promoInput.value = "";
  promoError.value = "";
  promoBonusItems.value = [];
}

// Reset promo when items change (price changes invalidate preview)
watch(
  () => [props.rooms, props.transfers, props.checkIn, props.checkOut, props.guest.email],
  () => {
    if (bookingStore.promoCode) {
      bookingStore.clearPromo();
      promoBonusItems.value = [];
      promoError.value = "Booking changed - please re-apply promo code.";
    }
  },
  { deep: true },
);

// Auto-dismiss promo error after 6 seconds so it doesn't linger after the user has seen it.
const { start: startErrorDismiss, stop: stopErrorDismiss } = useTimeoutFn(
  () => {
    promoError.value = "";
  },
  6000,
  { immediate: false },
);
watch(promoError, (val) => {
  stopErrorDismiss();
  if (val) startErrorDismiss();
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-base font-medium tracking-tight">Review Your Booking</h2>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Double-check the details before continuing to payment.
      </p>
    </div>

    <section class="bg-background space-y-3 rounded-2xl border p-4 sm:p-5">
      <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-medium tracking-tight">Stay Dates</h3>
        <button
          type="button"
          class="text-primary text-sm font-medium tracking-tight hover:underline"
          @click="emit('edit', 1)"
        >
          Edit
        </button>
      </div>
      <div class="text-sm tracking-tight">
        <div>
          <span class="text-muted-foreground">Check-in:</span> {{ fmtDate(checkIn) }}
        </div>
        <div>
          <span class="text-muted-foreground">Check-out:</span> {{ fmtDate(checkOut) }}
        </div>
        <div class="text-muted-foreground">
          {{ nights }} night{{ nights > 1 ? "s" : "" }} · {{ guestCount }} guest{{ guestCount > 1 ? "s" : "" }}
        </div>
      </div>
    </section>

    <section v-if="roomLines.length" class="bg-background space-y-3 rounded-2xl border p-4 sm:p-5">
      <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-medium tracking-tight">Rooms</h3>
        <button
          type="button"
          class="text-primary text-sm font-medium tracking-tight hover:underline"
          @click="emit('edit', 1)"
        >
          Edit
        </button>
      </div>
      <div class="space-y-3 text-sm tracking-tight">
        <div v-for="line in roomLines" :key="line.id" class="space-y-1">
          <div class="flex justify-between gap-3">
            <span>{{ line.name }} × {{ line.qty }}</span>
            <span class="tabular-nums">Rp{{ fmtRupiah(line.subtotal) }}</span>
          </div>
          <p
            v-if="line.notes"
            class="text-muted-foreground text-xs sm:text-sm italic tracking-tight"
          >
            <span class="font-medium not-italic">Notes:</span> {{ line.notes }}
          </p>
        </div>
      </div>
    </section>

    <section
      v-if="transferLines.length"
      class="bg-background space-y-3 rounded-2xl border p-4 sm:p-5"
    >
      <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-medium tracking-tight">Add-ons</h3>
        <button
          type="button"
          class="text-primary text-sm font-medium tracking-tight hover:underline"
          @click="emit('edit', 2)"
        >
          Edit
        </button>
      </div>
      <div class="space-y-3 text-sm tracking-tight">
        <div v-for="line in transferLines" :key="line.id" class="space-y-1">
          <div class="flex justify-between gap-3">
            <span>{{ line.label }}</span>
            <span class="tabular-nums">Rp{{ fmtRupiah(line.price) }}</span>
          </div>
          <p
            v-if="line.notes"
            class="text-muted-foreground text-xs sm:text-sm italic tracking-tight"
          >
            <span class="font-medium not-italic">Notes:</span> {{ line.notes }}
          </p>
        </div>
      </div>
    </section>

    <section class="bg-background space-y-3 rounded-2xl border p-4 sm:p-5">
      <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-medium tracking-tight">Guest Info</h3>
        <button
          type="button"
          class="text-primary text-sm font-medium tracking-tight hover:underline"
          @click="emit('edit', 3)"
        >
          Edit
        </button>
      </div>
      <dl class="grid grid-cols-1 gap-2 text-sm tracking-tight sm:grid-cols-2">
        <div>
          <dt class="text-muted-foreground">Name</dt>
          <dd>{{ guest.name }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Email</dt>
          <dd>{{ guest.email }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Phone</dt>
          <dd>{{ guest.phone }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">ID</dt>
          <dd>
            {{ guest.identity_type === "nik" ? "NIK" : "Passport" }} · {{ guest.identity_number }}
          </dd>
        </div>
        <div v-if="guest.company">
          <dt class="text-muted-foreground">Company</dt>
          <dd>{{ guest.company }}</dd>
        </div>
        <div v-if="guest.special_request">
          <dt class="text-muted-foreground">Special Request</dt>
          <dd>{{ guest.special_request }}</dd>
        </div>
      </dl>
    </section>

    <section class="bg-background space-y-3 rounded-2xl border p-4 sm:p-5">
      <h3 class="text-sm font-medium tracking-tight">Promo Code</h3>
      <div class="flex gap-2">
        <Input
          v-model="promoInput"
          placeholder="ENTER CODE"
          class="flex-1 uppercase"
          maxlength="60"
          :disabled="promoApplying || !!appliedPromo"
          @keydown.enter.prevent="!appliedPromo && promoInput?.trim() && !promoApplying && applyPromo()"
        />
        <Button
          v-if="!appliedPromo"
          type="button"
          variant="outline"
          :disabled="!promoInput?.trim() || promoApplying"
          @click="applyPromo"
        >
          <Icon v-if="promoApplying" name="svg-spinners:180-ring" class="size-4" />
          Apply
        </Button>
        <Button
          v-else
          type="button"
          variant="outline"
          @click="removePromo"
        >
          Remove
        </Button>
      </div>
      <div
        v-if="appliedPromo"
        class="bg-success/10 text-success-foreground space-y-1.5 rounded-md px-3 py-2 text-xs sm:text-sm tracking-tight"
      >
        <div>
          <Icon name="lucide:check-circle-2" class="size-4 inline mr-1" />
          <template v-if="totalBonusQty > 0">
            Promo "{{ appliedPromo }}" applied:
            {{ totalBonusQty }} bonus {{ totalBonusQty === 1 ? "item" : "items" }} added free
          </template>
          <template v-else>
            Promo "{{ appliedPromo }}" applied: -Rp{{ fmtRupiah(promoDiscount) }}
          </template>
        </div>
        <ul v-if="totalBonusQty > 0" class="text-success-foreground/90 ml-5 list-disc text-xs tracking-tight">
          <li v-for="(b, idx) in promoBonusItems" :key="idx">
            +{{ b.bonus_qty }} × {{ b.label }} (worth Rp{{ fmtRupiah(b.bonus_qty * b.unit_price) }})
          </li>
        </ul>
      </div>
      <div
        v-if="promoError"
        class="bg-destructive/10 text-destructive rounded-md px-3 py-2 text-xs sm:text-sm tracking-tight"
      >
        {{ promoError }}
      </div>
    </section>

    <section class="bg-background space-y-3 rounded-2xl border p-4 sm:p-5">
      <h3 class="text-sm font-medium tracking-tight">Price Breakdown</h3>
      <div v-if="dailyAggregate.length" class="space-y-1 text-sm tracking-tight">
        <div
          v-for="day in dailyAggregate"
          :key="day.date"
          class="text-muted-foreground flex justify-between text-xs sm:text-sm tabular-nums"
        >
          <span>{{ fmtDate(day.date) }}</span>
          <span>Rp{{ fmtRupiah(day.amount) }}</span>
        </div>
      </div>
      <div class="space-y-1.5 border-t pt-3 text-sm tracking-tight">
        <div class="flex justify-between">
          <span>Subtotal rooms</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(subtotalRooms) }}</span>
        </div>
        <div v-if="subtotalTransfer > 0" class="flex justify-between">
          <span>Add-ons</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(subtotalTransfer) }}</span>
        </div>
        <div v-if="promoDiscount > 0" class="text-success-foreground flex justify-between">
          <span>Discount</span>
          <span class="tabular-nums">-Rp{{ fmtRupiah(promoDiscount) }}</span>
        </div>
        <div class="text-muted-foreground flex justify-between">
          <span>Tax {{ taxRate }}%</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(effectiveTax) }}</span>
        </div>
        <div v-if="effectiveService > 0" class="text-muted-foreground flex justify-between">
          <span>Service {{ serviceRate }}%</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(effectiveService) }}</span>
        </div>
        <div class="flex justify-between border-t pt-1.5 text-base font-semibold">
          <span>Total</span>
          <span class="tabular-nums">Rp{{ fmtRupiah(effectiveTotal) }}</span>
        </div>
      </div>
    </section>

    <section class="space-y-2">
      <TermsCheckbox
        :model-value="acceptTerms"
        @update:model-value="(v) => emit('accept-terms', v)"
      />
      <InputErrorMessage :errors="errors.accept_terms" />
    </section>

    <Button
      type="button"
      class="w-full"
      size="lg"
      :disabled="!acceptTerms || saving"
      @click="emit('submit')"
    >
      <Icon v-if="saving" name="svg-spinners:180-ring" class="size-4" />
      <span v-if="effectiveTotal <= 0">Confirm Free Booking</span>
      <span v-else>Confirm &amp; Pay Rp{{ fmtRupiah(effectiveTotal) }}</span>
    </Button>
  </div>
</template>
