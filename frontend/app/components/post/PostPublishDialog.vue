<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="400px">
    <div class="p-4 md:p-6">
      <div class="space-y-1">
        <h3 class="text-primary text-lg font-semibold">Publish Post</h3>
        <p class="text-muted-foreground text-sm">Choose when to publish your post.</p>
      </div>

      <div class="mt-6 space-y-3">
        <!-- Publish Now Option -->
        <button
          type="button"
          @click="handlePublishNow"
          class="border-border hover:border-primary hover:bg-primary/5 group flex w-full items-center gap-3 rounded-lg border p-4 text-left transition"
        >
          <div
            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full"
          >
            <Icon name="hugeicons:sent" class="size-5" />
          </div>
          <div class="flex-1">
            <div class="font-medium">Publish Now</div>
            <p class="text-muted-foreground text-xs">Your post will be published immediately</p>
          </div>
          <Icon
            name="hugeicons:arrow-right-01"
            class="text-muted-foreground group-hover:text-primary size-5 transition"
          />
        </button>

        <!-- Schedule Option -->
        <button
          type="button"
          @click="showSchedulePicker = !showSchedulePicker"
          class="border-border hover:border-primary hover:bg-primary/5 group flex w-full items-center gap-3 rounded-lg border p-4 text-left transition"
          :class="{ 'border-primary bg-primary/5': showSchedulePicker }"
        >
          <div
            class="bg-blue-100 flex size-10 shrink-0 items-center justify-center rounded-full text-blue-600 dark:bg-blue-900 dark:text-blue-400"
          >
            <Icon name="hugeicons:calendar-03" class="size-5" />
          </div>
          <div class="flex-1">
            <div class="font-medium">Schedule for Later</div>
            <p class="text-muted-foreground text-xs">Set a specific date and time to publish</p>
          </div>
          <Icon
            :name="showSchedulePicker ? 'hugeicons:arrow-down-01' : 'hugeicons:arrow-right-01'"
            class="text-muted-foreground group-hover:text-primary size-5 transition"
          />
        </button>

        <!-- Schedule Picker -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div
            v-if="showSchedulePicker"
            class="bg-muted/50 space-y-4 rounded-lg border p-4"
          >
            <!-- Date Picker -->
            <div class="space-y-2">
              <Label class="text-xs font-medium">Date</Label>
              <Popover v-model:open="datePickerOpen">
                <PopoverTrigger as-child>
                  <Button
                    variant="outline"
                    :class="
                      cn(
                        'w-full justify-start text-left font-normal',
                        !scheduledDate && 'text-muted-foreground'
                      )
                    "
                  >
                    <Icon name="hugeicons:calendar-04" class="mr-2 size-4" />
                    {{ scheduledDate ? formatDate(scheduledDate) : "Pick a date" }}
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-auto p-0" align="start">
                  <Calendar
                    v-model="scheduledDate"
                    :min-value="todayDate"
                    initial-focus
                    @update:model-value="datePickerOpen = false"
                  />
                </PopoverContent>
              </Popover>
            </div>

            <!-- Time Picker -->
            <div class="space-y-2">
              <Label class="text-xs font-medium">Time</Label>
              <div class="flex items-center gap-2">
                <Select v-model="scheduledHour">
                  <SelectTrigger class="w-20">
                    <SelectValue placeholder="Hour" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="h in hours" :key="h" :value="h">
                      {{ h.toString().padStart(2, "0") }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <span class="text-muted-foreground">:</span>
                <Select v-model="scheduledMinute">
                  <SelectTrigger class="w-20">
                    <SelectValue placeholder="Min" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="m in minutes" :key="m" :value="m">
                      {{ m.toString().padStart(2, "0") }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- Schedule Button -->
            <Button
              type="button"
              @click="handleSchedule"
              :disabled="!scheduledDate"
              class="w-full"
            >
              <Icon name="hugeicons:calendar-check-out-02" class="mr-2 size-4" />
              Schedule for {{ scheduledDateTimeFormatted }}
            </Button>
          </div>
        </Transition>
      </div>

      <!-- Cancel Button -->
      <div class="mt-6 flex justify-end">
        <Button type="button" variant="ghost" @click="isOpen = false">Cancel</Button>
      </div>
    </div>
  </DialogResponsive>
</template>

<script setup lang="ts">
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { CalendarDate, getLocalTimeZone, today, type DateValue } from "@internationalized/date";

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  "update:open": [value: boolean];
  publish: [scheduledAt?: Date];
}>();

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit("update:open", value),
});

const showSchedulePicker = ref(false);
const datePickerOpen = ref(false);

const todayDate = today(getLocalTimeZone());
const now = new Date();

const scheduledDate = ref<DateValue | undefined>();
const scheduledHour = ref(now.getHours());
const scheduledMinute = ref(0);

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = Array.from({ length: 60 }, (_, i) => i);

function formatDate(date: DateValue): string {
  return date.toDate(getLocalTimeZone()).toLocaleDateString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

const scheduledDateTimeFormatted = computed(() => {
  if (!scheduledDate.value) return "";
  const date = scheduledDate.value.toDate(getLocalTimeZone());
  date.setHours(scheduledHour.value, scheduledMinute.value, 0, 0);
  return date.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
});

function handlePublishNow() {
  emit("publish");
  isOpen.value = false;
}

function handleSchedule() {
  if (!scheduledDate.value) return;
  const date = scheduledDate.value.toDate(getLocalTimeZone());
  date.setHours(scheduledHour.value, scheduledMinute.value, 0, 0);
  emit("publish", date);
  isOpen.value = false;
}

// Reset state when dialog closes
watch(isOpen, (value) => {
  if (!value) {
    showSchedulePicker.value = false;
    scheduledDate.value = undefined;
    scheduledHour.value = new Date().getHours();
    scheduledMinute.value = 0;
  }
});
</script>
