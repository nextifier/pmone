<template>
  <div class="frame sticky top-4">
    <div class="frame-header">
      <div class="frame-title">Booking</div>
    </div>
    <div class="frame-panel space-y-4">
      <div class="space-y-2">
        <Label>Stay Dates</Label>
        <Popover v-model:open="isOpen" :modal="false">
          <PopoverTrigger as-child>
            <Button
              variant="outline"
              type="button"
              class="w-full justify-start text-left font-normal tracking-tight"
            >
              <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
              <span class="truncate">{{ displayText }}</span>
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-auto rounded-xl p-0" align="start">
            <RangeCalendar
              :model-value="rangeValue"
              :min-value="today"
              :is-date-disabled="isDateDisabled"
              :number-of-months="numberOfMonths"
              @update:model-value="handleRangeChange"
            />
          </PopoverContent>
        </Popover>
        <p v-if="nights > 0" class="text-muted-foreground text-xs tracking-tight">
          {{ nights }} night{{ nights > 1 ? "s" : "" }}
        </p>
      </div>

      <div class="border-t pt-3 space-y-1.5 text-sm tracking-tight">
        <div class="flex justify-between">
          <span>Subtotal rooms</span>
          <span class="tabular-nums">Rp {{ formatRupiah(summary.rooms) }}</span>
        </div>
        <div class="flex justify-between">
          <span>Transfer</span>
          <span class="tabular-nums">Rp {{ formatRupiah(summary.transfer) }}</span>
        </div>
        <div class="text-muted-foreground flex justify-between">
          <span>Tax {{ taxPercentage }}%</span>
          <span class="tabular-nums">Rp {{ formatRupiah(summary.tax) }}</span>
        </div>
        <div v-if="summary.service > 0" class="text-muted-foreground flex justify-between">
          <span>Service {{ servicePercentage }}%</span>
          <span class="tabular-nums">Rp {{ formatRupiah(summary.service) }}</span>
        </div>
        <div class="flex justify-between border-t pt-1.5 font-semibold">
          <span>Total</span>
          <span class="tabular-nums">Rp {{ formatRupiah(summary.total) }}</span>
        </div>
      </div>

      <Button class="w-full" :disabled="!canProceed" @click="$emit('continue')">
        Continue to Booking
      </Button>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RangeCalendar } from "@/components/ui/range-calendar";
import {
  CalendarDate,
  DateFormatter,
  getLocalTimeZone,
  today as todayFn,
} from "@internationalized/date";
import { computed, ref } from "vue";

const props = defineProps({
  checkIn: { type: [Date, null], default: null },
  checkOut: { type: [Date, null], default: null },
  summary: { type: Object, required: true },
  taxPercentage: { type: [Number, String], default: 0 },
  servicePercentage: { type: [Number, String], default: 0 },
  canProceed: { type: Boolean, default: false },
  eventStart: { type: [String, null], default: null },
  eventEnd: { type: [String, null], default: null },
});

const emit = defineEmits(["update:checkIn", "update:checkOut", "continue"]);

const isOpen = ref(false);
const today = todayFn(getLocalTimeZone());
const df = new DateFormatter("en-GB", { day: "numeric", month: "short", year: "numeric" });
const numberOfMonths = ref(typeof window !== "undefined" && window.innerWidth >= 640 ? 2 : 1);

function toCalendarDate(date) {
  if (!date) {
    return undefined;
  }
  return new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

const rangeValue = computed(() => ({
  start: toCalendarDate(props.checkIn),
  end: toCalendarDate(props.checkOut),
}));

const nights = computed(() => {
  if (!props.checkIn || !props.checkOut) {
    return 0;
  }
  const ms = props.checkOut.getTime() - props.checkIn.getTime();
  return Math.max(0, Math.round(ms / 86400000));
});

const displayText = computed(() => {
  if (!props.checkIn || !props.checkOut) {
    return "Select check-in & check-out";
  }
  return `${df.format(props.checkIn)} – ${df.format(props.checkOut)}`;
});

const eventStart = computed(() => {
  if (!props.eventStart) return null;
  const d = new Date(props.eventStart);
  if (Number.isNaN(d.getTime())) return null;
  return new CalendarDate(d.getFullYear(), d.getMonth() + 1, d.getDate());
});

const eventEnd = computed(() => {
  if (!props.eventEnd) return null;
  const d = new Date(props.eventEnd);
  if (Number.isNaN(d.getTime())) return null;
  return new CalendarDate(d.getFullYear(), d.getMonth() + 1, d.getDate());
});

function isDateDisabled(date) {
  if (date.compare(today) < 0) return true;
  // Widen window by 3 days on each side of event to allow early check-in / late check-out.
  if (eventStart.value && date.compare(eventStart.value.subtract({ days: 3 })) < 0) return true;
  if (eventEnd.value && date.compare(eventEnd.value.add({ days: 3 })) > 0) return true;
  return false;
}

function handleRangeChange(value) {
  if (!value) {
    emit("update:checkIn", null);
    emit("update:checkOut", null);
    return;
  }
  const tz = getLocalTimeZone();
  emit("update:checkIn", value.start ? value.start.toDate(tz) : null);
  emit("update:checkOut", value.end ? value.end.toDate(tz) : null);
  if (value.start && value.end) {
    isOpen.value = false;
  }
}

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
