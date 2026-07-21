<template>
  <!-- Section (layout block, no input) -->
  <div
    v-if="normalized.type === 'section'"
    class="border-border"
    :class="isFirst ? '' : 'border-t pt-6'"
  >
    <h2 class="text-lg font-semibold tracking-tighter">{{ normalized.label }}</h2>
    <div
      v-if="normalized.settings?.description"
      class="format-html mt-1.5"
      v-html="normalized.settings.description"
    />
  </div>

  <div v-else class="space-y-2">
    <Label :for="fieldId" class="text-sm sm:text-base">
      {{ normalized.label }}
      <span v-if="isRequired" class="text-destructive">*</span>
    </Label>

    <!-- Control wrapper: one shared error indicator for every field type -->
    <div
      class="rounded-md"
      :class="{ 'ring-destructive/50 ring-offset-background ring-2 ring-offset-2': error }"
    >
      <!-- Text -->
      <Input
        v-if="normalized.type === 'text'"
        :id="fieldId"
        :model-value="modelValue"
        type="text"
        :disabled="disabled"
        :placeholder="normalized.placeholder"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Textarea -->
      <Textarea
        v-else-if="normalized.type === 'textarea'"
        :id="fieldId"
        :model-value="modelValue"
        rows="3"
        :disabled="disabled"
        :placeholder="normalized.placeholder"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Rich Text -->
      <TipTapEditor
        v-else-if="normalized.type === 'rich_text'"
        :model-value="modelValue || ''"
        :placeholder="normalized.placeholder || 'Write your answer'"
        :sticky="false"
        :allow-images="false"
        :editable="!disabled"
        min-height="140px"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Email -->
      <Input
        v-else-if="normalized.type === 'email'"
        :id="fieldId"
        :model-value="modelValue"
        type="email"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'email@example.com'"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Number -->
      <InputNumber
        v-else-if="normalized.type === 'number'"
        :id="fieldId"
        :model-value="modelValue"
        decimal
        :disabled="disabled"
        :placeholder="normalized.placeholder"
        :min="normalized.validation?.min"
        :max="normalized.validation?.max"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Phone -->
      <InputPhone
        v-else-if="normalized.type === 'phone'"
        :id="fieldId"
        :model-value="modelValue || ''"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- URL -->
      <InputLink
        v-else-if="normalized.type === 'url'"
        :model-value="modelValue || ''"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Date -->
      <DatePicker
        v-else-if="normalized.type === 'date'"
        :model-value="parseLocalDateString(modelValue)"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick a date'"
        @update:model-value="$emit('update:modelValue', $event ? toLocalDateString($event) : null)"
      />

      <!-- Time -->
      <TimePicker
        v-else-if="normalized.type === 'time'"
        :model-value="parseTimeString(modelValue)"
        clearable
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event ? formatTimeValue($event) : null)"
      />

      <!-- Date & Time -->
      <DatePicker
        v-else-if="normalized.type === 'datetime'"
        with-time
        :model-value="parseDateTimeString(modelValue)"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick date and time'"
        @update:model-value="$emit('update:modelValue', $event ? toDateTimeString($event) : null)"
      />

      <!-- Date Range -->
      <DatePicker
        v-else-if="normalized.type === 'date_range'"
        mode="range"
        :model-value="dateRangeValue"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick a date range'"
        @update:model-value="handleDateRange"
      />

      <!-- Month -->
      <MonthPicker
        v-else-if="normalized.type === 'month'"
        :model-value="parseMonthString(modelValue)"
        :disabled="disabled"
        :placeholder-text="normalized.placeholder || 'Pick a month'"
        @update:model-value="$emit('update:modelValue', $event ? formatMonthValue($event) : null)"
      />

      <!-- Month Range -->
      <MonthRangePicker
        v-else-if="normalized.type === 'month_range'"
        :model-value="monthRangeValue"
        :disabled="disabled"
        :placeholder-text="normalized.placeholder || 'Pick a month range'"
        @update:model-value="handleMonthRange"
      />

      <!-- Year -->
      <YearPicker
        v-else-if="normalized.type === 'year'"
        :model-value="yearToDateValue(modelValue)"
        :min-value="yearToDateValue(normalized.validation?.min)"
        :max-value="yearToDateValue(normalized.validation?.max)"
        :disabled="disabled"
        :placeholder-text="normalized.placeholder || 'Pick a year'"
        @update:model-value="$emit('update:modelValue', $event ? $event.year : null)"
      />

      <!-- Year Range -->
      <YearRangePicker
        v-else-if="normalized.type === 'year_range'"
        :model-value="yearRangeValue"
        :min-value="yearToDateValue(normalized.validation?.min)"
        :max-value="yearToDateValue(normalized.validation?.max)"
        :disabled="disabled"
        :placeholder-text="normalized.placeholder || 'Pick a year range'"
        @update:model-value="handleYearRange"
      />

      <!-- Time Range -->
      <TimeRangePicker
        v-else-if="normalized.type === 'time_range'"
        :model-value="timeRangeValue"
        clearable
        :disabled="disabled"
        @update:model-value="handleTimeRange"
      />

      <!-- Select -->
      <Select
        v-else-if="normalized.type === 'select'"
        :model-value="modelValue"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      >
        <SelectTrigger :id="fieldId" class="w-full">
          <SelectValue :placeholder="normalized.placeholder || 'Select an option'" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="opt in normalized.options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <!-- Multi Select -->
      <MultiSelect
        v-else-if="normalized.type === 'multi_select'"
        :model-value="multiSelectValue"
        :options="normalized.options"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Select options'"
        open-on-click
        @update:model-value="$emit('update:modelValue', $event.map((o) => o.value))"
      />

      <!-- Checkbox (single) -->
      <div v-else-if="normalized.type === 'checkbox'" class="flex items-center gap-x-2">
        <Checkbox
          :id="fieldId"
          :model-value="!!modelValue"
          :disabled="disabled"
          @update:model-value="$emit('update:modelValue', !!$event)"
        />
        <Label :for="fieldId" class="font-normal">
          {{ normalized.placeholder || normalized.label }}
        </Label>
      </div>

      <!-- Switch -->
      <div v-else-if="normalized.type === 'switch'" class="flex items-center gap-x-2">
        <Switch
          :id="fieldId"
          :model-value="!!modelValue"
          :disabled="disabled"
          @update:model-value="$emit('update:modelValue', !!$event)"
        />
        <Label :for="fieldId" class="font-normal">
          {{ normalized.placeholder || normalized.label }}
        </Label>
      </div>

      <!-- Checkbox Group -->
      <div v-else-if="normalized.type === 'checkbox_group'" class="space-y-2">
        <div v-for="opt in normalized.options" :key="opt.value" class="flex items-center gap-x-2">
          <Checkbox
            :id="`${fieldId}-${opt.value}`"
            :model-value="(modelValue || []).includes(opt.value)"
            :disabled="disabled"
            @update:model-value="handleMultiCheck($event, opt.value)"
          />
          <Label :for="`${fieldId}-${opt.value}`" class="font-normal">{{ opt.label }}</Label>
        </div>
      </div>

      <!-- Radio -->
      <RadioGroup
        v-else-if="normalized.type === 'radio'"
        :model-value="modelValue"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      >
        <div v-for="opt in normalized.options" :key="opt.value" class="flex items-center gap-x-2">
          <RadioGroupItem :value="opt.value" :id="`${fieldId}-${opt.value}`" />
          <Label :for="`${fieldId}-${opt.value}`" class="font-normal">{{ opt.label }}</Label>
        </div>
      </RadioGroup>

      <!-- Tags -->
      <TagsInput
        v-else-if="normalized.type === 'tags'"
        :model-value="modelValue || []"
        :max="normalized.validation?.max_selections"
        :disabled="disabled"
        class="text-sm"
        @update:model-value="$emit('update:modelValue', $event)"
      >
        <TagsInputItem v-for="tag in modelValue || []" :key="tag" :value="tag">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput :placeholder="normalized.placeholder || 'Type and press Enter'" />
      </TagsInput>

      <!-- Country -->
      <LocationCombobox
        v-else-if="normalized.type === 'country'"
        :model-value="modelValue"
        :options="countryOptions"
        :pinned="pinnedCountries"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Select country'"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Color -->
      <ColorPicker
        v-else-if="normalized.type === 'color'"
        :id="fieldId"
        alpha
        :model-value="modelValue"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick a color'"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- File -->
      <CustomFieldFileUpload
        v-else-if="normalized.type === 'file'"
        :field="normalized"
        :model-value="modelValue"
        :disabled="disabled || preview"
        :existing-files="existingFiles"
        :upload-handler="uploadHandler"
        :revert-handler="revertHandler"
        @update:model-value="$emit('update:modelValue', $event)"
        @uploading="$emit('uploading', $event)"
      />

      <!-- Rating -->
      <Rating
        v-else-if="normalized.type === 'rating'"
        :model-value="Number(modelValue) || 0"
        :max="ratingMax"
        :aria-label="normalized.label || 'Rating'"
        @update:model-value="!disabled && $emit('update:modelValue', $event)"
      />

      <!-- Slider -->
      <div v-else-if="normalized.type === 'slider'" class="space-y-2 pt-1">
        <Slider
          :model-value="[Number(modelValue ?? sliderMin)]"
          :min="sliderMin"
          :max="sliderMax"
          :step="sliderStep"
          :disabled="disabled"
          @update:model-value="$emit('update:modelValue', $event?.[0] ?? null)"
        />
        <div class="text-muted-foreground flex justify-between text-xs tracking-tight sm:text-sm">
          <span>{{ sliderMin }}</span>
          <span class="text-foreground font-medium">{{ modelValue ?? sliderMin }}</span>
          <span>{{ sliderMax }}</span>
        </div>
      </div>

      <!-- Slider Range -->
      <div v-else-if="normalized.type === 'slider_range'" class="space-y-2 pt-1">
        <Slider
          :model-value="sliderRangeValue"
          :min="sliderMin"
          :max="sliderMax"
          :step="sliderStep"
          :disabled="disabled"
          @update:model-value="handleSliderRange"
        />
        <div class="text-muted-foreground flex justify-between text-xs tracking-tight sm:text-sm">
          <span>{{ sliderMin }}</span>
          <span class="text-foreground font-medium">
            {{ sliderRangeValue[0] }} - {{ sliderRangeValue[1] }}
          </span>
          <span>{{ sliderMax }}</span>
        </div>
      </div>

      <!-- Slider Ruler -->
      <div
        v-else-if="normalized.type === 'slider_ruler'"
        class="pt-1"
        :class="disabled && 'pointer-events-none opacity-50'"
      >
        <SliderRuler
          :label="normalized.placeholder || ''"
          :model-value="Number(modelValue ?? sliderMin)"
          :min="sliderMin"
          :max="sliderMax"
          :step="sliderStep"
          :default-value="sliderMin"
          @update:model-value="$emit('update:modelValue', $event)"
        />
      </div>

      <!-- Linear Scale -->
      <div v-else-if="normalized.type === 'linear_scale'" class="space-y-2">
        <div
          role="radiogroup"
          class="grid grid-cols-[repeat(var(--scale-cols),minmax(0,1fr))] gap-1.5 sm:grid-cols-[repeat(var(--scale-cols-sm),minmax(0,1fr))] sm:gap-2"
          :style="scaleGridVars"
        >
          <button
            v-for="n in scaleRange"
            :key="n"
            type="button"
            role="radio"
            :aria-checked="Number(modelValue) === n"
            :aria-label="String(n)"
            :disabled="disabled"
            class="cn-input flex h-10 w-full min-w-0 cursor-pointer items-center justify-center px-0 text-sm font-medium tracking-tight transition-colors active:scale-95"
            :class="
              Number(modelValue) === n
                ? 'border-primary bg-primary text-primary-foreground'
                : 'hover:bg-muted'
            "
            @click="$emit('update:modelValue', n)"
          >
            {{ n }}
          </button>
        </div>
        <div
          v-if="normalized.settings?.min_label || normalized.settings?.max_label"
          class="text-muted-foreground flex justify-between gap-2 text-xs tracking-tight sm:text-sm"
        >
          <span class="text-left">{{ normalized.settings?.min_label }}</span>
          <span class="text-right">{{ normalized.settings?.max_label }}</span>
        </div>
      </div>
    </div>

    <!-- Help text -->
    <p v-if="normalized.help_text" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
      {{ normalized.help_text }}
    </p>

    <!-- Error -->
    <FieldError :errors="error ? [error] : []" />
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent } from "vue";
import { CalendarDate, Time } from "@internationalized/date";
import CustomFieldFileUpload from "./CustomFieldFileUpload.vue";
import { Checkbox } from "../checkbox";
import { ColorPicker } from "../color-picker";
import {
  DatePicker,
  MonthPicker,
  MonthRangePicker,
  YearPicker,
  YearRangePicker,
} from "../date-picker";
import { FieldError } from "../field";
import { Input } from "../input";
import { InputLink } from "../input-link";
import { InputNumber } from "../input-number";
import { InputPhone } from "../input-phone";
import { Label } from "../label";
import { LocationCombobox } from "../location-combobox";
import { MultiSelect } from "../multi-select";
import { RadioGroup, RadioGroupItem } from "../radio-group";
import { Rating } from "../rating";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../select";
import { Slider, SliderRuler } from "../slider";
import { Switch } from "../switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "../tags-input";
import { Textarea } from "../textarea";
import { TimePicker, TimeRangePicker } from "../date-picker";
import { countries as defaultCountries } from "./countries";
import { normalizeField, parseLocalDateString, toLocalDateString } from "./core";

