<template>
  <div class="relative min-h-dvh" :class="embed ? 'py-4' : 'pt-6 pb-12 sm:pt-8 sm:pb-16'">
    <!-- Page chrome belongs to the host app, so it is a slot and is dropped
         entirely when embedded in someone else's iframe. -->
    <div
      v-if="!embed && $slots.chrome"
      class="border-border bg-background/95 supports-backdrop-filter:bg-background/90 absolute top-4 right-3 z-10 flex items-center gap-x-1 rounded-full border p-1 backdrop-blur-sm sm:fixed"
    >
      <slot name="chrome" :locales="formLocales" />
    </div>

    <div class="container sm:max-w-xl">
      <!-- Only forms that dedupe by fingerprint land here: the answer needs the
           browser, so the form is held back rather than shown and then retracted.
           Every other form renders straight from SSR with no placeholder. -->
      <div v-if="isCheckingDuplicate" class="space-y-8">
        <div class="space-y-2.5">
          <Skeleton class="h-8 w-3/5" />
          <Skeleton class="h-4 w-4/5" />
        </div>
        <div class="space-y-6">
          <div v-for="i in 3" :key="i" class="space-y-2">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-11 w-full" />
          </div>
          <Skeleton class="h-11 w-full rounded-lg" />
        </div>
      </div>

      <!-- Terminal states (closed / load error / success / already responded)
           share one card. Result draws its own checkmark when no icon is given. -->
      <Result v-else-if="statusCard" size="sm" title-as="h1" v-bind="statusCard" />

      <div v-else-if="form" class="relative">
        <BlurImage
          v-if="form.cover_image?.xl && !embed"
          :src="form.cover_image.xl"
          :lqip="form.cover_image.lqip || ''"
          :srcset="coverSrcset"
          sizes="(min-width: 640px) 544px, calc(100vw - 2rem)"
          :alt="form.title"
          :width="1500"
          :height="500"
          loading="eager"
          fetchpriority="high"
          image-class="aspect-[3/1] w-full object-cover"
        />

        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tighter text-balance sm:text-3xl">
            {{ form.title }}
          </h1>
          <div
            v-if="form.description"
            class="prose prose-sm dark:prose-invert text-muted-foreground max-w-none text-sm tracking-tight sm:text-base"
            v-html="form.description"
          />
        </div>

        <div class="bg-border my-6 h-px sm:my-8" />

        <form @submit.prevent="handleSubmit" class="space-y-7">
          <div
            v-if="formErrors._general"
            class="bg-destructive/10 text-destructive rounded-lg px-4 py-3 text-sm tracking-tight"
          >
            {{ formErrors._general }}
          </div>

          <!-- Honeypot: invisible to humans, bots tend to fill it -->
          <div class="absolute top-auto -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label for="hp_website">Website</label>
            <input
              id="hp_website"
              v-model="honeypotWebsite"
              type="text"
              name="website"
              tabindex="-1"
              autocomplete="off"
            />
          </div>

          <div v-if="form.settings?.require_email" class="space-y-2">
            <Label for="respondent_email" class="text-sm sm:text-base">
              {{ t("forms.emailLabel") }}
              <span class="text-destructive">*</span>
            </Label>
            <Input
              id="respondent_email"
              v-model="respondentEmail"
              type="email"
              autocomplete="email"
              placeholder="your@email.com"
              :class="{ 'border-destructive': formErrors.respondent_email }"
            />
            <p v-if="formErrors.respondent_email" class="text-destructive text-sm tracking-tight">
              {{ formErrors.respondent_email }}
            </p>
          </div>

          <CustomFieldRenderer
            v-for="(field, index) in sortedFields"
            :key="field.ulid"
            :data-field-error="formErrors[`responses.${field.ulid}`] ? field.ulid : undefined"
            :field="field"
            :is-first="index === 0"
            :locale="locale"
            :model-value="responses[field.ulid]"
            :error="firstFieldError(formErrors, field.ulid)"
            :upload-handler="uploadHandlers.uploadHandler"
            :revert-handler="uploadHandlers.revertHandler"
            @update:model-value="responses[field.ulid] = $event"
            @uploading="handleUploading"
          />

          <Button
            type="submit"
            :disabled="submitting || uploadsInProgress > 0"
            class="w-full"
            size="lg"
          >
            <Spinner v-if="submitting" class="size-4" />
            <span>{{ submitLabel }}</span>
          </Button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useDebounceFn } from "@vueuse/core";
import {
  availableFormLocales,
  buildCoverSrcset,
  duplicateModeFor,
  firstFieldError,
  generateHoneypotToken,
  isClosedError,
  loadVisitorId,
  mapValidationErrors,
  sortFormFields,
} from "./core";
import { BlurImage } from "../blur-image";
import { Button } from "../button";
import {
  prefillValueFor as coercePrefill,
  CustomFieldRenderer,
  defaultValueFor,
  supportsPrefill,
} from "../custom-field";
import { Input } from "../input";
import { Label } from "../label";
import { Result } from "../result";
import { Skeleton } from "../skeleton";
import { Spinner } from "../spinner";

/**
 * The whole public form surface, shared verbatim between pmone.id/f/{slug} and
 * every event website's /f/{slug}. Everything host-specific is injected: the
 * page owns fetching (its meta title needs the payload), the endpoints, the
 * upload transport, and the chrome slot. Nothing in here may hardcode a URL.
 */
const props = defineProps({
  /** Payload from the public form endpoint, or null while unavailable. */
  form: { type: Object, default: null },
  /** useFetch error, kept raw so the 403/404 split stays in one place. */
  fetchError: { type: Object, default: null },
  /** Absolute or same-origin URLs: { check, submit }. */
  endpoints: { type: Object, required: true },
  /** { uploadHandler, revertHandler } from lib/uploadHandlers. */
  uploadHandlers: { type: Object, required: true },
  /** Picks which translation of each field label is rendered. */
  locale: { type: String, default: "en" },
  embed: { type: Boolean, default: false },
});

