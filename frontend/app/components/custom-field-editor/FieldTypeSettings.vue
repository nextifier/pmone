<template>
  <!-- Multiple roots on purpose: each block becomes a direct child of the host's
       spaced dialog column, so it inherits the surrounding rhythm. -->

  <!-- Section description -->
  <Field v-if="typeConfig.hasDescription">
    <!-- No `for` here on purpose. TipTapEditor renders a wrapper div as its
         root, so an id lands on the div and not on the contenteditable; a
         label pointing at a non-labelable element is worse than none. Giving
         the editor a real accessible name means an `aria-label` inside its
         `editorProps.attributes`, which is a components/ui change across three
         repos and out of scope here. -->
    <FieldLabel>Description</FieldLabel>
    <TipTapEditor
      v-model="settings.description"
      placeholder="Describe this section (optional)"
      :sticky="false"
      :allow-images="false"
      min-height="120px"
    />
  </Field>

  <Field v-if="typeConfig.hasPlaceholder" :data-invalid="!!errorFor('placeholder')">
    <FieldLabel :for="`${idPrefix}_placeholder`">
      {{ typeConfig.placeholderLabel || "Placeholder" }}
    </FieldLabel>
    <Input
      :aria-invalid="!!errorFor('placeholder')"
      :id="`${idPrefix}_placeholder`"
      v-model="placeholder"
      :placeholder="typeConfig.placeholderLabel ? 'Text shown next to the control' : 'Placeholder text'"
    />
    <FieldError :errors="errorFor('placeholder')" />
  </Field>

  <Field v-if="!isLayout" :data-invalid="!!errorFor('help_text')">
    <FieldLabel :for="`${idPrefix}_help_text`">Help text</FieldLabel>
    <Textarea
      :aria-invalid="!!errorFor('help_text')"
      :id="`${idPrefix}_help_text`"
      v-model="helpText"
      :rows="2"
      placeholder="Optional hint shown below the field"
    />
    <FieldError :errors="errorFor('help_text')" />
  </Field>

  <!-- Each host keeps its own options editor (pairs vs plain strings,
       predefined fields read-only), but it belongs in this slot so the field
       order stays identical everywhere. -->
  <slot name="options" />

  <!-- Validation & settings -->
  <div v-if="!isLayout" class="frame">
    <div class="frame-header">
      <h4 class="frame-title">Validation</h4>
    </div>
    <div class="frame-panel space-y-4">
      <div class="flex items-center gap-x-2">
        <Switch :id="`${idPrefix}_required`" v-model="validation.required" />
        <Label :for="`${idPrefix}_required`" class="font-normal">Required</Label>
      </div>

      <!-- Min / max -->
      <div v-if="typeConfig.minMaxMode" class="grid grid-cols-2 gap-x-2 gap-y-4">
        <Field>
          <FieldLabel :for="`${idPrefix}_min`">{{ minMaxLabels.min }}</FieldLabel>
          <InputNumber :id="`${idPrefix}_min`" v-model="validation[minMaxKeys.min]" placeholder="None" />
        </Field>
        <Field>
          <FieldLabel :for="`${idPrefix}_max`">{{ minMaxLabels.max }}</FieldLabel>
          <InputNumber :id="`${idPrefix}_max`" v-model="validation[minMaxKeys.max]" placeholder="None" />
        </Field>
      </div>

      <!-- Linear scale labels -->
      <div v-if="typeConfig.hasScaleLabels" class="grid grid-cols-2 gap-x-2 gap-y-4">
        <Field>
          <FieldLabel :for="`${idPrefix}_min_label`">Low label</FieldLabel>
          <Input :id="`${idPrefix}_min_label`" v-model="settings.min_label" placeholder="e.g. Poor" />
        </Field>
        <Field>
          <FieldLabel :for="`${idPrefix}_max_label`">High label</FieldLabel>
          <Input :id="`${idPrefix}_max_label`" v-model="settings.max_label" placeholder="e.g. Excellent" />
        </Field>
      </div>

      <!-- Slider step -->
      <Field v-if="typeConfig.hasStep">
        <FieldLabel :for="`${idPrefix}_step`">Step</FieldLabel>
        <InputNumber :id="`${idPrefix}_step`" v-model="settings.step" decimal placeholder="1" />
      </Field>

      <!-- Currency symbol -->
      <Field v-if="typeConfig.hasCurrency">
        <FieldLabel :for="`${idPrefix}_currency`">Currency symbol</FieldLabel>
        <Input :id="`${idPrefix}_currency`" v-model="settings.currency" placeholder="Rp" />
      </Field>

      <!-- Range slider -->
      <div v-if="typeConfig.hasSliderToggle" class="space-y-2">
        <div class="flex items-center gap-x-2">
          <Switch :id="`${idPrefix}_show_slider`" v-model="settings.show_slider" />
          <Label :for="`${idPrefix}_show_slider`" class="font-normal">Show range slider</Label>
        </div>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Needs a max value. Leave the max empty when the amount is open-ended - the
          two fields then accept any figure, with no ceiling implied.
        </p>
      </div>

      <!-- Rating max -->
      <Field v-if="typeConfig.hasRatingMax">
        <FieldLabel :for="`${idPrefix}_rating_max`">Number of stars</FieldLabel>
        <InputNumber :id="`${idPrefix}_rating_max`" v-model="settings.max" :min="2" :max="10" placeholder="5" />
      </Field>

      <!-- File config -->
      <template v-if="showFileConfig">
        <div class="flex items-center gap-x-2">
          <Switch :id="`${idPrefix}_multiple`" v-model="settings.multiple" />
          <Label :for="`${idPrefix}_multiple`" class="font-normal">Allow multiple files</Label>
        </div>

        <div class="grid grid-cols-2 gap-x-2 gap-y-4">
          <Field v-if="settings.multiple">
            <FieldLabel :for="`${idPrefix}_max_files`">Max files</FieldLabel>
            <InputNumber :id="`${idPrefix}_max_files`" v-model="validation.max_files" :min="1" :max="10" placeholder="5" />
          </Field>
          <Field>
            <FieldLabel :for="`${idPrefix}_max_size`">Max file size (MB)</FieldLabel>
            <InputNumber :id="`${idPrefix}_max_size`" v-model="maxFileSizeMb" :min="1" :max="20" placeholder="20" />
          </Field>
        </div>

        <Field>
          <FieldLabel :for="`${idPrefix}_file_types`">Allowed file types</FieldLabel>
          <TagsInput :id="`${idPrefix}_file_types`" v-model="validation.allowed_file_types" class="text-sm">
            <TagsInputItem v-for="ext in validation.allowed_file_types" :key="ext" :value="ext">
              <TagsInputItemText />
              <TagsInputItemDelete />
            </TagsInputItem>
            <TagsInputInput placeholder="pdf, docx, jpg" />
          </TagsInput>
          <p class="text-muted-foreground text-xs">
            File extensions without the dot. Leave empty to allow any type.
          </p>
        </Field>
      </template>
    </div>
  </div>

  <!-- Advanced -->
  <div v-if="showPrefill" class="frame">
    <div class="frame-header">
      <h4 class="frame-title">Advanced</h4>
    </div>
    <div class="frame-panel">
      <Field :data-invalid="!!errors?.['settings.param_key']">
        <FieldLabel :for="`${idPrefix}_param_key`">URL parameter key</FieldLabel>
        <Input
          :id="`${idPrefix}_param_key`"
          v-model="settings.param_key"
          placeholder="e.g. ticket"
          :aria-invalid="!!errors?.['settings.param_key']"
        />
        <p class="text-muted-foreground text-xs">
          Prefill this field from the public URL, e.g. ?ticket=vip. Letters, numbers, dashes and
          underscores only.
        </p>
        <FieldError :errors="errors['settings.param_key']" />
      </Field>
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputNumber } from "@/components/ui/input-number";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Textarea } from "@/components/ui/textarea";
import { getTypeConfig, resolveEditorType, supportsPrefill } from "@/lib/formFieldTypes";
import { computed, defineAsyncComponent } from "vue";