// Lazily loaded so the rich-text editor (and its heavy TipTap graph) only
// enters the bundle when a rich_text field actually renders. This also keeps
// the module portable to component libraries whose tip-tap-editor pulls in
// app-level node views that may be absent (the editor simply never loads there).
const TipTapEditor = defineAsyncComponent(() => import("../tip-tap-editor").then((m) => m.TipTapEditor));

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  error: { type: String, default: null },
  isFirst: { type: Boolean, default: false },
  preview: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  locale: { type: String, default: "en" },
  countries: { type: Array, default: null },
  pinnedCountries: { type: Array, default: () => ["Indonesia"] },
  uploadHandler: { type: Function, default: null },
  revertHandler: { type: Function, default: null },
  // Already-submitted files for a file-type field: [{ id, name, url, size }].
  existingFiles: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const normalized = computed(() => normalizeField(props.field, props.locale));

const fieldId = computed(() => `field-${normalized.value.key}`);

const isRequired = computed(() => !!normalized.value.validation?.required);

const countryOptions = computed(() => props.countries ?? defaultCountries);

const multiSelectValue = computed(() =>
  (props.modelValue || []).map(
    (v) => normalized.value.options.find((o) => o.value === v) || { value: v, label: v }
  )
);

const handleMultiCheck = (checked, value) => {
  const current = props.modelValue || [];
  if (checked) {
    emit("update:modelValue", [...current, value]);
  } else {
    emit(
      "update:modelValue",
      current.filter((v) => v !== value)
    );
  }
};

