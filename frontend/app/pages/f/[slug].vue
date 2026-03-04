<template>
  <div class="min-h-screen">
    <div class="mx-auto max-w-xl px-4 py-8 sm:py-16">
      <!-- Skeleton loading -->
      <template v-if="isLoading">
        <div class="mb-8 text-center">
          <Skeleton class="mx-auto mb-2 h-7 w-3/5" />
          <Skeleton class="mx-auto h-4 w-4/5" />
        </div>
        <div class="space-y-6">
          <div v-for="i in 3" :key="i" class="space-y-2">
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-10 w-full" />
          </div>
          <Skeleton class="h-10 w-full rounded-md" />
        </div>
      </template>

      <!-- Error states -->
      <div v-else-if="errorState" class="py-20 text-center">
        <Icon name="lucide:alert-circle" class="text-muted-foreground mx-auto size-12" />
        <h2 class="mt-4 text-lg font-semibold tracking-tight">{{ errorState.title }}</h2>
        <p class="text-muted-foreground mt-2 text-sm">{{ errorState.message }}</p>
      </div>

      <!-- Success state -->
      <div v-else-if="submitted" class="py-20 text-center">
        <Icon name="lucide:check-circle-2" class="mx-auto size-12 text-green-500" />
        <h2 class="mt-4 text-lg font-semibold tracking-tight">{{ successTitle }}</h2>
        <p class="text-muted-foreground mt-2 text-sm">{{ successMessage }}</p>
      </div>

      <!-- Already submitted state -->
      <template v-else-if="alreadySubmitted && form">
        <div class="mb-8 text-center">
          <h1 class="text-2xl font-bold tracking-tight">{{ form.title }}</h1>
        </div>
        <div class="">
          <div class="flex flex-col items-center text-center">
            <div class="bg-primary/10 flex size-14 items-center justify-center rounded-full">
              <Icon name="lucide:check-circle-2" class="text-primary size-7" />
            </div>
            <h2 class="mt-4 text-lg font-semibold tracking-tight">You're All Set!</h2>
            <p class="text-muted-foreground mt-1.5 max-w-xs text-sm">
              We've received your response. This form only accepts one submission per person.
            </p>
          </div>
        </div>
      </template>

      <!-- Form -->
      <template v-else-if="form">
        <div class="mb-8">
          <img
            v-if="form.cover_image?.xl"
            :src="form.cover_image.xl"
            :alt="form.title"
            class="mb-6 aspect-[3/1] w-full rounded-lg object-cover"
          />
          <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight">{{ form.title }}</h1>
            <div
              v-if="form.description"
              class="prose prose-sm text-muted-foreground mx-auto mt-2 text-sm"
              v-html="form.description"
            />
          </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- General error -->
          <div
            v-if="formErrors._general"
            class="bg-destructive/10 text-destructive rounded-md px-4 py-3 text-sm"
          >
            {{ formErrors._general }}
          </div>

          <!-- Email field (if require_email) -->
          <div v-if="form.settings?.require_email" class="space-y-1.5">
            <Label for="respondent_email"> Email <span class="text-destructive">*</span> </Label>
            <Input
              id="respondent_email"
              v-model="respondentEmail"
              type="email"
              placeholder="your@email.com"
              :class="{ 'border-destructive': formErrors.respondent_email }"
              @blur="checkDuplicate"
            />
            <p v-if="formErrors.respondent_email" class="text-destructive text-xs">
              {{ formErrors.respondent_email }}
            </p>
          </div>

          <!-- Dynamic fields -->
          <PublicFieldRenderer
            v-for="field in sortedFields"
            :key="field.ulid"
            :field="field"
            :model-value="responses[field.ulid]"
            :error="formErrors[`responses.${field.ulid}`]"
            :form-slug="slug"
            @update:model-value="responses[field.ulid] = $event"
          />

          <button
            type="submit"
            :disabled="submitting"
            class="bg-primary text-primary-foreground hover:bg-primary/90 flex w-full items-center justify-center gap-x-2 rounded-md px-4 py-2.5 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Spinner v-if="submitting" class="size-4" />
            <span>{{ submitting ? "Submitting..." : "Submit" }}</span>
          </button>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import PublicFieldRenderer from "@/components/form-builder/PublicFieldRenderer.vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";

definePageMeta({
  layout: "empty",
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const apiUrl = useRuntimeConfig().public.apiUrl;

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
    } else if (err.status === 409) {
      alreadySubmitted.value = true;
    } else {
      formErrors.value._general = err.data?.message || "Failed to submit form. Please try again.";
    }
  } finally {
    submitting.value = false;
  }
};

// Watch email for duplicate check
watch(respondentEmail, (val) => {
  if (val && val.includes("@")) {
    checkDuplicate();
  }
});
</script>
