<template>
  <div class="min-h-dvh bg-muted/30 dark:bg-background">
    <!-- Theme toggle -->
    <div v-if="!isEmbed" class="mx-auto flex w-full max-w-2xl justify-end px-4 pt-4">
      <ColorModeToggle />
    </div>

    <div
      class="mx-auto w-full max-w-2xl px-4"
      :class="isEmbed ? 'py-4' : 'pt-6 pb-12 sm:pt-8 sm:pb-16'"
    >
      <!-- Skeleton loading -->
      <template v-if="isLoading">
        <div class="bg-card overflow-hidden rounded-2xl border shadow-sm">
          <div class="space-y-8 p-6 sm:p-8">
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
        </div>
      </template>

      <!-- Error state -->
      <div
        v-else-if="errorState"
        class="bg-card rounded-2xl border p-8 text-center shadow-sm sm:p-12"
      >
        <div
          class="bg-muted text-muted-foreground mx-auto flex size-14 items-center justify-center rounded-full"
        >
          <Icon name="lucide:alert-circle" class="size-7" />
        </div>
        <h2 class="mt-5 text-lg font-semibold tracking-tight sm:text-xl">{{ errorState.title }}</h2>
        <p class="text-muted-foreground mx-auto mt-2 max-w-sm text-sm tracking-tight sm:text-base">
          {{ errorState.message }}
        </p>
      </div>

      <!-- Success state -->
      <div
        v-else-if="submitted"
        class="bg-card rounded-2xl border p-8 text-center shadow-sm sm:p-12"
      >
        <div
          class="bg-success/10 text-success mx-auto flex size-14 items-center justify-center rounded-full"
        >
          <Icon name="lucide:check" class="size-7" />
        </div>
        <h2 class="mt-5 text-lg font-semibold tracking-tight sm:text-xl">{{ successTitle }}</h2>
        <p class="text-muted-foreground mx-auto mt-2 max-w-sm text-sm tracking-tight sm:text-base">
          {{ successMessage }}
        </p>
      </div>

      <!-- Already submitted state -->
      <div
        v-else-if="alreadySubmitted && form"
        class="bg-card rounded-2xl border p-8 text-center shadow-sm sm:p-12"
      >
        <div
          class="bg-primary/10 text-primary mx-auto flex size-14 items-center justify-center rounded-full"
        >
          <Icon name="lucide:check-circle-2" class="size-7" />
        </div>
        <h2 class="mt-5 text-lg font-semibold tracking-tight sm:text-xl">You're all set</h2>
        <p class="text-muted-foreground mx-auto mt-2 max-w-sm text-sm tracking-tight sm:text-base">
          We've received your response. This form only accepts one submission per person.
        </p>
      </div>

      <!-- Form -->
      <template v-else-if="form">
        <div class="bg-card overflow-hidden rounded-2xl border shadow-sm">
          <img
            v-if="form.cover_image?.xl && !isEmbed"
            :src="form.cover_image.xl"
            :alt="form.title"
            class="aspect-[3/1] w-full object-cover"
          />
          <div class="p-6 sm:p-8">
            <!-- Header -->
            <div class="space-y-2">
              <h1 class="text-2xl font-semibold tracking-tighter text-balance sm:text-3xl">
                {{ form.title }}
              </h1>
              <div
                v-if="form.description"
                class="prose prose-sm text-muted-foreground max-w-none text-sm tracking-tight sm:text-base"
                v-html="form.description"
              />
            </div>

            <div class="bg-border my-6 h-px sm:my-8" />

            <form @submit.prevent="handleSubmit" class="space-y-7">
              <!-- General error -->
              <div
                v-if="formErrors._general"
                class="bg-destructive/10 text-destructive rounded-lg px-4 py-3 text-sm tracking-tight"
              >
                {{ formErrors._general }}
              </div>

              <!-- Honeypot: invisible to humans, bots tend to fill it -->
              <div
                class="absolute top-auto -left-[9999px] h-px w-px overflow-hidden"
                aria-hidden="true"
              >
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

              <!-- Email field (if require_email) -->
              <div v-if="form.settings?.require_email" class="space-y-1.5">
                <Label for="respondent_email" class="text-sm sm:text-base">
                  Email
                  <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="respondent_email"
                  v-model="respondentEmail"
                  type="email"
                  placeholder="your@email.com"
                  :class="{ 'border-destructive': formErrors.respondent_email }"
                  @blur="checkDuplicate"
                />
                <p v-if="formErrors.respondent_email" class="text-destructive text-sm tracking-tight">
                  {{ formErrors.respondent_email }}
                </p>
              </div>

              <!-- Dynamic fields -->
              <PublicFieldRenderer
                v-for="field in sortedFields"
                :key="field.ulid"
                :data-field-error="formErrors[`responses.${field.ulid}`] ? field.ulid : undefined"
                :field="field"
                :model-value="responses[field.ulid]"
                :error="firstFieldError(field)"
                :form-slug="slug"
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
                <span>
                  {{
                    uploadsInProgress > 0
                      ? "Uploading files..."
                      : submitting
                        ? "Submitting..."
                        : "Submit"
                  }}
                </span>
              </Button>
            </form>
          </div>
        </div>

        <!-- Footer -->
        <p
          v-if="!isEmbed"
          class="text-muted-foreground mt-6 text-center text-sm tracking-tight"
        >
          Powered by
          <a
            href="https://pmone.id"
            target="_blank"
            rel="noopener noreferrer"
            class="text-foreground font-medium underline-offset-2 hover:underline"
          >
            PM One
          </a>
        </p>
      </template>
    </div>
  </div>
