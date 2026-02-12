<template>
  <div class="space-y-2">
    <Popover v-model:open="isOpen" :modal="false">
      <PopoverTrigger as-child>
        <Button
          variant="outline"
          type="button"
          :disabled="disabled"
          :class="
            cn(
              'w-full justify-start text-left text-sm font-normal',
              !modelValue && 'text-muted-foreground'
            )
          "
        >
          <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
          <span class="truncate">
            {{ modelValue ? formatDateTime(modelValue) : placeholder }}
          </span>
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-auto p-0" align="start">
        <div class="p-3">
          <!-- Calendar -->
          <Calendar v-model="selectedDate" initial-focus />

          <!-- Time Picker -->
          <div class="border-border mt-3 flex items-center gap-2 border-t pt-3">
            <Icon name="hugeicons:clock-04" class="text-muted-foreground size-4" />
            <Select v-model="selectedHour">
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
            <Select v-model="selectedMinute">
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

          <!-- Actions -->
          <div class="mt-3 flex items-center justify-between gap-2">
            <Button
              v-if="modelValue"
              type="button"
              variant="ghost"
              size="sm"
              @click="clearDateTime"
              class="text-xs"
            >
              Clear
            </Button>
            <div class="ml-auto flex gap-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="isOpen = false"
                class="text-xs"
              >
                Cancel
              </Button>
              <Button type="button" size="sm" @click="applyDateTime" class="text-xs">
                Apply
              </Button>
            </div>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  </div>
</template>

<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { CalendarDate, getLocalTimeZone, type DateValue } from "@internationalized/date";

const props = withDefaults(
  defineProps<{
    modelValue?: Date | null;
    disabled?: boolean;
    placeholder?: string;
    defaultHour?: number;
    defaultMinute?: number;
  }>(),
  {
    modelValue: null,
    disabled: false,
    placeholder: "Select date and time",
    defaultHour: 9,
    defaultMinute: 0,
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: Date | null];
}>();

const isOpen = ref(false);

// Internal state for calendar and time
const selectedDate = ref<DateValue | undefined>();
const selectedHour = ref(props.defaultHour);
const selectedMinute = ref(props.defaultMinute);

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = Array.from({ length: 60 }, (_, i) => i);

// Initialize from modelValue
watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      const date = new Date(value);
      selectedDate.value = new CalendarDate(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate()
      );
      selectedHour.value = date.getHours();
      selectedMinute.value = date.getMinutes();
    } else {
      selectedDate.value = undefined;
      selectedHour.value = props.defaultHour;
      selectedMinute.value = props.defaultMinute;
    }
  },
  { immediate: true }
);

function formatDateTime(date: Date): string {
  return date.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function applyDateTime() {
  if (!selectedDate.value) {
    emit("update:modelValue", null);
    isOpen.value = false;
    return;
  }

  const date = selectedDate.value.toDate(getLocalTimeZone());
  date.setHours(selectedHour.value, selectedMinute.value, 0, 0);
  emit("update:modelValue", date);
  isOpen.value = false;
}

function clearDateTime() {
  selectedDate.value = undefined;
  selectedHour.value = props.defaultHour;
  selectedMinute.value = props.defaultMinute;
  emit("update:modelValue", null);
  isOpen.value = false;
}
</script>