const { t } = useI18n();
const route = useRoute();

const responses = ref({});
const respondentEmail = ref("");
const formErrors = ref({});
const submitting = ref(false);
const submitted = ref(false);
const successMessage = ref("");
const alreadySubmitted = ref(false);
const duplicateCheckDone = ref(false);
const uploadsInProgress = ref(0);
const visitorId = ref(null);
const honeypotWebsite = ref("");
const honeypotToken = ref("");

const sortedFields = computed(() => sortFormFields(props.form?.fields));
const coverSrcset = computed(() => buildCoverSrcset(props.form?.cover_image));

// Handed to the chrome slot so the host can scope its language switcher to the
// languages this particular form actually has.
const formLocales = computed(() => availableFormLocales(props.form));

const submitLabel = computed(() => {
  if (uploadsInProgress.value > 0) return t("forms.uploadingFiles");
  return submitting.value ? t("forms.submitting") : t("forms.submit");
});

const handleUploading = (active) => {
  uploadsInProgress.value = Math.max(0, uploadsInProgress.value + (active ? 1 : -1));
};

/**
 * One shared Result for every terminal state. A closed or missing form is
 * painted neutral on purpose: it is not the visitor's fault, so it should not
 * read as a failure they caused.
 */
const statusCard = computed(() => {
  if (props.fetchError) {
    const closed = isClosedError(props.fetchError);

    return {
      status: "error",
      variant: "muted",
      icon: "lucide:alert-circle",
      title: closed ? t("forms.closedTitle") : t("forms.notFoundTitle"),
      // The 403 body carries the owner's own "form closed" wording. A 404 body
      // does not: Laravel answers a missing form with the raw model name.
      description: closed
        ? props.fetchError.data?.message || t("forms.closedMessage")
        : t("forms.notFoundMessage"),
    };
  }

  if (submitted.value) {
    return {
      status: "success",
      variant: "soft",
      title: t("forms.successTitle"),
      description: successMessage.value || t("forms.successMessage"),
    };
  }

  if (alreadySubmitted.value && props.form) {
    return {
      status: "info",
      variant: "soft",
      icon: "lucide:check-circle-2",
      title: t("forms.alreadyTitle"),
      description: t("forms.alreadyMessage"),
    };
  }

  return null;
});

const duplicateMode = computed(() => duplicateModeFor(props.form?.settings));

const isCheckingDuplicate = computed(
  () => duplicateMode.value.checksFingerprint && !duplicateCheckDone.value
);

const runDuplicateCheck = async (email = null) => {
  const params = new URLSearchParams();
  if (email) params.append("email", email);
  if (visitorId.value) params.append("fingerprint", visitorId.value);
  if (![...params].length) return;

  try {
    const result = await $fetch(`${props.endpoints.check}?${params}`);
    if (result.already_submitted) {
      alreadySubmitted.value = true;
    }
  } catch {
    // A failed pre-check is not fatal - submit still rejects duplicates with 409.
  }
};

// Debounced rather than per-keystroke, which used to fire one request per
// character typed after the "@". Watching the value (not blur) covers autofill.
const checkEmailForDuplicate = useDebounceFn(() => {
  if (duplicateMode.value.checksEmail && respondentEmail.value.includes("@")) {
    runDuplicateCheck(respondentEmail.value);
  }
}, 500);

watch(respondentEmail, checkEmailForDuplicate);

onMounted(async () => {
  honeypotToken.value = generateHoneypotToken();
  visitorId.value = await loadVisitorId();

  if (duplicateMode.value.checksFingerprint) {
    await runDuplicateCheck();
  }
  duplicateCheckDone.value = true;
});

// Prefill a field from URL query params (?{ulid}=value or ?{param_key}=value).
// The view owns query extraction; per-type coercion lives in custom-field/core.
const prefillValueFor = (field) => {
  if (!supportsPrefill(field.type)) return undefined;

  const paramKey = field.settings?.param_key;
  const raw = route.query[field.ulid] ?? (paramKey ? route.query[paramKey] : undefined);

  return coercePrefill(field, raw);
};

watch(
  sortedFields,
  (fields) => {
    for (const field of fields) {
      if (field.type !== "section" && responses.value[field.ulid] === undefined) {
        responses.value[field.ulid] = prefillValueFor(field) ?? defaultValueFor(field);
      }
    }
  },
  { immediate: true }
);

// Scroll the first invalid field into view after server-side validation
const scrollToFirstError = async () => {
  await nextTick();
  const target = document.querySelector("[data-field-error]") || document.querySelector("form");
  target?.scrollIntoView({ behavior: "smooth", block: "center" });
};

const handleSubmit = async () => {
  if (alreadySubmitted.value) return;

  submitting.value = true;
  formErrors.value = {};

  try {
    const result = await $fetch(props.endpoints.submit, {
      method: "POST",
      body: {
        responses: responses.value,
        respondent_email: respondentEmail.value || null,
        browser_fingerprint: visitorId.value,
        website: honeypotWebsite.value,
        _token_time: honeypotToken.value,
      },
    });

    if (result.redirect_url) {
      window.location.href = result.redirect_url;
      return;
    }

    successMessage.value = result.message || t("forms.successMessage");
    submitted.value = true;
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      formErrors.value = mapValidationErrors(err.data.errors);
      scrollToFirstError();
    } else if (err.status === 409) {
      alreadySubmitted.value = true;
    } else {
      formErrors.value._general = err.data?.message || t("forms.submitFailed");
    }
  } finally {
    submitting.value = false;
  }
};
</script>
