<script lang="ts" setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import type { DateValue } from "@internationalized/date";
import { CalendarDate, getLocalTimeZone, today } from "@internationalized/date";
import { reactiveOmit } from "@vueuse/core";
import type { CalendarRootEmits, CalendarRootProps } from "reka-ui";
import { CalendarRoot, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";
import {
  CalendarCell,
  CalendarCellTrigger,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarHeader,
  CalendarNextButton,
  CalendarPrevButton,
} from ".";

interface Props extends CalendarRootProps {
  class?: HTMLAttributes["class"];
  minYear?: number;
  maxYear?: number;
}

const props = withDefaults(defineProps<Props>(), {
  minYear: () => today(getLocalTimeZone()).year - 100,
  maxYear: () => today(getLocalTimeZone()).year + 10,
});
const emits = defineEmits<CalendarRootEmits>();

const delegatedProps = reactiveOmit(props, "class", "minYear", "maxYear");
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const todayDate = today(getLocalTimeZone());

// Resolve initial date from modelValue or placeholder
function getInitialDate(): DateValue {
  const mv = props.modelValue;
  if (mv) {
    if (Array.isArray(mv)) return mv[0] ?? todayDate;
    return mv as DateValue;
  }
  if (props.placeholder) return props.placeholder as DateValue;
  return todayDate;
}

const initialDate = getInitialDate();
const displayMonth = ref(initialDate.month);
const displayYear = ref(initialDate.year);

// Placeholder computed from select values
const selectPlaceholder = computed(
  () => new CalendarDate(displayYear.value, displayMonth.value, 1)
);

// Sync selects when calendar navigates via prev/next
function onPlaceholderChange(date: DateValue) {
  displayMonth.value = date.month;
  displayYear.value = date.year;
}

// Sync selects when modelValue changes externally
watch(
  () => props.modelValue,
  (v) => {
    if (!v) return;
    const date = Array.isArray(v) ? v[0] : (v as DateValue);
    if (date) {
      displayMonth.value = date.month;
      displayYear.value = date.year;
    }
  }
);

// Month names (short format)
const monthNames = Array.from({ length: 12 }, (_, i) =>
  new CalendarDate(todayDate.year, i + 1, 1)
    .toDate(getLocalTimeZone())
    .toLocaleString("en-US", { month: "short" })
);

// Years list (descending)
const years = computed(() => {
  const count = props.maxYear - props.minYear + 1;
  return Array.from({ length: count }, (_, i) => props.maxYear - i);
});
</script>

<template>
  <CalendarRoot
    v-slot="{ grid, weekDays }"
    data-slot="calendar"
    :class="cn('p-2.5', props.class)"
    v-bind="forwarded"
    :placeholder="selectPlaceholder"
    weekday-format="short"
    @update:placeholder="onPlaceholderChange"
  >
    <CalendarHeader class="gap-1">
      <CalendarPrevButton class="static left-auto size-8 border-0 shadow-none" />
      <Select v-model="displayMonth">
        <SelectTrigger size="sm" class="bg-card tracking-tight">
          <SelectValue />
        </SelectTrigger>
        <SelectContent class="tracking-tight">
          <SelectItem v-for="(month, i) in monthNames" :key="i" :value="i + 1">
            {{ month }}
          </SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="displayYear">
        <SelectTrigger size="sm" class="bg-card tracking-tight">
          <SelectValue />
        </SelectTrigger>
        <SelectContent class="tracking-tight">
          <SelectItem v-for="year in years" :key="year" :value="year">
            {{ year }}
          </SelectItem>
        </SelectContent>
      </Select>
      <CalendarNextButton class="static right-auto size-8 border-0 shadow-none" />
    </CalendarHeader>

    <div class="mt-4 flex flex-col gap-y-4 sm:flex-row sm:gap-x-4 sm:gap-y-0">
      <CalendarGrid v-for="month in grid" :key="month.value.toString()">
        <CalendarGridHead>
          <CalendarGridRow>
            <CalendarHeadCell v-for="day in weekDays" :key="day">
              {{ day.slice(0, 2) }}
            </CalendarHeadCell>
          </CalendarGridRow>
        </CalendarGridHead>
        <CalendarGridBody>
          <CalendarGridRow
            v-for="(weekDates, index) in month.rows"
            :key="`weekDate-${index}`"
            class="mt-2 w-full"
          >
            <CalendarCell v-for="weekDate in weekDates" :key="weekDate.toString()" :date="weekDate">
              <CalendarCellTrigger :day="weekDate" :month="month.value" />
            </CalendarCell>
          </CalendarGridRow>
        </CalendarGridBody>
      </CalendarGrid>
    </div>
  </CalendarRoot>
</template>
