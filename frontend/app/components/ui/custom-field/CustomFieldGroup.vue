<template>
  <div class="space-y-6">
    <div
      v-for="(field, index) in fields"
      :key="fieldKey(field)"
      :data-field-error="errorFor(field) ? '' : null"
    >
      <CustomFieldRenderer
        :field="field"
        :is-first="index === 0"
        :model-value="modelValue[fieldKey(field)]"
        :error="errorFor(field)"
        :locale="locale"
        :disabled="disabled"
        :preview="preview"
        :existing-files="existingFiles[fieldKey(field)] || []"
        :upload-handler="uploadHandler"
        :revert-handler="revertHandler"
        @update:model-value="update(field, $event)"
        @uploading="$emit('uploading', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { onMounted } from "vue";
import CustomFieldRenderer from "./CustomFieldRenderer.vue";
import { defaultValueFor, normalizeField } from "./core";

const props = defineProps({
  fields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  // e.g. "responses." or "project_custom_fields." — prefixes the error keys.
  errorPrefix: { type: String, default: "" },
  locale: { type: String, default: "en" },
  disabled: { type: Boolean, default: false },
  preview: { type: Boolean, default: false },
  uploadHandler: { type: Function, default: null },
  revertHandler: { type: Function, default: null },
  // Already-submitted files, keyed by the same value-map key as modelValue:
  // { [fieldKey]: [{ id, name, url, size }] }.
  existingFiles: { type: Object, default: () => ({}) },
  // The value-map key: "ulid" (default) or "key" (brand profile).
  valueKey: { type: String, default: "ulid" },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const fieldKey = (field) => String(field[props.valueKey] ?? field.ulid ?? field.id ?? "");

const update = (field, value) => {
  emit("update:modelValue", { ...props.modelValue, [fieldKey(field)]: value });
};

// First error for a field, including nested keys (date_range .start/.end,
// array item .*) — the firstFieldError logic every consumer used to inline.
const errorFor = (field) => {
  const prefix = `${props.errorPrefix}${fieldKey(field)}`;
  const exact = props.errors[prefix];
  if (exact) return Array.isArray(exact) ? exact[0] : exact;
  const nestedKey = Object.keys(props.errors).find((key) => key.startsWith(`${prefix}.`));
  if (!nestedKey) return null;
  const nested = props.errors[nestedKey];
  return Array.isArray(nested) ? nested[0] : nested;
};

// Seed missing values with per-type defaults so controls render correctly.
onMounted(() => {
  const patch = {};
  for (const field of props.fields) {
    const key = fieldKey(field);
    if (props.modelValue[key] === undefined) {
      patch[key] = defaultValueFor(normalizeField(field, props.locale));
    }
  }
  if (Object.keys(patch).length) {
    emit("update:modelValue", { ...props.modelValue, ...patch });
  }
});
</script>
