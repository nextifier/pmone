<template>
  <div class="flex flex-col pb-16 sm:container">
    <template v-if="initialLoading">
      <div class="flex flex-col items-start gap-y-4 px-4 pt-4 sm:px-0">
        <Skeleton class="h-8 w-24 rounded-lg" />
        <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2">
          <Skeleton class="h-7 w-56 max-w-full" />
          <Skeleton class="h-[22px] w-24 rounded-full" />
          <Skeleton class="h-5 w-20" />
        </div>
        <div class="mt-4 flex w-full gap-x-6 border-b pb-2">
          <Skeleton v-for="i in 4" :key="i" class="h-5 w-16" />
        </div>
        <div class="w-full space-y-4 pt-4">
          <Skeleton class="h-40 w-full max-w-2xl rounded-xl" />
          <Skeleton class="h-9 w-full max-w-2xl" />
          <Skeleton class="h-24 w-full max-w-2xl rounded-lg" />
        </div>
      </div>
    </template>

    <template v-else-if="error">
      <div class="flex items-center justify-center py-20">
        <div class="flex flex-col items-center gap-y-4 text-center">
          <div
            class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
          >
            <div class="translate-y-1.5 -rotate-6">
              <Icon name="hugeicons:resize-field-rectangle" />
            </div>
            <div><Icon name="hugeicons:search-remove" /></div>
            <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:file-not-found" /></div>
          </div>
          <div class="space-y-1">
            <h3 class="text-lg font-semibold tracking-tighter text-balance">{{ error }}</h3>
            <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
              The form may have been moved to trash or you may not have access to it.
            </p>
          </div>
          <Button to="/forms">
            <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
            <span>Back to forms</span>
          </Button>
        </div>
      </div>
    </template>

    <template v-else-if="form">
      <!-- Header info -->
      <div class="flex flex-col items-start gap-y-4 px-4 pt-4 sm:px-0">
        <ButtonBack destination="/forms" force-destination />
        <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2">
          <h1 class="text-xl font-semibold tracking-tighter text-balance">{{ form.title }}</h1>
          <Badge :variant="statusBadge.variant" :icon="statusBadge.icon">
            {{ form.status.charAt(0).toUpperCase() + form.status.slice(1) }}
          </Badge>
          <span
            v-if="form.responses_count !== undefined"
            class="text-muted-foreground text-sm tracking-tight tabular-nums"
          >
            {{ form.responses_count }} {{ form.responses_count === 1 ? "response" : "responses" }}
          </span>
          <div v-if="form.status === 'published'" class="ml-auto flex items-center gap-1">
            <ButtonCopy :text="publicFormUrl" />
            <button
              type="button"
              v-tippy="'Share form'"
              class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg"
              @click="shareDialogOpen = true"
            >
              <Icon name="hugeicons:share-03" class="size-4 shrink-0" />
            </button>
            <a
              :href="`/f/${form.slug}`"
              target="_blank"
              rel="noopener noreferrer"
              v-tippy="'Open public form'"
              class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg"
            >
              <Icon name="hugeicons:arrow-up-right-01" class="size-4 shrink-0" />
            </a>
          </div>
        </div>
      </div>

      <FormShareDialog v-model:open="shareDialogOpen" :form="form" />

      <TabNav :tabs="formTabs" class="mt-4" />

      <div ref="contentArea" class="pt-6">
        <NuxtPage :form="form" @refresh="refreshForm" />
      </div>
    </template>
  </div>
</template>

<script setup>
import FormShareDialog from "@/components/form-builder/FormShareDialog.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { TabNav } from "@/components/ui/tab-nav";
import { formStatusBadge } from "@/lib/formBuilderStatus";

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

const statusBadge = computed(() => formStatusBadge(form.value?.status));

const contentArea = ref(null);

const formBase = computed(() => `/forms/${route.params.slug}`);

const formTabs = computed(() => [
  { label: "Settings", to: formBase.value, exact: true },
  { label: "Fields", to: `${formBase.value}/fields` },
  { label: "Responses", to: `${formBase.value}/responses` },
  { label: "Analytics", to: `${formBase.value}/analytics` },
]);

const config = useRuntimeConfig();
const publicFormUrl = computed(() =>
  form.value ? `${config.public.siteUrl}/f/${form.value.slug}` : ""
);

const shareDialogOpen = ref(false);

useTabSwipe(contentArea, formTabs);

provide("form", form);
provide("refreshForm", refreshForm);
</script>
