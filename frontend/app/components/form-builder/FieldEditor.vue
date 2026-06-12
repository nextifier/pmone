<template>
  <div class="space-y-5">
    <!-- Type selector (only for new fields) -->
    <div v-if="!editingField" class="space-y-2">
      <Label>Field type</Label>
      <FieldTypeSelector :selected="fieldForm.type" @select="changeType" />
    </div>

    <div v-else class="flex items-center gap-x-2">
      <span class="text-muted-foreground text-sm tracking-tight">Type:</span>
      <span class="bg-muted text-muted-foreground flex items-center gap-x-1.5 rounded px-1.5 py-0.5 text-xs tracking-tight sm:text-sm">
        <Icon :name="getTypeIcon(fieldForm.type)" class="size-3.5" />
        {{ getTypeLabel(fieldForm.type) }}
      </span>
    </div>

    <div class="space-y-2">
      <Label for="field_label">{{ isSection ? "Section title" : "Label" }}</Label>
      <Input
        id="field_label"
        v-model="fieldForm.label"
        :placeholder="isSection ? 'Section title' : 'Field label'"
        :class="{ 'border-destructive': errors.label }"
      />
      <InputErrorMessage :errors="errors.label" />
    </div>

    <!-- Section description -->
    <div v-if="typeConfig.hasDescription" class="space-y-2">
      <Label>Description</Label>
      <TipTapEditor
        v-model="fieldForm.settings.description"
        placeholder="Describe this section (optional)"
        :sticky="false"
        :allow-images="false"
        min-height="120px"
      />
    </div>

    <div v-if="typeConfig.hasPlaceholder" class="space-y-2">
      <Label for="field_placeholder">{{ typeConfig.placeholderLabel || "Placeholder" }}</Label>
      <Input
        id="field_placeholder"
        v-model="fieldForm.placeholder"
        :placeholder="typeConfig.placeholderLabel ? 'Text shown next to the control' : 'Placeholder text'"
      />
    </div>

    <div v-if="!isSection" class="space-y-2">
      <Label for="field_help_text">Help text</Label>
      <Textarea
        id="field_help_text"
        v-model="fieldForm.help_text"
        :rows="2"
        placeholder="Optional hint shown below the field"
      />
    </div>

    <!-- Options -->
    <div v-if="typeConfig.hasOptions" class="space-y-2">
      <Label>Options</Label>
      <div class="space-y-2">
        <div
          v-for="(option, idx) in fieldForm.options"
          :key="idx"
          class="flex items-center gap-x-2"
        >
          <Input
            v-model="fieldForm.options[idx].label"
            placeholder="Option label"
            class="flex-1"
          />
          <Input
            v-model="fieldForm.options[idx].value"
            placeholder="Value"
            class="w-24 sm:w-28"
          />
          <Button
            type="button"
            variant="ghost"
            size="iconSm"
            class="text-muted-foreground hover:text-destructive shrink-0"
            @click="fieldForm.options.splice(idx, 1)"
          >
            <Icon name="lucide:x" class="size-4" />
          </Button>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Button type="button" variant="outline" size="sm" @click="addOption">
          <Icon name="lucide:plus" class="size-3.5" />
          <span>Add option</span>
        </Button>
        <Button type="button" variant="ghost" size="sm" @click="showBulkAdd = !showBulkAdd">
          <Icon name="lucide:list-plus" class="size-3.5" />
          <span>Bulk add</span>
        </Button>
      </div>
      <div v-if="showBulkAdd" class="space-y-2">
        <Textarea
          v-model="bulkOptionsText"
          :rows="4"
          placeholder="One option per line. Use label|value to set a custom value."
        />
        <Button type="button" size="sm" :disabled="!bulkOptionsText.trim()" @click="applyBulkOptions">
          Add {{ bulkOptionsCount }} {{ bulkOptionsCount === 1 ? "option" : "options" }}
        </Button>
      </div>
      <InputErrorMessage :errors="errors.options" />
    </div>

    <!-- Validation & settings -->
    <div v-if="!isSection" class="frame">
      <div class="frame-header">
        <h4 class="frame-title">Validation</h4>
      </div>
      <div class="frame-panel space-y-4">
        <div class="flex items-center gap-x-2">
          <Switch id="field_required" v-model="fieldForm.validation.required" />
          <Label for="field_required" class="font-normal">Required</Label>
        </div>

        <!-- Min / max -->
        <div v-if="typeConfig.minMaxMode" class="grid grid-cols-2 gap-x-2 gap-y-4">
          <div class="space-y-2">
            <Label>{{ minMaxLabels.min }}</Label>
            <InputNumber v-model="fieldForm.validation[minMaxKeys.min]" placeholder="None" />
          </div>
          <div class="space-y-2">
            <Label>{{ minMaxLabels.max }}</Label>
            <InputNumber v-model="fieldForm.validation[minMaxKeys.max]" placeholder="None" />
          </div>
        </div>

        <!-- Linear scale labels -->
        <div v-if="typeConfig.hasScaleLabels" class="grid grid-cols-2 gap-x-2 gap-y-4">
          <div class="space-y-2">
            <Label for="field_min_label">Low label</Label>
            <Input id="field_min_label" v-model="fieldForm.settings.min_label" placeholder="e.g. Poor" />
          </div>
          <div class="space-y-2">
            <Label for="field_max_label">High label</Label>
            <Input id="field_max_label" v-model="fieldForm.settings.max_label" placeholder="e.g. Excellent" />
          </div>
        </div>

        <!-- Slider step -->
        <div v-if="typeConfig.hasStep" class="space-y-2">
          <Label>Step</Label>
          <InputNumber v-model="fieldForm.settings.step" decimal placeholder="1" />
        </div>

        <!-- Rating max -->
        <div v-if="typeConfig.hasRatingMax" class="space-y-2">
          <Label>Number of stars</Label>
          <InputNumber v-model="fieldForm.settings.max" :min="2" :max="10" placeholder="5" />
        </div>

        <!-- File config -->
        <template v-if="typeConfig.hasFileConfig">
          <div class="flex items-center gap-x-2">
            <Switch id="field_multiple" v-model="fieldForm.settings.multiple" />
            <Label for="field_multiple" class="font-normal">Allow multiple files</Label>
          </div>

          <div class="grid grid-cols-2 gap-x-2 gap-y-4">
            <div v-if="fieldForm.settings.multiple" class="space-y-2">
              <Label>Max files</Label>
              <InputNumber v-model="fieldForm.validation.max_files" :min="1" :max="10" placeholder="5" />
            </div>
            <div class="space-y-2">
              <Label>Max file size (MB)</Label>
              <InputNumber v-model="maxFileSizeMb" :min="1" :max="20" placeholder="20" />
            </div>
          </div>

          <div class="space-y-2">
            <Label>Allowed file types</Label>
            <TagsInput v-model="fieldForm.validation.allowed_file_types" class="text-sm">
              <TagsInputItem
                v-for="ext in fieldForm.validation.allowed_file_types"
                :key="ext"
                :value="ext"
              >
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="pdf, docx, jpg..." />
            </TagsInput>
            <p class="text-muted-foreground text-xs">
              File extensions without the dot. Leave empty to allow any type.
            </p>
          </div>
        </template>
      </div>
    </div>

    <!-- Advanced -->
    <div v-if="supportsPrefill(fieldForm.type)" class="frame">
      <div class="frame-header">
        <h4 class="frame-title">Advanced</h4>
      </div>
      <div class="frame-panel">
        <div class="space-y-2">
          <Label for="field_param_key">URL parameter key</Label>
          <Input
            id="field_param_key"
            v-model="fieldForm.settings.param_key"
            placeholder="e.g. ticket"
            :class="{ 'border-destructive': errors['settings.param_key'] }"
          />
          <p class="text-muted-foreground text-xs">
            Prefill this field from the public URL, e.g. ?ticket=vip. Letters, numbers, dashes and underscores only.
          </p>
          <InputErrorMessage :errors="errors['settings.param_key']" />
        </div>
      </div>
    </div>

    <!-- Live preview -->
    <div class="frame">
      <div class="frame-header">
        <h4 class="frame-title">Preview</h4>
      </div>
      <div class="frame-panel">
        <PublicFieldRenderer
          :key="previewKey"
          :field="previewField"
          v-model="previewValue"
          preview
        />
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" :disabled="saving" @click="$emit('cancel')">
        Cancel
      </Button>
      <Button type="button" :disabled="saving || !fieldForm.label || !fieldForm.type" @click="save">
        <Spinner v-if="saving" class="size-4" />
        <span>{{ editingField ? "Update field" : "Add field" }}</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import FieldTypeSelector from "@/components/form-builder/FieldTypeSelector.vue";
