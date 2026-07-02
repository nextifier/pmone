<template>
  <!-- Section (layout block, no input) -->
  <div
    v-if="field.type === 'section'"
    class="border-border"
    :class="isFirst ? '' : 'border-t pt-6'"
  >
    <h2 class="text-lg font-semibold tracking-tighter">{{ field.label }}</h2>
    <div
      v-if="field.settings?.description"
      class="prose prose-sm text-muted-foreground mt-1.5 max-w-none text-sm tracking-tight"
      v-html="field.settings.description"
    />
  </div>

  <div v-else class="space-y-2">
    <Label :for="fieldId" class="text-sm sm:text-base">
      {{ field.label }}
      <span v-if="isRequired" class="text-destructive">*</span>
    </Label>

    <!-- Control wrapper: one shared error indicator for every field type -->
    <div
      class="rounded-md"
      :class="{ 'ring-destructive/50 ring-offset-background ring-2 ring-offset-2': error }"
    >
    <!-- Text -->
    <Input
      v-if="field.type === 'text'"
      :id="fieldId"
      :model-value="modelValue"
      type="text"
      :placeholder="field.placeholder"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Textarea -->
    <Textarea
      v-else-if="field.type === 'textarea'"
      :id="fieldId"
      :model-value="modelValue"
      rows="3"
      :placeholder="field.placeholder"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Rich Text -->
    <TipTapEditor
      v-else-if="field.type === 'rich_text'"
      :model-value="modelValue || ''"
      :placeholder="field.placeholder || 'Write your answer'"
      :sticky="false"
      :allow-images="false"
      min-height="140px"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Email -->
    <Input
      v-else-if="field.type === 'email'"
      :id="fieldId"
      :model-value="modelValue"
      type="email"
      :placeholder="field.placeholder || 'email@example.com'"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Number -->
    <InputNumber
      v-else-if="field.type === 'number'"
      :id="fieldId"
      :model-value="modelValue"
      decimal
      :placeholder="field.placeholder"
      :min="field.validation?.min"
      :max="field.validation?.max"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Phone -->
    <InputPhone
      v-else-if="field.type === 'phone'"
      :id="fieldId"
      :model-value="modelValue || ''"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- URL -->
    <InputLink
      v-else-if="field.type === 'url'"
      :model-value="modelValue || ''"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Date -->
    <DatePicker
      v-else-if="field.type === 'date'"
      :model-value="parseLocalDateString(modelValue)"
      :placeholder="field.placeholder || 'Pick a date'"
      @update:model-value="$emit('update:modelValue', $event ? toLocalDateString($event) : null)"
    />

    <!-- Time -->
    <TimePicker
      v-else-if="field.type === 'time'"
      :model-value="parseTimeString(modelValue)"
      clearable
      @update:model-value="$emit('update:modelValue', $event ? formatTimeValue($event) : null)"
    />

    <!-- Date & Time -->
    <DatePicker
      v-else-if="field.type === 'datetime'"
      with-time
      :model-value="parseDateTimeString(modelValue)"
      :placeholder="field.placeholder || 'Pick date and time'"
      @update:model-value="$emit('update:modelValue', $event ? toDateTimeString($event) : null)"
    />

    <!-- Date Range -->
    <RangeCalendarPicker
      v-else-if="field.type === 'date_range'"
      :model-value="dateRangeValue"
      size="default"
      :placeholder="field.placeholder || 'Pick a date range'"
      @update:model-value="handleDateRange"
    />

    <!-- Select -->
    <Select
      v-else-if="field.type === 'select'"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <SelectTrigger :id="fieldId" class="w-full">
        <SelectValue :placeholder="field.placeholder || 'Select an option'" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="opt in field.options"
          :key="opt.value || opt"
          :value="String(opt.value || opt)"
        >
          {{ opt.label || opt }}
        </SelectItem>
      </SelectContent>
    </Select>

    <!-- Multi Select -->
    <MultiSelect
      v-else-if="field.type === 'multi_select'"
      :model-value="multiSelectValue"
      :options="selectOptions"
      :placeholder="field.placeholder || 'Select options'"
      open-on-click
      @update:model-value="$emit('update:modelValue', $event.map((o) => o.value))"
    />

    <!-- Checkbox (single) -->
    <div v-else-if="field.type === 'checkbox'" class="flex items-center gap-x-2">
      <Checkbox
        :id="fieldId"
        :model-value="!!modelValue"
        @update:model-value="$emit('update:modelValue', !!$event)"
      />
      <Label :for="fieldId" class="font-normal">
        {{ field.placeholder || field.label }}
      </Label>
    </div>

    <!-- Switch -->
    <div v-else-if="field.type === 'switch'" class="flex items-center gap-x-2">
      <Switch
        :id="fieldId"
        :model-value="!!modelValue"
        @update:model-value="$emit('update:modelValue', !!$event)"
      />
      <Label :for="fieldId" class="font-normal">
        {{ field.placeholder || field.label }}
      </Label>
    </div>

    <!-- Checkbox Group -->
    <div v-else-if="field.type === 'checkbox_group'" class="space-y-2">
      <div
        v-for="opt in field.options"
        :key="opt.value || opt"
        class="flex items-center gap-x-2"
      >
        <Checkbox
          :id="`${fieldId}-${opt.value || opt}`"
          :model-value="(modelValue || []).includes(opt.value || opt)"
          @update:model-value="handleMultiCheck($event, opt.value || opt)"
        />
        <Label :for="`${fieldId}-${opt.value || opt}`" class="font-normal">
          {{ opt.label || opt }}
        </Label>
      </div>
    </div>

    <!-- Radio -->
    <RadioGroup
      v-else-if="field.type === 'radio'"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <div
        v-for="opt in field.options"
        :key="opt.value || opt"
        class="flex items-center gap-x-2"
      >
        <RadioGroupItem
          :value="String(opt.value || opt)"
          :id="`${fieldId}-${opt.value || opt}`"
        />
        <Label :for="`${fieldId}-${opt.value || opt}`" class="font-normal">
          {{ opt.label || opt }}
        </Label>
      </div>
    </RadioGroup>

    <!-- Tags -->
    <TagsInput
      v-else-if="field.type === 'tags'"
      :model-value="modelValue || []"
      :max="field.validation?.max_selections"
      class="text-sm"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <TagsInputItem v-for="tag in modelValue || []" :key="tag" :value="tag">
        <TagsInputItemText />
        <TagsInputItemDelete />
      </TagsInputItem>
      <TagsInputInput :placeholder="field.placeholder || 'Type and press Enter'" />
    </TagsInput>

    <!-- Country -->
    <LocationCombobox
      v-else-if="field.type === 'country'"
      :model-value="modelValue"
      :options="countries"
      :pinned="['Indonesia']"
      :placeholder="field.placeholder || 'Select country'"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Color -->
    <div v-else-if="field.type === 'color'" class="flex items-center gap-x-2">
      <label
        class="border-border relative size-9 shrink-0 cursor-pointer overflow-hidden rounded-md border shadow-xs"
        :style="{ backgroundColor: isValidHex(modelValue) ? modelValue : '#ffffff' }"
      >
        <input
          :id="fieldId"
          type="color"
          :value="isValidHex(modelValue) ? modelValue : '#000000'"
          class="absolute inset-0 size-full cursor-pointer opacity-0"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </label>
      <Input
        :model-value="modelValue"
        placeholder="#000000"
        maxlength="7"
        class="w-32 font-mono"
        @update:model-value="$emit('update:modelValue', $event)"
      />
    </div>

    <!-- File -->
    <PublicFileUpload
      v-else-if="field.type === 'file'"
      :field="field"
      :model-value="modelValue"
      :form-slug="formSlug"
      :preview="preview"
      @update:model-value="$emit('update:modelValue', $event)"
      @uploading="$emit('uploading', $event)"
    />

    <!-- Rating -->
    <Rating
      v-else-if="field.type === 'rating'"
      :model-value="Number(modelValue) || 0"
      :max="ratingMax"
      :aria-label="field.label || 'Rating'"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Slider -->
    <div v-else-if="field.type === 'slider'" class="space-y-2 pt-1">
      <Slider
        :model-value="[Number(modelValue ?? sliderMin)]"
        :min="sliderMin"
        :max="sliderMax"
        :step="sliderStep"
        @update:model-value="$emit('update:modelValue', $event?.[0] ?? null)"
      />
      <div class="text-muted-foreground flex justify-between text-xs tracking-tight sm:text-sm">
        <span>{{ sliderMin }}</span>
        <span class="text-foreground font-medium">{{ modelValue ?? sliderMin }}</span>
        <span>{{ sliderMax }}</span>
      </div>
    </div>

    <!-- Linear Scale -->
    <div v-else-if="field.type === 'linear_scale'" class="space-y-2">
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
        v-if="field.settings?.min_label || field.settings?.max_label"
        class="text-muted-foreground flex justify-between gap-2 text-xs tracking-tight sm:text-sm"
      >
        <span class="text-left">{{ field.settings?.min_label }}</span>
        <span class="text-right">{{ field.settings?.max_label }}</span>
      </div>
    </div>
    </div>

    <!-- Help text -->
    <p v-if="field.help_text" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
      {{ field.help_text }}
    </p>

    <!-- Error -->
    <p v-if="error" class="text-destructive text-sm tracking-tight">{{ error }}</p>
  </div>
