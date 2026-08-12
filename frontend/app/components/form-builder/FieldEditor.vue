<template>
  <!--
    `@container/editor` rather than `sm:`/`lg:`: this renders inside a dialog on
    desktop and a drawer on mobile, so the space it actually has is the dialog's
    width, not the window's.
  -->
  <!--
    `keydown.enter` rather than a `<form>`: this renders inside a dialog, and a
    real form here would submit the page behind it (DESIGN.md, Dialogs).
    Textareas and the rich-text editor keep Enter for newlines.
  -->
  <div class="@container/editor space-y-5" @keydown.enter="onEnter">
    <div class="flex flex-col gap-5 @4xl/editor:flex-row @4xl/editor:items-start">
    <div class="min-w-0 flex-1 space-y-5">
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
            <!-- A rejected translation is invisible from any other tab, so the
                 tab itself has to say that something over there needs fixing. -->
            <span
              v-if="localesWithErrors.includes(locale.value)"
              class="bg-destructive ms-1.5 size-1.5 rounded-full"
              :aria-label="`${locale.label} has an error`"
            />
          </TabsTrigger>
        </TabsList>
      </Tabs>
      <p class="text-muted-foreground text-xs tracking-tight">
        The selected language applies to the label, placeholder and help text below.
      </p>
    </div>

    <Field :data-invalid="!!localizedLabelErrors">
      <FieldLabel for="field_label">{{ isSection ? "Section title" : "Label" }}</FieldLabel>
      <Input
        id="field_label"
        v-model="labelField"
        :required="activeLocale === 'en'"
        :placeholder="isSection ? 'Section title' : 'Field label'"
        :aria-invalid="!!localizedLabelErrors"
      />
      <FieldError :errors="localizedLabelErrors" />
    </Field>

    <Field :data-invalid="!!errors?.type">
      <FieldLabel for="field_type">Field type</FieldLabel>
      <FieldTypeCombobox
        id="field_type"
        :model-value="fieldForm.type"
        :disabled="!!editingField"
        :aria-invalid="!!errors?.type"
        @update:model-value="changeType"
      />
      <!-- Locked while editing: the stored answers are shaped by the type, so
           switching it would leave every existing response unreadable. -->
      <p v-if="editingField" class="text-muted-foreground text-xs tracking-tight">
        Field type is fixed once the field exists. Delete it and add a new one to change it.
      </p>
      <FieldError :errors="errors.type" />
    </Field>

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
        <OptionsEditor
          v-if="typeConfig.hasOptions"
          v-model="fieldForm.options"
          mode="pairs"
          allow-bulk
          :errors="errors.options"
          :locale="activeLocale"
          id-prefix="field"
        />
      </template>
    </FieldTypeSettings>
    </div>

      <!--
        Sticky, not just side-by-side: the settings column runs longer than the
        preview on almost every type, and the whole point is watching the field
        change while you tune validation two screens down.

        `top-12` rather than `top-0`: the dialog's close button is absolutely
        positioned at its top-right and does not scroll, so a frame pinned flush
        to the top of the scrollport lands underneath it.

        `order-first` while stacked, which is what the mobile drawer always is:
        side by side is impossible there, and last in the column means the
        preview sits below two frames of validation where nobody sees it.
      -->
      <div class="order-first shrink-0 @4xl/editor:order-none @4xl/editor:sticky @4xl/editor:top-12 @4xl/editor:w-80">
        <FieldPreviewFrame :field="previewField" :locale="activeLocale" label-size="lg" />
      </div>
    </div>

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
import OptionsEditor from "@/components/custom-field-editor/OptionsEditor.vue";
import FieldTypeCombobox from "@/components/custom-field-editor/FieldTypeCombobox.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import {
  buildSettingsPayload,
  buildTranslatablePayload,
  buildValidationPayload,
  cleanTranslatable,
  FIELD_LOCALE_TABS,
} from "@/lib/customFieldEditor";
import { getTypeConfig, getTypeLabel } from "@/lib/formFieldTypes";
import { toast } from "vue-sonner";

const props = defineProps({
  formSlug: { type: String, required: true },
  editingField: { type: Object, default: null },
});

const emit = defineEmits(["saved", "cancel"]);

const client = useSanctumClient();
const saving = ref(false);

const {
  form: fieldForm,
  errors,
  activeLocale,
  typeConfig,
  labelField,
  placeholderField,
  helpTextField,
  localizedLabelErrors,
  localesWithErrors,
  hasEnglishLabel,
  previewField,
  localProblem,
  isDirty,
  loadField,
  takeSnapshot,
  applyServerErrors,
} = useCustomFieldForm();

if (props.editingField) {
  loadField(props.editingField);
}
takeSnapshot();

const isSection = computed(() => fieldForm.type === "section");

defineExpose({ isDirty });

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

const onEnter = (event) => {
  const el = event.target;
  if (el?.tagName === "TEXTAREA" || el?.isContentEditable) return;
  // The combobox and any other listbox own Enter for choosing an item.
  if (el?.getAttribute?.("role") === "combobox" && el.getAttribute("aria-expanded") === "true") {
    return;
  }
  event.preventDefault();
  save();
};

/* ----- Save ----- */
const save = async () => {
  const problem = localProblem.value;
  if (problem) {
    if (problem.locale) activeLocale.value = problem.locale;
    toast.error(problem.message);
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
    // A 422 already speaks through the inline messages; a toast on top of them
    // says the same thing twice and covers the fields it is talking about.
    if (applyServerErrors(e)) return;
    toast.error(e?.data?.message || e?.response?._data?.message || "Failed to save field");
  } finally {
    saving.value = false;
  }
};
</script>
