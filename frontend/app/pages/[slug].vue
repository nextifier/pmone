<template>
  <div v-if="status === 'pending'" class="flex min-h-screen items-center justify-center">
    <div class="text-center">
      <div class="mb-4 inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]" />
      <p class="text-muted-foreground">Redirecting...</p>
    </div>
  </div>
  <div v-else-if="error" class="flex min-h-screen items-center justify-center">
    <div class="text-center">
      <h1 class="text-4xl font-bold">{{ error.statusCode }}</h1>
      <p class="text-muted-foreground">{{ error.message }}</p>
    </div>
  </div>
</template>

<script setup>
const route = useRoute();
const slug = computed(() => route.params.slug);

const {
  data,
  status,
  error: fetchError,
} = await useFetch(() => `/api/s/${slug.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `short-link-${slug.value}`,
});

const error = computed(() => {
  if (!fetchError.value) return null;

  const err = fetchError.value;
  return {
    statusCode: err.statusCode || 500,
    statusMessage: err.data?.message || err.statusMessage || "Error",
    message: err.data?.message || err.message || "Short link not found",
    stack: err.stack,
  };
});

const title = "Redirecting...";
const description = "Redirecting to destination";

usePageMeta("", {
  title: title,
  description: description,
});

// Redirect when short link data is loaded
if (import.meta.client) {
  watch(
    data,
    (newResponse) => {
      if (newResponse?.data?.destination_url) {
        window.location.href = newResponse.data.destination_url;
      }
    },
    { immediate: true }
  );
}
</script>