/* ----- Date & time ----- */
const parseTimeString = (value) => {
  if (!value) return null;
  const match = /^(\d{1,2}):(\d{2})/.exec(value);
  if (!match) return null;
  return new Time(Number(match[1]), Number(match[2]));
};

const formatTimeValue = (time) =>
  `${String(time.hour).padStart(2, "0")}:${String(time.minute).padStart(2, "0")}`;

const parseDateTimeString = (value) => {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value);
  if (!match) return null;
  const [, y, m, d, h, min] = match;
  return new Date(Number(y), Number(m) - 1, Number(d), Number(h), Number(min));
};

const toDateTimeString = (date) => {
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${toLocalDateString(date)} ${hours}:${minutes}`;
};

const dateRangeValue = computed(() => {
  const value = props.modelValue;
  if (!value || typeof value !== "object") return null;
  return {
    start: parseLocalDateString(value.start),
    end: parseLocalDateString(value.end),
  };
});

const handleDateRange = (range) => {
  if (!range || (!range.start && !range.end)) {
    emit("update:modelValue", null);
    return;
  }
  if (range.start && range.end) {
    emit("update:modelValue", {
      start: toLocalDateString(range.start),
      end: toLocalDateString(range.end),
    });
  }
};

/* ----- Month & year (reka DateValue-based pickers) ----- */
const parseMonthString = (value) => {
  const match = /^(\d{4})-(\d{2})$/.exec(String(value ?? ""));
  return match ? new CalendarDate(Number(match[1]), Number(match[2]), 1) : null;
};

const formatMonthValue = (date) =>
  `${String(date.year).padStart(4, "0")}-${String(date.month).padStart(2, "0")}`;

const monthRangeValue = computed(() => {
  const value = props.modelValue;
  if (!value || typeof value !== "object") return null;
  return { start: parseMonthString(value.start), end: parseMonthString(value.end) };
});

const handleMonthRange = (range) => {
  if (!range || (!range.start && !range.end)) {
    emit("update:modelValue", null);
    return;
  }
  if (range.start && range.end) {
    emit("update:modelValue", {
      start: formatMonthValue(range.start),
      end: formatMonthValue(range.end),
    });
  }
};

const yearToDateValue = (value) => {
  const year = Number(value);
  return Number.isInteger(year) && year > 0 ? new CalendarDate(year, 1, 1) : undefined;
};

const yearRangeValue = computed(() => {
  const value = props.modelValue;
  if (!value || typeof value !== "object") return null;
  return { start: yearToDateValue(value.start) ?? null, end: yearToDateValue(value.end) ?? null };
});

const handleYearRange = (range) => {
  if (!range || (!range.start && !range.end)) {
    emit("update:modelValue", null);
    return;
  }
  if (range.start && range.end) {
    emit("update:modelValue", { start: range.start.year, end: range.end.year });
  }
};

/* ----- Time range ----- */
const timeRangeValue = computed(() => {
  const value = props.modelValue;
  if (!value || typeof value !== "object") return { start: undefined, end: undefined };
  return {
    start: parseTimeString(value.start) ?? undefined,
    end: parseTimeString(value.end) ?? undefined,
  };
});

const handleTimeRange = (range) => {
  if (!range || (!range.start && !range.end)) {
    emit("update:modelValue", null);
    return;
  }
  if (range.start && range.end) {
    emit("update:modelValue", {
      start: formatTimeValue(range.start),
      end: formatTimeValue(range.end),
    });
  }
};

/* ----- Scales ----- */
const ratingMax = computed(() => Number(normalized.value.settings?.max) || 5);

const scaleRange = computed(() => {
  const min = normalized.value.validation?.min ?? 1;
  const max = normalized.value.validation?.max ?? 5;
  const range = [];
  for (let i = min; i <= max; i++) {
    range.push(i);
  }
  return range;
});

const scaleGridVars = computed(() => {
  const count = scaleRange.value.length;
  const mobileCols = count <= 6 ? count : Math.ceil(count / 2);
  return {
    "--scale-cols": String(mobileCols),
    "--scale-cols-sm": String(count),
  };
});

const sliderMin = computed(() => Number(normalized.value.validation?.min ?? 0));
const sliderMax = computed(() => Number(normalized.value.validation?.max ?? 100));
const sliderStep = computed(() => Number(normalized.value.settings?.step) || 1);

const sliderRangeValue = computed(() => {
  const value = props.modelValue;
  if (value && typeof value === "object" && value.start != null && value.end != null) {
    return [Number(value.start), Number(value.end)];
  }
  return [sliderMin.value, sliderMax.value];
});

const handleSliderRange = (values) => {
  if (!Array.isArray(values) || values.length < 2) {
    emit("update:modelValue", null);
    return;
  }
  emit("update:modelValue", {
    start: Math.min(values[0], values[1]),
    end: Math.max(values[0], values[1]),
  });
};
</script>
