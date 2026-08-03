<template>
  <PublicFormView
    :form="form"
    :fetch-error="fetchError"
    :endpoints="endpoints"
    :upload-handlers="uploadHandlers"
    :locale="locale"
    :embed="isEmbed"
  >
    <template #chrome="{ locales }">
      <LanguageSwitcher :codes="locales" />
      <AppearancePickerButton />
      <ColorModeToggle />
    </template>
  </PublicFormView>
</template>

<script setup>
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
import { LanguageSwitcher } from "@/components/ui/language-switcher";
import { isClosedError, PublicFormView } from "@/components/ui/public-form";
import { createPublicFormUploadHandlers } from "@/lib/uploadHandlers";

definePageMeta({
  layout: "empty",
});

const route = useRoute();
const { t, locale } = useI18n();
const apiUrl = useRuntimeConfig().public.apiUrl;
const slug = computed(() => route.params.slug);

/**
 * `?embed=1` is what the iframe snippet in FormShareDialog appends. Embedded
 * mode drops the chrome and never escalates to a full-page error, which would
 * hijack the host site's iframe.
 */
const isEmbed = computed(() => ["1", "true"].includes(String(route.query.embed)));

const base = computed(() => `${apiUrl}/api/public/forms/${slug.value}`);

const endpoints = computed(() => ({
  check: `${base.value}/check`,
  submit: `${base.value}/submit`,
}));

const uploadHandlers = computed(() => createPublicFormUploadHandlers(`${base.value}/upload`));

const { data: formResponse, error: fetchError } = await useFetch(
  () => `/api/public/forms/${slug.value}`,
  {
    baseURL: apiUrl,
    key: `public-form-${slug.value}`,
  }
);

const form = computed(() => formResponse.value?.data || null);

// A missing form is a genuine not-found page, so surface it through the shared
// error.vue boundary. A 403 (closed / not yet open / limit reached) stays inline
// as a neutral card, and embedded mode keeps every failure inline.
if (fetchError.value && !isEmbed.value && !isClosedError(fetchError.value)) {
  throw createError({
    statusCode: fetchError.value.statusCode || fetchError.value.data?.statusCode || 404,
    statusMessage: t("forms.notFoundMessage"),
    fatal: true,
  });
}

usePageMeta(null, {
  title: computed(() => form.value?.title || "Form"),
});
</script>
