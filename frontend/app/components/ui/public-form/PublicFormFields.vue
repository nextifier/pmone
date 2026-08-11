<script setup>
import { firstFieldError } from "./core";
import { Button } from "../button";
import { CustomFieldRenderer } from "../custom-field";
import { FieldError } from "../field";
import { Input } from "../input";
import { Label } from "../label";
import { Spinner } from "../spinner";

/**
 * The single-page form body: every question at once, one submit button at the
 * bottom. This is how forms have always rendered and stays the default.
 *
 * It owns its own <form> element rather than sitting inside one, because the
 * multi-step alternative IS a form (Questionnaire renders one) and forms cannot
 * nest. PublicFormView swaps the two bodies at this level for that reason.
 * State stays with PublicFormView; this component only renders and reports.
 */
defineProps({
  form: { type: Object, required: true },
  /** Already sorted and translation-resolved by the parent. */
  fields: { type: Array, required: true },
  responses: { type: Object, required: true },
  formErrors: { type: Object, required: true },
  respondentEmail: { type: String, default: "" },
  honeypot: { type: String, default: "" },
  locale: { type: String, default: "en" },
  uploadHandlers: { type: Object, required: true },
  submitting: { type: Boolean, default: false },
  uploadsInProgress: { type: Number, default: 0 },
  submitLabel: { type: String, required: true },
});

defineEmits([
  "update:response",
  "update:respondentEmail",
  "update:honeypot",
  "uploading",
  "submit",
]);

const { t } = useI18n();
</script>

<template>
  <form class="space-y-7" @submit.prevent="$emit('submit')">
    <div
      v-if="formErrors._general"
      class="bg-destructive/10 text-destructive-foreground rounded-lg px-4 py-3 text-sm tracking-tight"
    >
      {{ formErrors._general }}
    </div>

    <!-- Honeypot: invisible to humans, bots tend to fill it -->
    <div class="absolute top-auto -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
      <label for="hp_website">Website</label>
      <input
        id="hp_website"
        :value="honeypot"
        type="text"
        name="website"
        tabindex="-1"
        autocomplete="off"
        @input="$emit('update:honeypot', $event.target.value)"
      />
    </div>

    <Field
      v-if="form.settings?.require_email"
      :data-invalid="!!formErrors?.respondent_email"
    >
      <FieldLabel for="respondent_email" required class="text-base leading-snug">
        {{ t("forms.emailLabel") }}
      </FieldLabel>
      <Input
        id="respondent_email"
        :model-value="respondentEmail"
        type="email"
        autocomplete="email"
        placeholder="your@email.com"
        :aria-invalid="!!formErrors?.respondent_email"
        @update:model-value="$emit('update:respondentEmail', $event)"
      />
      <FieldError :errors="formErrors.respondent_email ? [formErrors.respondent_email] : []" />
    </Field>

    <CustomFieldRenderer
      v-for="(field, index) in fields"
      :key="field.ulid"
      :data-field-error="formErrors[`responses.${field.ulid}`] ? field.ulid : undefined"
      :field="field"
      :is-first="index === 0"
      :locale="locale"
      label-size="lg"
      :model-value="responses[field.ulid]"
      :error="firstFieldError(formErrors, field.ulid)"
      :upload-handler="uploadHandlers.uploadHandler"
      :revert-handler="uploadHandlers.revertHandler"
      @update:model-value="$emit('update:response', field.ulid, $event)"
      @uploading="$emit('uploading', $event)"
    />

    <Button type="submit" :disabled="submitting || uploadsInProgress > 0" class="w-full" size="lg">
      <Spinner v-if="submitting" class="size-4" />
      <span>{{ submitLabel }}</span>
    </Button>
  </form>
</template>