import PublicFieldRenderer from "@/components/form-builder/PublicFieldRenderer.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
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
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import {
  defaultValueFor,
  getTypeConfig,
  getTypeIcon,
  getTypeLabel,
  supportsPrefill,
} from "@/lib/formFieldTypes";
import { toast } from "vue-sonner";

const props = defineProps({
  formSlug: { type: String, required: true },
  editingField: { type: Object, default: null },
});

const emit = defineEmits(["saved", "cancel"]);

const client = useSanctumClient();
const saving = ref(false);
const errors = ref({});

const emptyForm = () => ({
  type: "text",
  label: "",
  placeholder: "",
  help_text: "",
  options: [],
  validation: {
    required: false,
    min: null,
    max: null,
    min_selections: null,
    max_selections: null,
    max_file_size: null,
    max_files: null,
    allowed_file_types: [],
  },
  settings: {
    multiple: false,
    step: null,
    max: null,
    min_label: "",
    max_label: "",
    description: "",
    param_key: "",
  },
});

const fieldForm = reactive(emptyForm());

if (props.editingField) {
  const field = props.editingField;
  fieldForm.type = field.type;
  fieldForm.label = field.label;
  fieldForm.placeholder = field.placeholder || "";
  fieldForm.help_text = field.help_text || "";
  fieldForm.options = (field.options || []).map((o) =>
    typeof o === "string" ? { label: o, value: o } : { ...o }
  );
  fieldForm.validation = {
    ...emptyForm().validation,
    ...(field.validation || {}),
    allowed_file_types: field.validation?.allowed_file_types || [],
  };
  fieldForm.settings = {
    ...emptyForm().settings,
    ...(field.settings || {}),
  };
}

