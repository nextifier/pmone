<template>
  <div class="flex flex-col pb-16 sm:container">
    <template v-if="initialLoading">
      <div class="flex items-center justify-center py-20">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading</span>
        </div>
      </div>
    </template>

    <template v-else-if="error">
      <div class="flex items-center justify-center py-20">
        <div class="flex flex-col items-center gap-y-4 text-center">
          <div class="space-y-1">
            <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
          </div>
          <NuxtLink
            to="/forms"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
          >
            <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
            <span>Back to Forms</span>
          </NuxtLink>
        </div>
      </div>
    </template>

    <template v-else-if="form">
      <!-- Header info -->
      <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 pt-4 sm:px-0">
        <BackButton destination="/forms" />
        <h1 class="text-lg font-semibold tracking-tight">{{ form.title }}</h1>
        <Badge :variant="statusVariant(form.status)">
          {{ form.status.charAt(0).toUpperCase() + form.status.slice(1) }}
        </Badge>
        <span v-if="form.responses_count !== undefined" class="text-muted-foreground text-sm tracking-tight">
          {{ form.responses_count }} {{ form.responses_count === 1 ? "response" : "responses" }}
        </span>
        <ButtonCopy
          v-if="form.status === 'published'"
          :text="publicFormUrl"
          label="Copy form link"
          class="ml-auto"
        />
      </div>

      <TabNav :tabs="formTabs" class="mt-4" />

      <div ref="contentArea" class="pt-6">
        <NuxtPage :form="form" @refresh="refreshForm" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";

definePageMeta({
  layout: "app",
  middleware: ["sanctum:auth", "permission"],
  permissions: ["forms.read"],
});

const route = useRoute();

const {
  data: formResponse,
  pending: initialLoading,
  error: formError,
  refresh: refreshForm,
} = await useLazySanctumFetch(() => `/api/forms/${route.params.slug}`, {
  key: `form-detail-${route.params.slug}`,
});

const form = computed(() => formResponse.value?.data || null);

const error = computed(() => {
  if (!formError.value) return null;
  const err = formError.value;
  if (err.statusCode === 404) return "Form not found";
  if (err.statusCode === 403) return "You do not have permission to view this form";
  return err.message || "Failed to load form";
});

usePageMeta(null, {
  title: computed(() => form.value?.title || "Form"),
});

const statusVariant = (status) => {
  switch (status) {
    case "draft":
      return "secondary";
    case "published":
      return "default";
    case "closed":
      return "destructive";
    default:
      return "outline";
  }
};

const contentArea = ref(null);

const formBase = computed(() => `/forms/${route.params.slug}`);

const formTabs = computed(() => [
  { label: "Settings", to: formBase.value, exact: true },
  { label: "Fields", to: `${formBase.value}/fields` },
  { label: "Responses", to: `${formBase.value}/responses` },
]);

const publicFormUrl = computed(() => {
  if (!form.value) return "";
  const frontendUrl = window?.location?.origin || "https://pmone.id";
  return `${frontendUrl}/f/${form.value.slug}`;
});

useTabSwipe(contentArea, formTabs);

provide("form", form);
provide("refreshForm", refreshForm);
</script>
