<template>
  <div class="frame">
    <div class="frame-header">
      <h4 class="frame-title">{{ title }}</h4>
    </div>
    <div class="frame-panel">
      <CustomFieldRenderer
        :key="field.type"
        v-model="previewValue"
        :field="field"
        :locale="locale"
        :disabled="disabled"
        :label-size="labelSize"
        preview
        is-first
      />
    </div>
  </div>
</template>

<script setup>
import { CustomFieldRenderer, defaultValueFor, normalizeField } from "@/components/ui/custom-field";
import { ref, watch } from "vue";

const props = defineProps({
  // Preview shape assembled by the host (previewFieldFrom in lib/customFieldEditor).
  field: { type: Object, required: true },
  // Follows the host's active language tab so translated labels preview too.
  locale: { type: String, default: "en" },
  title: { type: String, default: "Preview" },
  disabled: { type: Boolean, default: false },
  // Which surface is being previewed. The form builder previews /f/{slug}, which
  // runs larger labels; project / ticket / document fields render inside
  // dashboard forms and keep the default.
  labelSize: { type: String, default: "default" },
});

const previewValue = ref(null);

// Seed a type-appropriate value whenever the type changes; the renderer itself
// is re-keyed on the same signal.
watch(
  () => props.field.type,
  () => {
    previewValue.value = defaultValueFor(normalizeField(props.field, props.locale));
  },
  { immediate: true }
);
</script>
