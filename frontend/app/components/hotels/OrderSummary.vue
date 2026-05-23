<!--
  Compact order summary used in the dedicated /hotels/checkout/[token] page.
  Designed to coexist with the Components SDK panel: shows just enough context
  (hotel, stay window, rooms, price breakdown) without competing with the
  payment surface for attention. Deliberately omits guest info, special
  requests, transfer detail, cancellation policy — those live on the full
  /hotels/reservation/[token] view.
-->
<template>
  <div class="space-y-4 text-sm tracking-tight">
    <!-- Hotel identity -->
    <div class="space-y-0.5">
      <p class="text-foreground font-medium">{{ reservation.hotel?.name }}</p>
      <p
        v-if="reservation.hotel?.city"
        class="text-muted-foreground text-xs tracking-tight sm:text-sm"
      >
        {{ reservation.hotel.city }}
      </p>
    </div>

    <!-- Stay window -->
    <div class="bg-muted/40 grid grid-cols-2 gap-3 rounded-lg p-3">
      <div>
        <p class="text-muted-foreground text-xs tracking-tight">Check-in</p>
        <p class="font-medium">{{ formatShortDate(firstCheckIn) }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs tracking-tight">Check-out</p>
        <p class="font-medium">{{ formatShortDate(lastCheckOut) }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs tracking-tight">Nights</p>
        <p class="font-medium tabular-nums">{{ totalNights }}</p>
      </div>
      <div>
        <p class="text-muted-foreground text-xs tracking-tight">Rooms</p>
        <p class="font-medium tabular-nums">{{ totalRooms }}</p>
      </div>
    </div>

    <!-- Line items (compact) -->
    <ul class="space-y-2">
      <li
        v-for="(item, idx) in reservation.items"
        :key="`room-${idx}`"
        class="flex items-baseline justify-between gap-3"
      >
        <span class="text-muted-foreground">
          {{ item.room_type_name }}
          <span v-if="Number(item.qty) > 1" class="tabular-nums"> × {{ item.qty }}</span>
        </span>
        <span class="tabular-nums">Rp{{ formatRupiah(item.subtotal) }}</span>
      </li>
      <li
        v-for="(t, idx) in reservation.transfers || []"
        :key="`transfer-${idx}`"
        class="flex items-baseline justify-between gap-3"
      >
        <span class="text-muted-foreground">
          {{ t.direction_label || t.direction }}
        </span>
        <span class="tabular-nums">Rp{{ formatRupiah(t.price) }}</span>
      </li>
    </ul>

    <!-- Price breakdown -->
    <div class="space-y-1.5 border-t pt-3">
      <div class="text-muted-foreground flex items-baseline justify-between">
        <span>Subtotal</span>
        <span class="tabular-nums">
          Rp{{
            formatRupiah(
              (reservation.amounts.subtotal_rooms || 0) +
                (reservation.amounts.subtotal_transfer || 0)
            )
          }}
        </span>
      </div>
      <div class="text-muted-foreground flex items-baseline justify-between">
        <span>Tax</span>
        <span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.tax) }}</span>
      </div>
      <div
        v-if="reservation.amounts.service > 0"
        class="text-muted-foreground flex items-baseline justify-between"
      >
        <span>Service</span>
        <span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.service) }}</span>
      </div>
      <div
        class="text-foreground flex items-baseline justify-between border-t pt-2 text-base font-semibold tracking-tighter"
      >
        <span>Total</span>
        <span class="tabular-nums">Rp{{ formatRupiah(reservation.amounts.total) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  reservation: { type: Object, required: true },
});

const firstCheckIn = computed(() => {
  const items = props.reservation?.items ?? [];
  if (!items.length) return null;
  return items
    .map((i) => i.check_in_date)
    .sort()
    .at(0);
});

const lastCheckOut = computed(() => {
  const items = props.reservation?.items ?? [];
  if (!items.length) return null;
  return items
    .map((i) => i.check_out_date)
    .sort()
    .at(-1);
});

const totalNights = computed(() => {
  const items = props.reservation?.items ?? [];
  return items.reduce((sum, i) => sum + Number(i.nights || 0), 0);
});

const totalRooms = computed(() => {
  const items = props.reservation?.items ?? [];
  return items.reduce((sum, i) => sum + Number(i.qty || 0), 0);
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const formatShortDate = (iso) => {
  if (!iso) return "-";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};
</script>