</template>

<script setup>
import countries from "@/data/countries.json";
import PublicFileUpload from "@/components/form-builder/PublicFileUpload.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { InputLink } from "@/components/ui/input-link";
import { InputNumber } from "@/components/ui/input-number";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";
import { LocationCombobox } from "@/components/ui/location-combobox";
import { MultiSelect } from "@/components/ui/multi-select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Textarea } from "@/components/ui/textarea";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { parseLocalDateString, toLocalDateString } from "@/lib/utils";
import { Time } from "@internationalized/date";

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  error: { type: String, default: null },
  formSlug: { type: String, default: null },
  preview: { type: Boolean, default: false },
  isFirst: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const fieldId = computed(() => `field-${props.field.ulid}`);

const isRequired = computed(() => !!props.field.validation?.required);

/* ----- Options ----- */
const selectOptions = computed(() =>
  (props.field.options || []).map((opt) => ({
    value: String(opt.value ?? opt),
    label: String(opt.label ?? opt.value ?? opt),
  }))
);

const multiSelectValue = computed(() =>
  (props.modelValue || []).map(
    (v) => selectOptions.value.find((o) => o.value === v) || { value: v, label: v }
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

/* ----- Scales ----- */
const ratingMax = computed(() => Number(props.field.settings?.max) || 5);

const scaleRange = computed(() => {
  const min = props.field.validation?.min ?? 1;
  const max = props.field.validation?.max ?? 5;
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

const sliderMin = computed(() => Number(props.field.validation?.min ?? 0));
const sliderMax = computed(() => Number(props.field.validation?.max ?? 100));
const sliderStep = computed(() => Number(props.field.settings?.step) || 1);

/* ----- Color ----- */
const isValidHex = (value) => /^#[0-9a-fA-F]{6}$/.test(value || "");

</script>