const typeConfig = computed(() => getTypeConfig(fieldForm.type));
const isSection = computed(() => fieldForm.type === "section");

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
    fieldForm.validation.max_file_size ? Math.round(fieldForm.validation.max_file_size / 1024) : null,
  set: (value) => {
    fieldForm.validation.max_file_size = value ? value * 1024 : null;
  },
});

const changeType = (type) => {
  fieldForm.type = type;
  errors.value = {};
  if (getTypeConfig(type).hasOptions && !fieldForm.options.length) {
    fieldForm.options = [
      { label: "Option 1", value: "option-1" },
      { label: "Option 2", value: "option-2" },
    ];
  }
};

const addOption = () => {
  fieldForm.options.push({ label: "", value: "" });
};

/* ----- Bulk add options ----- */
const showBulkAdd = ref(false);
const bulkOptionsText = ref("");

const parseBulkOptions = () =>
  bulkOptionsText.value
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [label, value] = line.split("|").map((part) => part.trim());
      return { label, value: value || label.toLowerCase().replace(/\s+/g, "-") };
    });

const bulkOptionsCount = computed(() => parseBulkOptions().length);

const applyBulkOptions = () => {
  fieldForm.options = [
    ...fieldForm.options.filter((o) => o.label || o.value),
    ...parseBulkOptions(),
  ];
  bulkOptionsText.value = "";
  showBulkAdd.value = false;
};

