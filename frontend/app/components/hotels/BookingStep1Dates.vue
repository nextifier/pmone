<script setup>
import { Button } from "@/components/ui/button";
import { ButtonGroup } from "@/components/ui/button-group";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PricingCalendar } from "@/components/ui/pricing-calendar";
import BookingStep1RoomCard from "./BookingStep1RoomCard.vue";
import {
  CalendarDate,
  DateFormatter,
  getLocalTimeZone,
  today as todayFn,
} from "@internationalized/date";
import { computed, ref } from "vue";

const props = defineProps({
  hotel: { type: Object, default: null },
  pricingData: { type: Object, default: () => ({}) },
  pricingLoading: { type: Boolean, default: false },
  availability: { type: Object, default: () => ({}) },
  roomPreviews: { type: Object, default: () => ({}) },
  checkingAvailability: { type: Boolean, default: false },
  checkIn: { type: [String, null], default: null },
  checkOut: { type: [String, null], default: null },
  guestCount: { type: Number, default: 1 },
  rooms: { type: Object, default: () => ({}) },
  roomNotes: { type: Object, default: () => ({}) },
  nights: { type: Number, default: 0 },
});

const emit = defineEmits([
  "update:checkIn",
  "update:checkOut",
  "update:guestCount",
  "update:roomQty",
  "update:roomNotes",
  "monthChange",
]);

const today = todayFn(getLocalTimeZone());
const calendarOpen = ref(false);

const df = new DateFormatter("en-US", {
  day: "numeric",
  month: "short",
  year: "numeric",
});

function parseISO(iso) {
  if (!iso) return undefined;
  const [y, m, d] = iso.split("-").map(Number);
  if (!y || !m || !d) return undefined;
  return new CalendarDate(y, m, d);
}

function toISO(dateValue) {
  if (!dateValue) return null;
  const pad = (n) => String(n).padStart(2, "0");
  return `${dateValue.year}-${pad(dateValue.month)}-${pad(dateValue.day)}`;
}

const rangeValue = computed(() => ({
  start: parseISO(props.checkIn),
  end: parseISO(props.checkOut),
}));

function parseEventDate(value) {
  if (!value) return undefined;
  // Backend can return either `YYYY-MM-DD` or full ISO (e.g. `2026-07-22T00:00:00Z`);
  // slice to the date portion so the parsed components are always numeric.
  const [y, m, d] = String(value).slice(0, 10).split("-").map(Number);
  if (!y || !m || !d) return undefined;
  return new CalendarDate(y, m, d);
}

const placeholder = computed(() => {
  const eventDate = parseEventDate(props.hotel?.event?.start_date);
  if (eventDate && eventDate.compare(today) >= 0) {
    return eventDate;
  }
  return parseISO(props.checkIn) ?? today;
});

// Only block dates in the past. Event dates are not the booking boundary —
// guests can check in earlier or stay longer than the event window as long
// as the hotel has an active allotment for those dates (the calendar's
// per-day pricing/availability data already disables cells outside the
// allotment range).
const minValue = computed(() => today);

const maxValue = computed(() => undefined);

function handleRangeChange(value) {
  if (!value) {
    emit("update:checkIn", null);
    emit("update:checkOut", null);
    return;
  }
  emit("update:checkIn", value.start ? toISO(value.start) : null);
  emit("update:checkOut", value.end ? toISO(value.end) : null);
  if (value.start && value.end) {
    calendarOpen.value = false;
  }
}

const triggerText = computed(() => {
  const start = parseISO(props.checkIn);
  const end = parseISO(props.checkOut);
  if (start && end) {
    return `${df.format(start.toDate(getLocalTimeZone()))} → ${df.format(end.toDate(getLocalTimeZone()))}`;
  }
  if (start) {
    return `${df.format(start.toDate(getLocalTimeZone()))} → Check-out date`;
  }
  return "Select check-in & check-out dates";
});

const roomTypes = computed(() => props.hotel?.room_types ?? []);

const MIN_GUESTS = 1;
const MAX_GUESTS = 10;

