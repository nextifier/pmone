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
      <Tabs v-model="activeLocale" variant="segmented">
        <TabsList>
          <TabsIndicator />
          <TabsTrigger
            v-for="locale in FIELD_LOCALE_TABS"
            :key="locale.value"
            :value="locale.value"
          >
            {{ locale.label }}
          </TabsTrigger>
        </TabsList>
      </Tabs>
      <p class="text-muted-foreground text-xs tracking-tight">
        The selected language applies to the label, placeholder and help text below.
      </p>
    </div>

    <div class="space-y-2">
      <Label for="field_label">{{ isSection ? "Section title" : "Label" }}</Label>
      <Input
        id="field_label"
        v-model="labelField"
        :required="activeLocale === 'en'"
        :placeholder="isSection ? 'Section title' : 'Field label'"
        :class="{ 'border-destructive': localizedLabelErrors }"
      />
      <FieldError :errors="localizedLabelErrors" />
    </div>

    <FieldTypeSettings
      v-model:placeholder="placeholderField"
      v-model:help-text="helpTextField"
      :error-locale="activeLocale"
      v-model:validation="fieldForm.validation"
      v-model:settings="fieldForm.settings"
      :type="fieldForm.type"
      :errors="errors"
      allow-prefill
    >
      <template #options>
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
          <FieldError :errors="errors.options" />
        </div>
      </template>
    </FieldTypeSettings>

    <FieldPreviewFrame :field="previewField" :locale="activeLocale" label-size="lg" />

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" :disabled="saving" @click="$emit('cancel')">
        Cancel
      </Button>
      <Button type="button" :disabled="saving || !hasEnglishLabel || !fieldForm.type" @click="save">
        <Spinner v-if="saving" class="size-4" />
        <span>{{ editingField ? "Update field" : "Add field" }}</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import FieldPreviewFrame from "@/components/custom-field-editor/FieldPreviewFrame.vue";
import FieldTypeSettings from "@/components/custom-field-editor/FieldTypeSettings.vue";
import FieldTypeSelector from "@/components/form-builder/FieldTypeSelector.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import {
  buildSettingsPayload,
  buildTranslatablePayload,
  buildValidationPayload,
  cleanTranslatable,
  emptyFieldState,
  FIELD_LOCALE_TABS,
  hydrateFieldState,
  previewFieldFrom,
} from "@/lib/customFieldEditor";
import { getTypeConfig, getTypeIcon, getTypeLabel } from "@/lib/formFieldTypes";
import { toast } from "vue-sonner";

const props = defineProps({
  formSlug: { type: String, required: true },
  editingField: { type: Object, default: null },
});

const emit = defineEmits(["saved", "cancel"]);

const client = useSanctumClient();
const saving = ref(false);
const errors = ref({});
const activeLocale = ref("en");

const fieldForm = reactive(
  props.editingField ? hydrateFieldState(props.editingField) : emptyFieldState()
);

const typeConfig = computed(() => getTypeConfig(fieldForm.type));
const isSection = computed(() => fieldForm.type === "section");

// One language tab drives all three translatable inputs, same as the brand,
// ticket and ops-document editors.
const translatableProxy = (key) =>
  computed({
    get: () => fieldForm[key][activeLocale.value] ?? "",
    set: (value) => {
      fieldForm[key] = { ...fieldForm[key], [activeLocale.value]: value };
    },
  });

const labelField = translatableProxy("label");
const placeholderField = translatableProxy("placeholder");
const helpTextField = translatableProxy("help_text");

const localizedLabelErrors = computed(
  () => errors.value[`label.${activeLocale.value}`] ?? errors.value.label ?? null
);

// English is the only required translation, so it also gates the save button.
const hasEnglishLabel = computed(() => Boolean(String(fieldForm.label.en ?? "").trim()));

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
const previewField = computed(() =>
  previewFieldFrom(fieldForm, {
    label: Object.keys(cleanTranslatable(fieldForm.label)).length
      ? fieldForm.label
      : { en: getTypeLabel(fieldForm.type) },
    options: fieldForm.options.filter((o) => o.label || o.value),
  })
);

/* ----- Save ----- */
const save = async () => {
  if (!fieldForm.type) return;

  if (!String(fieldForm.label.en ?? "").trim()) {
    activeLocale.value = "en";
    toast.error("English label is required");
    return;
  }

  saving.value = true;
  errors.value = {};

  try {
    const label = cleanTranslatable(fieldForm.label);
    label.en = String(fieldForm.label.en).trim();

    const body = {
      type: fieldForm.type,
      label,
      // Every locale is sent, "" for the empty ones, so clearing an input
      // actually clears that translation instead of leaving it behind.
      placeholder: buildTranslatablePayload(fieldForm.placeholder),
      help_text: buildTranslatablePayload(fieldForm.help_text),
      options: typeConfig.value.hasOptions
        ? fieldForm.options
            .filter((o) => o.label || o.value)
            .map((o) => ({ label: o.label || o.value, value: o.value || o.label }))
        : null,
      validation: buildValidationPayload(fieldForm, fieldForm.type),
      settings: buildSettingsPayload(fieldForm, fieldForm.type, { prefill: true }),
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
