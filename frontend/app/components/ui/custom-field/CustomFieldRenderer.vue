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
      class="typeset typeset-cms max-w-2xl mt-1.5"
      v-html="normalized.settings.description"
    />
  </div>

  <!-- No gap class: `.cn-field` already supplies `gap-2` (8px) in every style
       pack, so the old `isLargeLabel ? 'gap-2.5' : 'gap-2'` was half a restated
       default and half a 10px exception that made public forms sit looser than
       every other surface. -->
  <Field v-else-if="!isDependentHidden" :data-invalid="!!error">
    <FieldLabel
      :id="isOptionGroup ? labelId : undefined"
      :for="isOptionGroup ? undefined : fieldId"
      :required="isRequired"
      :class="labelClass"
    >
      {{ normalized.label }}
    </FieldLabel>

    <!-- Control wrapper: one shared error indicator for every field type -->
    <div
      class="rounded-md"
      :class="{ 'ring-destructive-foreground/50 ring-offset-background ring-2 ring-offset-2': error }"
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
        :id="fieldId"
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

      <!-- Price -->
      <InputGroup v-else-if="normalized.type === 'price'">
        <InputNumber
          :id="fieldId"
          :model-value="modelValue"
          :min="priceMin"
          :max="normalized.validation?.max"
          :disabled="disabled"
          :placeholder="normalized.placeholder || 'Ex: 50,000,000'"
          data-slot="input-group-control"
          class="cn-input-group-input flex-1"
          @update:model-value="$emit('update:modelValue', $event)"
        />
        <InputGroupAddon>
          <InputGroupText>{{ currencySymbol }}</InputGroupText>
        </InputGroupAddon>
      </InputGroup>

      <!-- Price Range -->
      <PriceRange
        v-else-if="normalized.type === 'price_range'"
        :id="fieldId"
        :model-value="modelValue"
        :min="priceMin"
        :max="priceMax"
        :step="priceStep"
        :currency="currencySymbol"
        :show-slider="showPriceSlider"
        :disabled="disabled"
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
        :id="fieldId"
        v-else-if="normalized.type === 'url'"
        :model-value="modelValue || ''"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Date -->
      <DatePicker
        :id="fieldId"
        v-else-if="normalized.type === 'date'"
        :model-value="parseLocalDateString(modelValue)"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick a date'"
        @update:model-value="$emit('update:modelValue', $event ? toLocalDateString($event) : null)"
      />

      <!-- Time -->
      <TimePicker
        :id="fieldId"
        v-else-if="normalized.type === 'time'"
        :model-value="parseTimeString(modelValue)"
        clearable
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event ? formatTimeValue($event) : null)"
      />

      <!-- Date & Time -->
      <DatePicker
        :id="fieldId"
        v-else-if="normalized.type === 'datetime'"
        with-time
        :model-value="parseDateTimeString(modelValue)"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick date and time'"
        @update:model-value="$emit('update:modelValue', $event ? toDateTimeString($event) : null)"
      />

      <!-- Date Range -->
      <DatePicker
        :id="fieldId"
        v-else-if="normalized.type === 'date_range'"
        mode="range"
        :model-value="dateRangeValue"
        :disabled="disabled"
        :placeholder="normalized.placeholder || 'Pick a date range'"
        @update:model-value="handleDateRange"
      />

      <!-- Month -->
      <MonthPicker
        :id="fieldId"
        v-else-if="normalized.type === 'month'"
        :model-value="parseMonthString(modelValue)"
        :disabled="disabled"
        :placeholder-text="normalized.placeholder || 'Pick a month'"
        @update:model-value="$emit('update:modelValue', $event ? formatMonthValue($event) : null)"
      />

      <!-- Month Range -->
      <MonthRangePicker
        :id="fieldId"
        v-else-if="normalized.type === 'month_range'"
        :model-value="monthRangeValue"
        :disabled="disabled"
        :placeholder-text="normalized.placeholder || 'Pick a month range'"
        @update:model-value="handleMonthRange"
      />

      <!-- Year -->
      <YearPicker
        :id="fieldId"
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
        :id="fieldId"
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
        :id="fieldId"
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

      <!-- Multi Select: a combobox in `multiple` mode. Selected options stay in the list
           with a check so it never reflows under the cursor; only the query filters. -->
      <Combobox
        v-else-if="normalized.type === 'multi_select'"
        :model-value="multiSelectValue"
        multiple
        ignore-filter
        open-on-click
        open-on-focus
        :disabled="disabled"
        @update:model-value="emitMultiSelect"
      >
        <ComboboxAnchor class="w-full">
          <ComboboxChips
            :model-value="multiSelectValue"
            :disabled="disabled"
            :display-value="(option) => option.label"
            class="w-full"
            @update:model-value="emitMultiSelect"
          >
            <ComboboxChip v-for="item in multiSelectValue" :key="item.value" :value="item" />
            <ComboboxChipsInput
              :id="fieldId"
              v-model="multiSelectQuery"
              :placeholder="normalized.placeholder || 'Select options'"
            />
            <button
              v-if="multiSelectValue.length"
              type="button"
              class="text-muted-foreground/80 hover:text-foreground focus-visible:ring-ring/50 ml-auto flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none focus-visible:ring-2"
              aria-label="Clear all"
              @click="$emit('update:modelValue', [])"
            >
              <X class="size-3.5" aria-hidden="true" />
            </button>
          </ComboboxChips>
        </ComboboxAnchor>

        <ComboboxList class="w-(--reka-combobox-trigger-width)">
          <!-- The viewport carries the scroll; without it a long list is clipped. -->
          <ComboboxViewport>
            <ComboboxEmpty>No results found.</ComboboxEmpty>

            <ComboboxGroup v-if="multiSelectOptions.length">
              <!-- The predicate is bound here rather than spread onto the option
                   objects: `multiSelectValue` resolves selections out of the same
                   `normalized.options` array, so the items must stay
                   reference-identical for reka to match them. -->
              <ComboboxItem
                v-for="opt in multiSelectOptions"
                :key="opt.value"
                :value="opt"
                :disabled="disabled || isOptionBlocked(opt.value)"
              >
                {{ opt.label }}

                <ComboboxItemIndicator>
                  <Check class="ml-auto size-4" />
                </ComboboxItemIndicator>
              </ComboboxItem>
            </ComboboxGroup>
          </ComboboxViewport>
        </ComboboxList>
      </Combobox>

      <!-- Every control row below is `<Field orientation="horizontal">`, which
           is `flex-row items-center` + `gap-2` - exactly the hand-rolled div
           these branches used to carry, except it is the component the library
           documents for a control-beside-its-caption row. -->

      <!-- Checkbox (single) -->
      <Field v-else-if="normalized.type === 'checkbox'" orientation="horizontal">
        <Checkbox
          :id="fieldId"
          :model-value="!!modelValue"
          :disabled="disabled"
          @update:model-value="$emit('update:modelValue', !!$event)"
        />
        <FieldLabel :for="fieldId" :class="['font-normal', labelClass]">
          {{ normalized.placeholder || normalized.label }}
        </FieldLabel>
      </Field>

      <!-- Switch -->
      <Field v-else-if="normalized.type === 'switch'" orientation="horizontal">
        <Switch
          :id="fieldId"
          :model-value="!!modelValue"
          :disabled="disabled"
          @update:model-value="$emit('update:modelValue', !!$event)"
        />
        <FieldLabel :for="fieldId" :class="['font-normal', labelClass]">
          {{ normalized.placeholder || normalized.label }}
        </FieldLabel>
      </Field>

      <!-- Checkbox Group -->
      <div
        v-else-if="normalized.type === 'checkbox_group'"
        role="group"
        :aria-labelledby="labelId"
        class="space-y-2"
      >
        <Field
          v-for="opt in normalized.options"
          :key="opt.value"
          orientation="horizontal"
        >
          <Checkbox
            :id="`${fieldId}-${opt.value}`"
            :model-value="(modelValue || []).includes(opt.value)"
            :disabled="disabled || isOptionBlocked(opt.value)"
            @update:model-value="handleMultiCheck($event, opt.value)"
          />
          <!-- The label dims with the box: a full-contrast label next to a
               greyed checkbox reads as a rendering glitch, not as a rule. -->
          <FieldLabel
            :for="`${fieldId}-${opt.value}`"
            :class="[
              'font-normal',
              labelClass,
              isOptionBlocked(opt.value) && 'text-muted-foreground',
            ]"
          >
            {{ opt.label }}
          </FieldLabel>
        </Field>
      </div>

      <!-- Two options sit inline - a yes/no or male/female pair does not deserve
           two rows on the narrowest screen. Three or more fall back to
           `cn-radio-group`'s own grid, one per row, because `flex-wrap` packs by
           content width: four options of uneven length wrap into a ragged 2x2
           whose second column never lines up. Stacked also matches the
           `checkbox_group` right beside it. The inline class wins over the
           pack's `grid` because style packs are imported into `@layer base`
           while utilities sit above them. -->
      <RadioGroup
        v-else-if="normalized.type === 'radio'"
        :class="isInlineOptions ? 'flex flex-wrap gap-x-6 gap-y-2' : undefined"
        :aria-labelledby="labelId"
        :model-value="modelValue"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      >
        <!-- `w-auto` only when inline: `Field`'s base is `w-full`, which is
             right for a stacked group and would put each of the two inline
             options back on its own row. -->
        <Field
          v-for="opt in normalized.options"
          :key="opt.value"
          orientation="horizontal"
          :class="isInlineOptions ? 'w-auto' : undefined"
        >
          <RadioGroupItem :value="opt.value" :id="`${fieldId}-${opt.value}`" />
          <FieldLabel :for="`${fieldId}-${opt.value}`" :class="['font-normal', labelClass]">
            {{ opt.label }}
          </FieldLabel>
        </Field>
      </RadioGroup>

      <!-- Tags -->
      <TagsInput
        :id="fieldId"
        v-else-if="normalized.type === 'tags'"
        :model-value="modelValue || []"
        :max="normalized.validation?.max_selections"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      >
        <TagsInputItem v-for="tag in modelValue || []" :key="tag" :value="tag">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput :placeholder="normalized.placeholder || 'Type and press Enter'" />
      </TagsInput>

      <!-- Province / City: narrowed by the parent named in settings.depends_on.
           Only reached when the country is Indonesia - otherwise the field is
           withdrawn above. City is present but disabled until a province is
           picked, so the buyer can see what is coming. -->
      <LocationCombobox
        :id="fieldId"
        v-else-if="isLocationDependent"
        :model-value="modelValue"
        :options="locationOptions"
        :pinned="normalized.type === 'province' ? ['DKI Jakarta'] : []"
        :disabled="disabled || (normalized.type === 'city' && !parentValue)"
        :placeholder="
          normalized.placeholder ||
          (normalized.type === 'province' ? 'Select province' : 'Select city')
        "
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <!-- Country -->
      <LocationCombobox
        :id="fieldId"
        v-else-if="normalized.type === 'country'"
        :model-value="modelValue"
        :options="countryOptions"
        :pinned="pinnedCountries"
        :disabled="disabled"
        show-flag
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
        :id="fieldId"
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
        :id="fieldId"
        v-else-if="normalized.type === 'rating'"
        :model-value="Number(modelValue) || 0"
        :max="ratingMax"
        :aria-label="normalized.label || 'Rating'"
        @update:model-value="!disabled && $emit('update:modelValue', $event)"
      />

      <!-- Slider -->
      <div v-else-if="normalized.type === 'slider'" class="space-y-2 pt-1">
        <Slider
          :id="fieldId"
          :model-value="[Number(modelValue ?? sliderMin)]"
          :min="sliderMin"
          :max="sliderMax"
          :step="sliderStep"
          :disabled="disabled"
          @update:model-value="$emit('update:modelValue', $event?.[0] ?? null)"
        />
        <div class="text-muted-foreground flex justify-between text-sm tracking-tight">
          <span>{{ sliderMin }}</span>
          <span class="text-foreground font-medium">{{ modelValue ?? sliderMin }}</span>
          <span>{{ sliderMax }}</span>
        </div>
      </div>

      <!-- Slider Range -->
      <div v-else-if="normalized.type === 'slider_range'" class="space-y-2 pt-1">
        <Slider
          :id="fieldId"
          :model-value="sliderRangeValue"
          :min="sliderMin"
          :max="sliderMax"
          :step="sliderStep"
          :disabled="disabled"
          @update:model-value="handleSliderRange"
        />
        <div class="text-muted-foreground flex justify-between text-sm tracking-tight">
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
          :id="fieldId"
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
          :aria-labelledby="labelId"
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
            class="cn-input flex h-11 w-full min-w-0 cursor-pointer items-center justify-center px-0 font-medium tracking-tight transition-colors active:scale-95 sm:h-10"
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
          class="text-muted-foreground flex justify-between gap-2 text-sm tracking-tight"
        >
          <span class="text-left">{{ normalized.settings?.min_label }}</span>
          <span class="text-right">{{ normalized.settings?.max_label }}</span>
        </div>
      </div>
    </div>

    <!-- Help text + selection counter, sharing one row so a capped field does
         not grow a third line of small print. -->
    <div
      v-if="normalized.help_text || selectionCounter"
      class="flex items-start justify-between gap-x-2"
    >
      <!-- `text-sm` flat. The old `text-xs sm:text-sm` branch was still 12px on
           a phone, which STYLE_GUIDE names as explicitly not a remedy. -->
      <p
        v-if="normalized.help_text"
        class="text-muted-foreground text-sm tracking-tight"
      >
        {{ normalized.help_text }}
      </p>
      <span
        v-if="selectionCounter"
        class="text-muted-foreground ml-auto shrink-0 text-sm tracking-tight tabular-nums"
      >
        {{ selectionCounter }}
      </span>
    </div>

    <!-- Error -->
    <FieldError :errors="error ? [error] : []" />
  </Field>