defineOptions({ inheritAttrs: false });

// Only the `section` type needs it, and section is excluded from every context
// outside the Form Builder - this keeps the editor out of the other bundles.
const TipTapEditor = defineAsyncComponent(() =>
  import("@/components/ui/tip-tap-editor").then((m) => m.TipTapEditor)
);

const props = defineProps({
  // Raw field type; aliases (year_select) are resolved before reading flags.
  type: { type: String, required: true },
  errors: { type: Object, default: () => ({}) },
  // Contexts with translatable placeholder/help text key their 422 errors per
  // locale ("placeholder.en"), so the host tells us which tab is open.
  errorLocale: { type: String, default: null },
  // The URL prefill key only makes sense for public Form Builder forms.
  allowPrefill: { type: Boolean, default: false },
  // Contexts without an upload pipeline exclude the file type outright, but the
  // flag lets a host opt out explicitly.
  allowFileConfig: { type: Boolean, default: true },
  idPrefix: { type: String, default: "field" },
});

const placeholder = defineModel("placeholder", { type: String, default: "" });
const helpText = defineModel("helpText", { type: String, default: "" });
// Plain objects owned by the host: nested keys are mutated in place so the
// host's reactive state updates without round-tripping through an emit.
const validation = defineModel("validation", { type: Object, required: true });
const settings = defineModel("settings", { type: Object, required: true });

const typeConfig = computed(() => getTypeConfig(resolveEditorType(props.type)));
const isLayout = computed(() => Boolean(typeConfig.value.isLayout));
const showFileConfig = computed(() => typeConfig.value.hasFileConfig && props.allowFileConfig);
const showPrefill = computed(() => props.allowPrefill && supportsPrefill(props.type));

// Translatable payloads come back keyed per locale ("placeholder.en") even for
// the single-language Form Builder, so fall back to English when no tab is open.
const errorFor = (key) =>
  props.errors[`${key}.${props.errorLocale ?? "en"}`] ?? props.errors[key] ?? null;

const minMaxLabels = computed(() => {
  switch (typeConfig.value.minMaxMode) {
    case "length":
      return { min: "Min length", max: "Max length" };
    case "selections":
      return { min: "Min selections", max: "Max selections" };
    case "scale":
      return { min: "Scale from", max: "Scale to" };
    default:
      return { min: "Min value", max: "Max value" };
  }
});

const minMaxKeys = computed(() =>
  typeConfig.value.minMaxMode === "selections"
    ? { min: "min_selections", max: "max_selections" }
    : { min: "min", max: "max" }
);

const maxFileSizeMb = computed({
  get: () =>
    validation.value.max_file_size ? Math.round(validation.value.max_file_size / 1024) : null,
  set: (value) => {
    validation.value.max_file_size = value ? value * 1024 : null;
  },
});
</script>