/* ----- Live preview ----- */
const previewValue = ref(null);
const previewKey = computed(() => fieldForm.type);

const previewField = computed(() => ({
  ulid: "preview",
  type: fieldForm.type,
  label: fieldForm.label || getTypeLabel(fieldForm.type),
  placeholder: fieldForm.placeholder || null,
  help_text: fieldForm.help_text || null,
  options: fieldForm.options.filter((o) => o.label || o.value),
  validation: { ...fieldForm.validation },
  settings: { ...fieldForm.settings },
}));

watch(
  () => fieldForm.type,
  () => {
    previewValue.value = defaultValueFor(previewField.value);
  },
  { immediate: true }
);

/* ----- Save ----- */
const buildValidationPayload = () => {
  const validation = { required: fieldForm.validation.required };
  const mode = typeConfig.value.minMaxMode;

  if (mode === "selections") {
    if (fieldForm.validation.min_selections != null)
      validation.min_selections = fieldForm.validation.min_selections;
    if (fieldForm.validation.max_selections != null)
      validation.max_selections = fieldForm.validation.max_selections;
  } else if (mode) {
    if (fieldForm.validation.min != null) validation.min = fieldForm.validation.min;
    if (fieldForm.validation.max != null) validation.max = fieldForm.validation.max;
  }

  if (typeConfig.value.hasFileConfig) {
    if (fieldForm.validation.max_file_size) validation.max_file_size = fieldForm.validation.max_file_size;
    if (fieldForm.settings.multiple && fieldForm.validation.max_files)
      validation.max_files = fieldForm.validation.max_files;
    if (fieldForm.validation.allowed_file_types?.length)
      validation.allowed_file_types = fieldForm.validation.allowed_file_types.map((ext) =>
        String(ext).toLowerCase().replace(/^\./, "")
      );
  }

  return validation;
};

const buildSettingsPayload = () => {
  const settings = {};

  if (typeConfig.value.hasDescription && fieldForm.settings.description)
    settings.description = fieldForm.settings.description;
  if (typeConfig.value.hasStep && fieldForm.settings.step) settings.step = fieldForm.settings.step;
  if (typeConfig.value.hasRatingMax && fieldForm.settings.max) settings.max = fieldForm.settings.max;
  if (typeConfig.value.hasScaleLabels) {
    if (fieldForm.settings.min_label) settings.min_label = fieldForm.settings.min_label;
    if (fieldForm.settings.max_label) settings.max_label = fieldForm.settings.max_label;
  }
  if (typeConfig.value.hasFileConfig) settings.multiple = !!fieldForm.settings.multiple;
  if (supportsPrefill(fieldForm.type) && fieldForm.settings.param_key)
    settings.param_key = fieldForm.settings.param_key;

  return settings;
};

const save = async () => {
  if (!fieldForm.label || !fieldForm.type) return;
  saving.value = true;
  errors.value = {};

  try {
    const body = {
      type: fieldForm.type,
      label: fieldForm.label,
      placeholder: fieldForm.placeholder || null,
      help_text: fieldForm.help_text || null,
      options: typeConfig.value.hasOptions
        ? fieldForm.options
            .filter((o) => o.label || o.value)
            .map((o) => ({ label: o.label || o.value, value: o.value || o.label }))
        : null,
      validation: buildValidationPayload(),
      settings: buildSettingsPayload(),
    };

    if (props.editingField) {
      await client(`/api/forms/${props.formSlug}/fields/${props.editingField.ulid}`, {
        method: "PUT",
        body,
      });
      toast.success("Field updated");
    } else {
      await client(`/api/forms/${props.formSlug}/fields`, {
        method: "POST",
        body,
      });
      toast.success("Field added");
    }

    emit("saved");
  } catch (e) {
    if (e.response?.status === 422 && e.response?._data?.errors) {
      errors.value = e.response._data.errors;
    }
    toast.error(e?.data?.message || e?.response?._data?.message || "Failed to save field");
  } finally {
    saving.value = false;
  }
};
</script>