</template>

<script setup>
import PublicFieldRenderer from "@/components/form-builder/PublicFieldRenderer.vue";
import { Button } from "@/components/ui/button";
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { defaultValueFor, supportsPrefill } from "@/lib/formFieldTypes";

definePageMeta({
  layout: "empty",
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const apiUrl = useRuntimeConfig().public.apiUrl;

const isEmbed = computed(() => ["1", "true"].includes(String(route.query.embed)));

// Fetch form
const {
  data: formResponse,
  status,
  error: fetchError,
} = await useLazyFetch(() => `/api/public/forms/${slug.value}`, {
  baseURL: apiUrl,
  key: `public-form-${slug.value}`,
});

const form = computed(() => formResponse.value?.data || null);

const sortedFields = computed(() => {
  if (!form.value?.fields) return [];
  return [...form.value.fields].sort((a, b) => (a.order_column || 0) - (b.order_column || 0));
});

// Error state
const errorState = computed(() => {
  if (!fetchError.value) return null;

  const err = fetchError.value;
  const statusCode = err.statusCode || err.data?.statusCode;
  const message = err.data?.message || err.message;

  if (statusCode === 404) {
    return { title: "Form Not Found", message: message || "This form does not exist." };
  }
  if (statusCode === 403) {
    return { title: "Form Unavailable", message: message || "This form is not available." };
  }
  return { title: "Error", message: message || "Failed to load form." };
});

// Page meta
usePageMeta(null, {
  title: computed(() => form.value?.title || "Form"),
});

// Form state
const responses = ref({});
const respondentEmail = ref("");
const formErrors = ref({});
const submitting = ref(false);
const submitted = ref(false);
const successMessage = ref("");
const successTitle = ref("Thank You!");
const alreadySubmitted = ref(false);
const duplicateCheckDone = ref(false);
const uploadsInProgress = ref(0);

const handleUploading = (active) => {
  uploadsInProgress.value = Math.max(0, uploadsInProgress.value + (active ? 1 : -1));
};

// Honeypot anti-spam: hidden field stays empty, token proves a human-paced fill
const honeypotWebsite = ref("");
const honeypotToken = ref("");

const generateHoneypotToken = () => {
  const rand = () => Math.random().toString(16).slice(2, 10);
  return btoa(`${rand()}_${Math.floor(Date.now() / 1000)}_${rand()}`);
};

// Prefill a field from URL query params (?{ulid}=value or ?{param_key}=value)
const prefillValueFor = (field) => {
  if (!supportsPrefill(field.type)) return undefined;

  const raw = route.query[field.ulid] ?? (field.settings?.param_key && route.query[field.settings.param_key]);
  if (raw === undefined || raw === null || raw === false || raw === "") return undefined;

  const value = String(Array.isArray(raw) ? raw[0] : raw);
  const optionValues = (field.options || []).map((o) => String(o.value ?? o));

  switch (field.type) {
    case "multi_select":
    case "checkbox_group": {
      const values = value.split(",").map((v) => v.trim()).filter(Boolean);
      const valid = values.filter((v) => optionValues.includes(v));
      return valid.length ? valid : undefined;
    }
    case "tags":
      return value.split(",").map((v) => v.trim()).filter(Boolean);
    case "select":
    case "radio":
      return optionValues.includes(value) ? value : undefined;
    case "checkbox":
    case "switch":
      return ["1", "true", "yes", "on"].includes(value.toLowerCase());
    case "number":
    case "slider":
    case "rating":
    case "linear_scale": {
      const number = Number(value);
      return Number.isNaN(number) ? undefined : number;
    }
    case "date_range": {
      const [start, end] = value.split(",").map((v) => v.trim());
      return start && end ? { start, end } : undefined;
    }
    default:
      return value;
  }
};

// Initialize default values per field type once the form loads
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

// First error for a field, including nested keys (date_range start/end, array items)
const firstFieldError = (field) => {
  const prefix = `responses.${field.ulid}`;
  const exact = formErrors.value[prefix];
  if (exact) return exact;
  const nestedKey = Object.keys(formErrors.value).find((key) => key.startsWith(`${prefix}.`));
  return nestedKey ? formErrors.value[nestedKey] : null;
};

// Browser fingerprint
const visitorId = ref(null);
const fingerprintReady = ref(false);

// Loading: show skeleton until form loaded AND duplicate check completed
const isLoading = computed(() => {
  if (status.value === "pending") return true;
  if (fetchError.value) return false;
  if (!form.value) return true;
  if (form.value?.settings?.prevent_duplicate && !duplicateCheckDone.value) return true;
  return false;
});

onMounted(async () => {
  honeypotToken.value = generateHoneypotToken();

  try {
    const FingerprintJS = await import("@fingerprintjs/fingerprintjs");
    const fp = await FingerprintJS.load();
    const result = await fp.get();
    visitorId.value = result.visitorId;
  } catch {
    visitorId.value = null;
  }
  fingerprintReady.value = true;

  // Auto-check if form already loaded
  await checkDuplicateOnLoad();
});

// Also watch for form data becoming available (useLazyFetch may resolve after onMounted)
watch(form, async (newForm) => {
  if (newForm && fingerprintReady.value && !alreadySubmitted.value) {
    await checkDuplicateOnLoad();
  }
});

// Check duplicate on initial page load
const checkDuplicateOnLoad = async () => {
  if (!form.value?.settings?.prevent_duplicate) {
    duplicateCheckDone.value = true;
    return;
  }

  const by = form.value.settings.prevent_duplicate_by || "fingerprint";
  if ((by === "fingerprint" || by === "both") && !visitorId.value) {
    duplicateCheckDone.value = true;
    return;
  }

  try {
    const params = new URLSearchParams();
    if (visitorId.value) params.append("fingerprint", visitorId.value);

    const result = await $fetch(
      `${apiUrl}/api/public/forms/${slug.value}/check?${params.toString()}`
    );
    if (result.already_submitted) {
      alreadySubmitted.value = true;
    }
  } catch {
    // Ignore check errors
  } finally {
    duplicateCheckDone.value = true;
  }
};

// Check duplicate on email blur
const checkDuplicate = async () => {
  if (!form.value?.settings?.prevent_duplicate) return;

  const params = new URLSearchParams();
  if (respondentEmail.value) params.append("email", respondentEmail.value);
  if (visitorId.value) params.append("fingerprint", visitorId.value);

  try {
    const result = await $fetch(
      `${apiUrl}/api/public/forms/${slug.value}/check?${params.toString()}`
    );
    if (result.already_submitted) {
      alreadySubmitted.value = true;
    }
  } catch {
    // Ignore check errors
  }
};

// Submit handler
const handleSubmit = async () => {
  if (alreadySubmitted.value) {
    return;
  }

  submitting.value = true;
  formErrors.value = {};

  try {
    const body = {
      responses: responses.value,
      respondent_email: respondentEmail.value || null,
      browser_fingerprint: visitorId.value,
      website: honeypotWebsite.value,
      _token_time: honeypotToken.value,
    };

    const result = await $fetch(`${apiUrl}/api/public/forms/${slug.value}/submit`, {
      method: "POST",
      body,
    });

    // Handle redirect
    if (result.redirect_url) {
      window.location.href = result.redirect_url;
      return;
    }

    // Show success
    successMessage.value = result.message || "Your response has been recorded.";
    submitted.value = true;
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      const errors = err.data.errors;
      const unmapped = [];
      Object.entries(errors).forEach(([key, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : messages;
        if (key.startsWith("responses.") || key === "respondent_email") {
          formErrors.value[key] = msg;
        } else {
          unmapped.push(msg);
        }
      });
      if (unmapped.length) {
        formErrors.value._general = unmapped.join(". ");
      }
      scrollToFirstError();
    } else if (err.status === 409) {
      alreadySubmitted.value = true;
    } else {
      formErrors.value._general = err.data?.message || "Failed to submit form. Please try again.";
    }
  } finally {
    submitting.value = false;
  }
};

// Scroll the first invalid field into view after server-side validation
const scrollToFirstError = async () => {
  await nextTick();
  const target = document.querySelector("[data-field-error]") || document.querySelector("form");
  target?.scrollIntoView({ behavior: "smooth", block: "center" });
};

// Watch email for duplicate check
watch(respondentEmail, (val) => {
  if (val && val.includes("@")) {
    checkDuplicate();
  }
});
</script>
