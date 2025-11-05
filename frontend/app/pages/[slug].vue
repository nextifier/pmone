<template>
  <div>
    <ErrorState v-if="error" :error="error" />
    <div v-else class="flex min-h-screen items-center justify-center">
      <div class="flex items-center justify-center gap-x-1.5 font-medium tracking-tight">
        <Spinner class="size-4" />
        <span>Redirecting...</span>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: "empty",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const { data, error: fetchError } = await useFetch(() => `/api/s/${slug.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `short-link-${slug.value}`,
});

const error = computed(() => {
  if (!fetchError.value) return null;

  const err = fetchError.value;
  return {
    statusCode: err.statusCode || 500,
    statusMessage: err.data?.message || err.statusMessage || "Error",
    message: err.data?.message || err.message || "Page not found",
    stack: err.stack,
  };
});

const title = computed(() => {
  return error.value ? "Page Not Found" : "Redirecting...";
});

const description = computed(() => {
  return error.value
    ? error.value.message ||
        "The short link you are looking for does not exist or has been deactivated."
    : "Redirecting to destination";
});

watchEffect(() => {
  useSeoMeta({
    titleTemplate: "%s",
    title: title.value,
    ogTitle: title.value,
    description: description.value,
    ogDescription: description.value,
    ogUrl: useAppConfig().app.url + route.fullPath,
    twitterCard: "summary_large_image",
  });
});

if (import.meta.client) {
  watch(
    data,
    async (newResponse) => {
      const destinationUrl = newResponse?.data?.destination_url;
      if (destinationUrl) {
        try {
          await navigateTo(destinationUrl, {
            external: true,
            replace: true,
          });
        } catch (err) {
          console.error("Navigation failed, using fallback:", err);
          window.location.replace(destinationUrl);
        }
      }
    },
    { immediate: true }
  );
}
</script>
