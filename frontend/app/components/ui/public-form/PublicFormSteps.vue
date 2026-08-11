<script setup>
import { computed, ref, watch } from "vue";
import { firstFieldError } from "./core";
import PublicFormStepAnswer from "./PublicFormStepAnswer.vue";
import { CustomFieldRenderer, isAnswered, normalizeField, validateFieldValue } from "../custom-field";
import { FieldError } from "../field";
import { Input } from "../input";
import { Label } from "../label";
import {
  Questionnaire,
  QuestionnaireActions,
  QuestionnaireItem,
  QuestionnaireNext,
  QuestionnairePrevious,
  QuestionnaireProgress,
  QuestionnaireSubmit,
} from "../questionnaire";
import { Spinner } from "../spinner";

/**
 * The multi-step form body: one question per screen, built on Questionnaire.
 *
 * Questionnaire renders the <form> itself, which is why this is a sibling of
 * PublicFormFields rather than something rendered inside it. State stays with
 * PublicFormView; this component owns only which step is showing and which step
 * failed its client-side check.
 *
 * Navigation is fully controlled (`v-model:item`) and every button is
 * intercepted, because the built-in navigation validates native form controls
 * and this form has none - see PublicFormStepAnswer for the bridge.
 */
const props = defineProps({
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

const emit = defineEmits([
  "update:response",
  "update:respondentEmail",
  "update:honeypot",
  "uploading",
  "submit",
]);

const { t } = useI18n();

/** Reserved step name; no field ulid can collide with it. */
const EMAIL_STEP = "__email";

/** Synthetic field so the email step goes through the same validator as the rest. */
const EMAIL_FIELD = { type: "email", validation: { required: true } };

const stepErrors = ref({});

const steps = computed(() => {
  const list = [];

  if (props.form.settings?.require_email) {
    list.push({ name: EMAIL_STEP, kind: "email", required: true });
  }

  for (const field of props.fields) {
    const normalized = normalizeField(field, props.locale);

    list.push({
      name: field.ulid,
      kind: normalized.type === "section" ? "section" : "field",
      field,
      required: normalized.type === "section" ? false : Boolean(normalized.validation.required),
    });
  }

  return list;
});

/**
 * Declaring the order up front is what lets the first step, the progress
 * readout, and the button row render on the server. Without it `total` is 0
 * until hydration and the whole row pops in.
 */
const items = computed(() => steps.value.map(({ name, required }) => ({ name, required })));

const activeName = ref(steps.value[0]?.name ?? null);

watch(steps, (list) => {
  if (!list.some((step) => step.name === activeName.value)) {
    activeName.value = list[0]?.name ?? null;
  }
});

const activeIndex = computed(() => steps.value.findIndex((step) => step.name === activeName.value));

const valueFor = (step) =>
  step.kind === "email" ? props.respondentEmail : props.responses[step.name];

/**
 * What Questionnaire is told about a step. Optional steps always read as
 * answered so its own submit scan never blocks on a question the visitor was
 * free to leave alone; the real gate is messageFor below.
 */
const filledFor = (step) => {
  if (step.kind === "section") return true;
  if (!step.required) return true;
  if (step.kind === "email") return props.respondentEmail.trim() !== "";

  return isAnswered(step.field, props.responses[step.name]);
};

const messageFor = (step) => {
  if (step.kind === "section") return null;
  if (step.kind === "email") {
    return validateFieldValue(EMAIL_FIELD, props.respondentEmail, props.locale);
  }

  return validateFieldValue(step.field, props.responses[step.name], props.locale);
};

/** A client-side message wins over the server's: it is about the value on screen now. */
const errorFor = (step) =>
  stepErrors.value[step.name] ??
  (step.kind === "email"
    ? props.formErrors.respondent_email
    : firstFieldError(props.formErrors, step.name)) ??
  null;

const setStepError = (name, message) => {
  stepErrors.value = { ...stepErrors.value, [name]: message };
};

const clearStepError = (name) => {
  if (!(name in stepErrors.value)) return;

  const { [name]: _dropped, ...rest } = stepErrors.value;
  stepErrors.value = rest;
};

/** Drives the entry animation, so going back reads as going back. */
const direction = ref("forward");

const goTo = (index) => {
  const step = steps.value[index];
  if (!step) return;

  direction.value = index < activeIndex.value ? "backward" : "forward";
  activeName.value = step.name;
};

const goToName = (name) => {
  goTo(steps.value.findIndex((step) => step.name === name));
};

const handlePrevious = () => {
  goTo(activeIndex.value - 1);
};

const handleNext = () => {
  // QuestionnaireNext emits `click` before it consults its own `disabled`, so
  // the guard has to be repeated here.
  if (props.uploadsInProgress > 0) return;

  const step = steps.value[activeIndex.value];
  if (!step) return;

  const message = messageFor(step);
  if (message) {
    setStepError(step.name, message);
    return;
  }

  clearStepError(step.name);
  goTo(activeIndex.value + 1);
};

/**
 * The only route to a submit. Every step is re-checked rather than trusting the
 * ones already walked past, because Back lets a visitor empty a field they had
 * filled in.
 */
const handleSubmit = () => {
  if (props.submitting || props.uploadsInProgress > 0) return;

  for (const step of steps.value) {
    const message = messageFor(step);

    if (message) {
      setStepError(step.name, message);
      goToName(step.name);
      return;
    }
  }

  stepErrors.value = {};
  emit("submit");
};

// Editing a field should retract the complaint about it right away rather than
// leaving it up until the next attempt to move on.
watch(
  () => [props.responses, props.respondentEmail],
  () => {
    for (const name of Object.keys(stepErrors.value)) {
      const step = steps.value.find((candidate) => candidate.name === name);
      if (step && !messageFor(step)) clearStepError(name);
    }
  },
  { deep: true },
);

// Safety net for a 422 the client-side rules did not anticipate: land the
// visitor on the step the server objected to instead of on whatever is showing.
watch(
  () => props.formErrors,
  (errors) => {
    const offending = steps.value.find((step) =>
      step.kind === "email" ? errors.respondent_email : errors[`responses.${step.name}`],
    );

    if (offending) goToName(offending.name);
  },
  { deep: true },
);
</script>

<template>
  <Questionnaire
    v-model:item="activeName"
    :items="items"
    :data-direction="direction"
    class="gap-7"
    @submit="handleSubmit"
  >
    <div
      v-if="formErrors._general"
      class="bg-destructive/10 text-destructive-foreground rounded-lg px-4 py-3 text-sm tracking-tight"
    >
      {{ formErrors._general }}
    </div>

    <!-- Honeypot: invisible to humans, bots tend to fill it. Kept outside every
         item so it is never made inert along with an inactive step. -->
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

    <QuestionnaireProgress v-slot="{ current, total }" class="w-full">
      <div class="space-y-2">
        <div class="bg-muted h-1 w-full overflow-hidden rounded-full">
          <div
            class="bg-primary t-step-bar h-full rounded-full"
            :style="{ width: total ? `${(current / total) * 100}%` : '0%' }"
          />
        </div>
        <span>{{ t("forms.stepProgress", { current, total }) }}</span>
      </div>
    </QuestionnaireProgress>

    <QuestionnaireItem
      v-for="step in steps"
      :key="step.name"
      :name="step.name"
      :required="step.required"
      :invalid="Boolean(errorFor(step))"
      class="t-step"
    >
      <PublicFormStepAnswer :filled="filledFor(step)">
        <template v-if="step.kind === 'email'">
          <Field :data-invalid="Boolean(errorFor(step))">
            <FieldLabel for="respondent_email" required class="text-base leading-snug">
              {{ t("forms.emailLabel") }}
            </FieldLabel>
            <Input
              id="respondent_email"
              :model-value="respondentEmail"
              type="email"
              autocomplete="email"
              placeholder="your@email.com"
              :aria-invalid="Boolean(errorFor(step))"
              @update:model-value="$emit('update:respondentEmail', $event)"
            />
            <FieldError :errors="errorFor(step) ? [errorFor(step)] : []" />
          </Field>
        </template>

        <CustomFieldRenderer
          v-else
          :field="step.field"
          is-first
          :locale="locale"
          label-size="lg"
          :model-value="responses[step.name]"
          :error="errorFor(step)"
          :upload-handler="uploadHandlers.uploadHandler"
          :revert-handler="uploadHandlers.revertHandler"
          @update:model-value="$emit('update:response', step.name, $event)"
          @uploading="$emit('uploading', $event)"
        />
      </PublicFormStepAnswer>
    </QuestionnaireItem>

    <QuestionnaireActions>
      <QuestionnairePrevious @click.prevent="handlePrevious">
        {{ t("forms.back") }}
      </QuestionnairePrevious>

      <QuestionnaireNext
        :disabled="uploadsInProgress > 0"
        @click.prevent="handleNext"
      >
        {{ uploadsInProgress > 0 ? t("forms.uploadingFiles") : t("forms.next") }}
      </QuestionnaireNext>

      <!-- Intercepted rather than left as a native submit: Questionnaire's own
           scan only knows whether a step was answered, so it would bounce the
           visitor to the offending step with no explanation. handleSubmit
           carries the message with it. -->
      <QuestionnaireSubmit
        :disabled="submitting || uploadsInProgress > 0"
        @click.prevent="handleSubmit"
      >
        <Spinner v-if="submitting" class="size-4" />
        <span>{{ submitLabel }}</span>
      </QuestionnaireSubmit>
    </QuestionnaireActions>
  </Questionnaire>
</template>
