<template>
  <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
    <h3 class="text-lg font-semibold tracking-tighter">
      {{ editing ? "Edit field" : "Add field" }}
    </h3>

    <form @submit.prevent="$emit('submit')" class="mt-4 space-y-4">
      <div class="space-y-2">
        <Tabs :model-value="locale" variant="segmented" @update:model-value="$emit('update:locale', $event)">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger
              v-for="tab in FIELD_LOCALE_TABS"
              :key="tab.value"
              :value="tab.value"
            >
              {{ tab.label }}
              <!-- A rejected translation is invisible from any other tab, so the
                   tab itself has to say that something over there needs fixing. -->
              <span
                v-if="localesWithErrors.includes(tab.value)"
                class="bg-destructive ms-1.5 size-1.5 rounded-full"
                :aria-label="`${tab.label} has an error`"
              />
            </TabsTrigger>
          </TabsList>
        </Tabs>
        <p class="text-muted-foreground text-xs tracking-tight">
          The selected language applies to the label, placeholder and help text below.
        </p>
      </div>

      <Field :data-invalid="!!labelErrors">
        <FieldLabel :for="`${idPrefix}-label`">Label</FieldLabel>
        <Input
          :id="`${idPrefix}-label`"
          :model-value="labelValue"
          :aria-invalid="!!labelErrors"
          :required="locale === 'en'"
          :placeholder="locale === 'en' ? labelPlaceholder : labelPlaceholderLocalized"
          @update:model-value="$emit('update:labelValue', $event)"
        />
        <FieldError :errors="labelErrors" />
      </Field>

      <Field :data-invalid="!!errors?.type">
        <FieldLabel :for="`${idPrefix}_type`">Field type</FieldLabel>
        <FieldTypeCombobox
          :id="`${idPrefix}_type`"
          :model-value="form.type"
          :exclude="excludeTypes"
          :extra-types="extraTypes"
          :disabled="typeLocked"
          :aria-invalid="!!errors?.type"
          @update:model-value="$emit('update:type', $event)"
        />
        <p v-if="typeLocked && typeLockedNote" class="text-muted-foreground text-xs tracking-tight">
          {{ typeLockedNote }}
        </p>
        <FieldError :errors="errors.type" />
      </Field>

      <FieldTypeSettings
        :placeholder="placeholderValue"
        :help-text="helpTextValue"
        :validation="form.validation"
        :settings="form.settings"
        :type="settingsType"
        :errors="errors"
        :error-locale="locale"
        :allow-file-config="allowFileConfig"
        :allow-prefill="allowPrefill"
        :id-prefix="idPrefix"
        @update:placeholder="$emit('update:placeholderValue', $event)"
        @update:help-text="$emit('update:helpTextValue', $event)"
        @update:validation="form.validation = $event"
        @update:settings="form.settings = $event"
      >
        <template #options>
          <OptionsEditor
            v-if="showOptions"
            v-model="form.options"
            :mode="optionsMode"
            :allow-bulk="!optionsReadonly"
            :readonly="optionsReadonly"
            :readonly-note="optionsReadonlyNote"
            :errors="errors.options"
            :locale="locale"
            :id-prefix="idPrefix"
          />
        </template>
      </FieldTypeSettings>

      <!-- Where a surface adds something of its own: the brand editor's public
           toggle, an Active switch, anything else that is not shared. -->
      <slot name="extra" />

      <FieldPreviewFrame :field="previewField" :locale="locale" :disabled="previewDisabled" />

      <div class="flex justify-end gap-2 pt-2">
        <Button variant="outline" type="button" @click="$emit('cancel')">Cancel</Button>
        <Button type="submit" :disabled="saving">
          <Spinner v-if="saving" />
          {{ editing ? "Save field" : "Add field" }}
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
/**
 * The body of a custom-field Add/Edit dialog.
 *
 * Brand fields, the two event contexts and ops documents each had their own
 * copy of this markup: same locale tabs with the same helper sentence, same
 * label field, same type field, same settings block, same footer. What actually
 * varied is small enough to be props and one `#extra` slot.
 *
 * The form builder is not a caller: its dialog is two columns with the preview
 * pinned beside the settings, so it composes the same parts differently.
 */
import FieldPreviewFrame from "@/components/custom-field-editor/FieldPreviewFrame.vue";
import FieldTypeCombobox from "@/components/custom-field-editor/FieldTypeCombobox.vue";
import FieldTypeSettings from "@/components/custom-field-editor/FieldTypeSettings.vue";
import OptionsEditor from "@/components/custom-field-editor/OptionsEditor.vue";
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { FIELD_LOCALE_TABS } from "@/lib/customFieldEditor";

defineProps({
  /** The reactive draft from `useCustomFieldForm`. */
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  editing: { type: Object, default: null },
  saving: { type: Boolean, default: false },

  locale: { type: String, default: "en" },
  localesWithErrors: { type: Array, default: () => [] },

  labelValue: { type: String, default: "" },
  labelErrors: { type: [Array, String, null], default: null },
  labelPlaceholder: { type: String, default: "Field label" },
  labelPlaceholderLocalized: { type: String, default: "Label" },

  placeholderValue: { type: String, default: "" },
  helpTextValue: { type: String, default: "" },

  /** Alias-resolved type for the settings panel (`year_select` -> `select`). */
  settingsType: { type: String, required: true },
  excludeTypes: { type: Array, default: () => [] },
  extraTypes: { type: Array, default: () => [] },
  typeLocked: { type: Boolean, default: false },
  typeLockedNote: { type: String, default: "" },

  showOptions: { type: Boolean, default: false },
  optionsMode: { type: String, default: "strings" },
  optionsReadonly: { type: Boolean, default: false },
  optionsReadonlyNote: { type: String, default: "" },

  allowFileConfig: { type: Boolean, default: true },
  allowPrefill: { type: Boolean, default: false },
  previewField: { type: Object, required: true },
  previewDisabled: { type: Boolean, default: false },

  idPrefix: { type: String, default: "field" },
});

defineEmits([
  "submit",
  "cancel",
  "update:locale",
  "update:labelValue",
  "update:placeholderValue",
  "update:helpTextValue",
  "update:type",
]);
</script>