</template>

<script setup>
import { computed, defineAsyncComponent, ref, shallowRef, watch } from "vue";
import { CalendarDate, Time } from "@internationalized/date";
import { Check, X } from "@lucide/vue";
import { useFilter } from "reka-ui";
import CustomFieldFileUpload from "./CustomFieldFileUpload.vue";
import { Checkbox } from "../checkbox";
import { ColorPicker } from "../color-picker";
import {
  Combobox,
  ComboboxAnchor,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "../combobox";
import {
  DatePicker,
  MonthPicker,
  MonthRangePicker,
  YearPicker,
  YearRangePicker,
} from "../date-picker";
import { FieldError } from "../field";
import { Input } from "../input";
import { InputGroup, InputGroupAddon, InputGroupText } from "../input-group";
import { InputLink } from "../input-link";
import { InputNumber } from "../input-number";
import { InputPhone } from "../input-phone";
import { Label } from "../label";
import { LocationCombobox } from "../location-combobox";
import { PriceRange } from "../price-range";
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
  // "lg" on standalone public surfaces (the /f/{slug} form), where a field label
  // is a question the visitor reads rather than a dashboard control caption.
  labelSize: { type: String, default: "default" },
  countries: { type: Array, default: null },
  // Sibling answers, keyed the same way CustomFieldGroup keys its model. A
  // dependent field (province, city) needs the value of the field named in its
  // `settings.depends_on`, which this component would otherwise never see: it
  // renders one field and is handed only that field's value.
  contextValues: { type: Object, default: () => ({}) },
  pinnedCountries: { type: Array, default: () => ["Indonesia"] },
  uploadHandler: { type: Function, default: null },
  revertHandler: { type: Function, default: null },
  // Already-submitted files for a file-type field: [{ id, name, url, size }].
  existingFiles: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const normalized = computed(() => normalizeField(props.field, props.locale));

// --- Dependent location selects (province, city) -------------------------------
// The datasets are Indonesia-only while the country field is global. Outside
// Indonesia the whole field is withdrawn rather than degraded to free text -
// same as `AddressFields.vue`, the pattern contacts/hotels/brands already use.
// Loaded on demand: the region list is ~39 KB and most forms never use it.
const regions = shallowRef(null);

const isLocationDependent = computed(() =>
  ["province", "city"].includes(normalized.value.type),
);

watch(
  isLocationDependent,
  async (needed) => {
    if (!needed || regions.value) return;
    regions.value = await import("./indonesiaRegions");
  },
  { immediate: true },
);

/** The answer this field depends on, resolved through `settings.depends_on`. */
const parentValue = computed(() => {
  const key = props.field?.settings?.depends_on;
  return key ? (props.contextValues?.[key] ?? null) : null;
});

// province depends on country; city depends on province, which itself only has
// options inside Indonesia. Walking up one more level keeps the city field from
// offering a dropdown when the country is not Indonesia.
const countryValue = computed(() => {
  if (normalized.value.type === "province") return parentValue.value;
  return props.contextValues?.country ?? null;
});

const locationOptions = computed(() => {
  if (!regions.value || !isLocationDependent.value) return [];
  if (!regions.value.isIndonesia(countryValue.value)) return [];
  return normalized.value.type === "province"
    ? regions.value.INDONESIA_PROVINCES
    : regions.value.citiesForProvinceLabel(parentValue.value);
});

/**
 * The field only exists once the country it hangs off is Indonesia - before
 * that there is nothing to choose from, and a dropdown offering nothing is
 * worse than no dropdown. City stays visible but disabled until a province is
 * picked, which is how `AddressFields.vue` behaves.
 *
 * A withdrawn field must not be required either; see
 * `CustomFieldValidation::errorsFor()`, which skips the same cases server-side.
 */
const isDependentHidden = computed(
  () =>
    isLocationDependent.value &&
    (!regions.value || !regions.value.isIndonesia(countryValue.value)),
);

// A stale child is worse than an empty one: changing province must not leave the
// previous province's city sitting in the answer.
watch(parentValue, (next, prev) => {
  if (prev === undefined || next === prev) return;
  if (isLocationDependent.value && props.modelValue) {
    emit("update:modelValue", null);
  }
});


const fieldId = computed(() => `field-${normalized.value.key}`);

const isRequired = computed(() => !!normalized.value.validation?.required);

/**
 * A group of controls has no single element to point `for` at - `fieldId` is
 * only ever on a lone input, and the options carry `${fieldId}-${value}`. So
 * these types label their group through `aria-labelledby` instead, and the
 * caption drops the `for` that used to dangle.
 */
const isOptionGroup = computed(() =>
  ["radio", "checkbox_group", "linear_scale", "rating"].includes(normalized.value.type),
);

const labelId = computed(() => `${fieldId.value}-label`);

// Two options share a row; three or more stack. `flex-wrap` packs by content
// width, so more than two wrap into columns that never line up.
const isInlineOptions = computed(() => normalized.value.options?.length === 2);

/**
 * One step up from `.cn-label`, and `leading-snug` in place of its `leading-none`
 * so a question that wraps to two lines does not have its descenders sitting on
 * the next line's caps. Kept opt-in: STYLE_GUIDE fixes dashboard labels at
 * `text-sm`, and most call sites here are dashboard forms.
 */
const isLargeLabel = computed(() => props.labelSize === "lg");

const labelClass = computed(() => (isLargeLabel.value ? "text-base leading-snug" : undefined));

const countryOptions = computed(() => props.countries ?? defaultCountries);

/* ----- Multi select ----- */
// The field stores plain values; the combobox works in option objects so chips can show
// labels. These map between the two shapes.
const multiSelectValue = computed(() =>
  (props.modelValue || []).map(
    (v) => normalized.value.options.find((o) => o.value === v) || { value: v, label: v }
  )
);

const emitMultiSelect = (options) => {
  emit(
    "update:modelValue",
    options.map((o) => o.value)
  );
};

const multiSelectQuery = ref("");

const { contains } = useFilter({ sensitivity: "base" });

const multiSelectOptions = computed(() =>
  normalized.value.options.filter(
    (o) => contains(o.label, multiSelectQuery.value) || contains(o.value, multiSelectQuery.value)
  )
);

// reka only auto-clears the search text when the selection changed without typing, so
// picking a filtered option would otherwise leave the query in the field.
watch(
  () => props.modelValue,
  () => {
    multiSelectQuery.value = "";
  }
);

const handleMultiCheck = (checked, value) => {
  const current = props.modelValue || [];
  if (checked) {
    if (limitReached.value && !current.includes(value)) return;
    emit("update:modelValue", [...current, value]);
  } else {
    emit(
      "update:modelValue",
      current.filter((v) => v !== value)
    );
  }
};

/* ----- Selection limits ----- */
// A `max_selections` cap used to be invisible until the server rejected the
// submission. Showing it while the user picks - unselected options go disabled,
// a counter says where they are - saves the round trip.
const isMultiChoice = computed(() =>
  ["multi_select", "checkbox_group", "tags"].includes(normalized.value.type)
);

const maxSelections = computed(() => Number(normalized.value.validation?.max_selections) || null);

const minSelections = computed(() => Number(normalized.value.validation?.min_selections) || null);

const selectedCount = computed(() =>
  Array.isArray(props.modelValue) ? props.modelValue.length : 0
);

const limitReached = computed(
  () => maxSelections.value !== null && selectedCount.value >= maxSelections.value
);

// Selected options stay live at the cap so a choice can always be traded for
// another; only the ones that would push past it go dead.
const isOptionBlocked = (value) =>
  limitReached.value && !(props.modelValue || []).includes(value);

const selectionCounter = computed(() => {
  if (!isMultiChoice.value) return "";
  if (maxSelections.value) return `${selectedCount.value} of ${maxSelections.value} selected`;
  if (minSelections.value) {
    return `${selectedCount.value} selected, at least ${minSelections.value} required`;
  }
  return "";
});

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

/* ----- Price ----- */
// No invented ceiling: an unset max means the amount is open-ended, which is
// the honest default for money. The slider only appears when the author has
// actually set a ceiling and left the toggle on.
const currencySymbol = computed(() => String(normalized.value.settings?.currency || "Rp"));
const priceMin = computed(() => Number(normalized.value.validation?.min ?? 0));
const priceMax = computed(() => {
  const max = normalized.value.validation?.max;
  return max === null || max === undefined || max === "" ? null : Number(max);
});
const priceStep = computed(() => Number(normalized.value.settings?.step) || 1000000);
const showPriceSlider = computed(() => normalized.value.settings?.show_slider !== false);

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
