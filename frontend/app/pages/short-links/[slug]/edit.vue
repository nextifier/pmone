<template>
  <div class="mx-auto max-w-lg space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/short-links" />

      <div class="flex w-full items-center justify-between gap-2">
        <h1 class="page-title">Edit Short Link</h1>

        <nuxt-link
          v-if="shortLink?.slug"
          :to="`/short-links/${shortLink.slug}/analytics`"
          class="text-primary bg-muted hover:bg-border inline-flex items-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight"
        >
          <Icon name="lucide:chart-no-axes-combined" class="size-4" />
          <span>View Analytics</span>
        </nuxt-link>
      </div>
    </div>

    <div v-if="loadingData" class="flex justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <FormShortLink v-else-if="shortLink" ref="formRef" mode="edit" :short-link="shortLink" />

    <div v-else class="py-12 text-center">
      <p class="text-muted-foreground">Short link not found</p>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

usePageMeta("", {
  title: `Edit Short Link - ${slug.value}`,
  description: "Edit short link",
});

// Data state
const formRef = ref(null);

const {
  data: shortLinkResponse,
  pending: loadingData,
  error: fetchError,
  refresh: loadShortLink,
} = await useLazySanctumFetch(() => `/api/short-links/${slug.value}`, {
  key: `short-link-edit-${slug.value}`,
});

const shortLink = computed(() => shortLinkResponse.value?.data || null);

const error = computed(() => {
  if (!fetchError.value) return null;
  if (fetchError.value.statusCode === 404) return "Short link not found";
  if (fetchError.value.statusCode === 403) return "You do not have permission";
  return fetchError.value.message || "Failed to load short link";
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
