<template>
  <div>
    <ErrorState v-if="error" :error="error" />
    <div v-else class="flex min-h-screen items-center justify-center">
      <div class="flex items-center justify-center gap-x-1.5 font-medium tracking-tight">
        <Spinner class="size-7" />
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

const sanctumFetch = useSanctumClient();

// Use useAsyncData to prevent duplicate requests during SSR/hydration
const { data, error: fetchError } = await useAsyncData(
  `short-link-${slug.value}`,
  async () => {
    try {
      return await sanctumFetch(`/api/s/${slug.value}`);
    } catch (err) {
      throw err;
    }
  },
  {
    // Only fetch once during SSR, use cached data on client
    server: true,
    lazy: false,
  }
);

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
  if (error.value) return "Page Not Found";

  const linkData = data.value?.data;
  return linkData?.og_title || linkData?.slug || "Redirecting...";
});

const description = computed(() => {
  if (error.value) {
    return (
      error.value.message ||
      "The short link you are looking for does not exist or has been deactivated."
    );
  }

  const linkData = data.value?.data;
  return linkData?.og_description || "Click to visit this link";
});

const ogImage = computed(() => {
  const linkData = data.value?.data;
  return linkData?.og_image || null;
});

const ogType = computed(() => {
  const linkData = data.value?.data;
  return linkData?.og_type || "website";
});

watchEffect(() => {
  useSeoMeta({
    titleTemplate: "%s",
    title: title.value,
    ogTitle: title.value,
    description: description.value,
    ogDescription: description.value,
    ogImage: ogImage.value,
    ogType: ogType.value,
    ogUrl: useAppConfig().app.url + route.fullPath,
    twitterCard: "summary_large_image",
  });
});

if (import.meta.client && data.value) {
  const destinationUrl = data.value?.data?.destination_url;
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
}
</script>
