<script lang="ts" setup>
import {
  CalendarCell,
  CalendarCellTrigger,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarHeader,
  CalendarHeading,
  CalendarNextButton,
  CalendarPrevButton,
} from "@/components/ui/calendar";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { CalendarDateTime, getLocalTimeZone, today } from "@internationalized/date";
import { formatDate } from "@vueuse/core";
import { CalendarRoot } from "reka-ui";

const props = defineProps<{
  modelValue?: Date | null;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: Date | null];
}>();

const todayDate = today(getLocalTimeZone());

// Track if user has selected a date (to avoid emitting on initial mount when null)
const hasUserInteracted = ref(false);

// Internal calendar state
const internalValue = ref(
  new CalendarDateTime(todayDate.year, todayDate.month, todayDate.day, 12, 0, 0)
);

// Selected hour and minute for dropdowns
const selectedHour = ref(12);
const selectedMinute = ref(0);

// Generate hours (0-23) and minutes (0-59)
const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = Array.from({ length: 60 }, (_, i) => i);

// Initialize from modelValue
watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      hasUserInteracted.value = true;
      const date = new Date(value);
      internalValue.value = new CalendarDateTime(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate(),
        date.getHours(),
        date.getMinutes()
      );
      selectedHour.value = date.getHours();
      selectedMinute.value = date.getMinutes();
    }
  },
  { immediate: true }
);

// Update internalValue when hour/minute changes
watch([selectedHour, selectedMinute], ([hour, minute]) => {
  hasUserInteracted.value = true;
  internalValue.value = new CalendarDateTime(
    internalValue.value.year,
    internalValue.value.month,
    internalValue.value.day,
    hour,
    minute
  );
});

// Emit changes to parent only after user interaction
watch(
  internalValue,
  (value) => {
    if (hasUserInteracted.value) {
      const date = value.toDate(getLocalTimeZone());
      emit("update:modelValue", date);
    }
  },
  { deep: true }
);

// Mark as interacted when calendar date changes
const handleCalendarChange = () => {
  hasUserInteracted.value = true;
};
</script>

<template>
  <div>
    <div class="rounded-md border p-3" :class="{ 'pointer-events-none opacity-50': disabled }">
      <div class="flex gap-4 max-sm:flex-col">
        <CalendarRoot v-model="internalValue" v-slot="{ grid, weekDays }" data-slot="calendar" @update:model-value="handleCalendarChange">
          <CalendarHeader>
            <CalendarHeading />
            <div class="flex items-center gap-1">
              <CalendarPrevButton class="absolute left-1" />
              <CalendarNextButton class="absolute right-1" />
            </div>
          </CalendarHeader>
          <div class="mt-4 flex flex-col gap-y-4 sm:flex-row sm:gap-x-4 sm:gap-y-0">
            <CalendarGrid v-for="month in grid" :key="month.value.toString()">
              <CalendarGridHead>
                <CalendarGridRow>
                  <CalendarHeadCell v-for="day in weekDays" :key="day">
                    {{ day }}
                  </CalendarHeadCell>
                </CalendarGridRow>
              </CalendarGridHead>
              <CalendarGridBody>
                <CalendarGridRow
                  v-for="(weekDates, index) in month.rows"
                  :key="`weekDate-${index}`"
                  class="mt-2 w-full"
                >
                  <CalendarCell
                    v-for="weekDate in weekDates"
                    :key="weekDate.toString()"
                    :date="weekDate"
                  >
                    <CalendarCellTrigger :day="weekDate" :month="month.value" />
                  </CalendarCell>
                </CalendarGridRow>
              </CalendarGridBody>
            </CalendarGrid>
          </div>
        </CalendarRoot>

        <!-- Time Picker Section -->
        <div class="flex flex-col justify-center gap-3 max-sm:border-t max-sm:pt-3 sm:border-s sm:ps-4">
          <p class="text-sm font-medium">
            {{ formatDate(internalValue.toDate(getLocalTimeZone()), "dddd, D") }}
          </p>

          <div class="flex items-center gap-2">
            <Icon name="hugeicons:clock-04" class="text-muted-foreground size-4" />
            <Select v-model="selectedHour" :disabled="disabled">
              <SelectTrigger class="w-16 text-xs">
                <SelectValue placeholder="HH" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="h in hours" :key="h" :value="h">
                  {{ h.toString().padStart(2, "0") }}
                </SelectItem>
              </SelectContent>
            </Select>
            <span class="text-muted-foreground">:</span>
            <Select v-model="selectedMinute" :disabled="disabled">
              <SelectTrigger class="w-16 text-xs">
                <SelectValue placeholder="MM" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="m in minutes" :key="m" :value="m">
                  {{ m.toString().padStart(2, "0") }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <p class="text-muted-foreground text-xs">
            {{ selectedHour.toString().padStart(2, "0") }}:{{ selectedMinute.toString().padStart(2, "0") }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