function handleGuestAdjustment(delta) {
  const next = Math.max(MIN_GUESTS, Math.min(MAX_GUESTS, Number(props.guestCount) + delta));
  emit("update:guestCount", next);
}

function handleGuestInputChange(value) {
  const parsed = Number.parseInt(String(value), 10);
  if (!Number.isNaN(parsed)) {
    const clamped = Math.max(MIN_GUESTS, Math.min(MAX_GUESTS, parsed));
    emit("update:guestCount", clamped);
  } else {
    emit("update:guestCount", props.guestCount);
  }
}
</script>

<template>
  <div class="space-y-6">
    <section class="space-y-3">
      <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end sm:gap-4">
        <div class="min-w-0">
          <Label class="text-xs tracking-tight sm:text-sm">Check-in / Check-out</Label>
          <Popover v-model:open="calendarOpen" :modal="false">
            <PopoverTrigger as-child>
              <Button
                variant="outline"
                type="button"
                class="mt-1.5 w-full justify-start gap-x-2 text-left text-sm font-normal tracking-tight"
                :class="!checkIn || !checkOut ? 'text-muted-foreground' : ''"
              >
                <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
                <span class="truncate">{{ triggerText }}</span>
                <span
                  v-if="nights > 0"
                  class="bg-muted text-foreground ml-auto shrink-0 rounded-full px-2 py-0.5 text-xs tracking-tight"
                >
                  {{ nights }} night{{ nights > 1 ? "s" : "" }}
                </span>
              </Button>
            </PopoverTrigger>
            <PopoverContent
              class="z-50 max-h-[85vh] w-auto max-w-[95vw] overflow-auto border-0 bg-transparent p-0 shadow-none"
              align="start"
              :side-offset="6"
            >
              <PricingCalendar
                :model-value="rangeValue"
                :placeholder="placeholder"
                :min-value="minValue"
                :max-value="maxValue"
                :pricing-data="pricingData"
                :is-loading="pricingLoading"
                class="bg-popover text-popover-foreground shadow-md"
                @update:model-value="handleRangeChange"
                @month-change="(p) => emit('monthChange', p)"
              />
            </PopoverContent>
          </Popover>
        </div>
        <div class="min-w-0">
          <Label class="text-xs tracking-tight sm:text-sm">Guests</Label>
          <ButtonGroup class="mt-1.5">
            <Input
              :model-value="guestCount"
              :max-length="2"
              class="h-9 !w-14 text-center font-mono"
              aria-label="Number of guests"
              @update:model-value="handleGuestInputChange"
            />
            <Button
              variant="outline"
              size="icon"
              type="button"
              aria-label="Decrement guests"
              :disabled="guestCount <= MIN_GUESTS"
              @click="handleGuestAdjustment(-1)"
            >
              <Icon name="hugeicons:minus-sign" class="size-4" />
            </Button>
            <Button
              variant="outline"
              size="icon"
              type="button"
              aria-label="Increment guests"
              :disabled="guestCount >= MAX_GUESTS"
              @click="handleGuestAdjustment(1)"
            >
              <Icon name="hugeicons:plus-sign" class="size-4" />
            </Button>
          </ButtonGroup>
        </div>
      </div>
    </section>

    <section v-if="roomTypes.length" class="space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-medium tracking-tight">Select Rooms</h2>
        <span v-if="checkingAvailability" class="text-muted-foreground text-xs tracking-tight">
          Checking availability…
        </span>
      </div>

      <BookingStep1RoomCard
        v-for="room in roomTypes"
        :key="room.id"
        :room="room"
        :qty="rooms[room.id] ?? 0"
        :notes="roomNotes[room.id] ?? ''"
        :available="availability[room.id] ?? null"
        :preview="roomPreviews[room.id] ?? null"
        :nights="nights"
        @update:qty="(qty) => emit('update:roomQty', { roomId: room.id, qty })"
        @update:notes="(notes) => emit('update:roomNotes', { roomId: room.id, notes })"
      />
    </section>
  </div>
</template>
