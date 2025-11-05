<template>
  <div class="mx-auto max-w-xl space-y-9">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/short-links" />
      <h1 class="page-title">Edit Short Link</h1>
    </div>

    <div v-if="loadingData" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <FormShortLink
      v-else-if="shortLink"
      ref="formRef"
      mode="edit"
      :short-link="shortLink"
    />

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

const sanctumFetch = useSanctumClient();

// Data state
const shortLink = ref(null);
const loadingData = ref(true);
const formRef = ref(null);

// Load short link
async function loadShortLink() {
  try {
    const response = await sanctumFetch(`/api/short-links/${slug.value}`);
    shortLink.value = response.data;
  } catch (err) {
    console.error("Error loading short link:", err);
    toast.error("Failed to load short link");
  } finally {
    loadingData.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await loadShortLink();
});

defineShortcuts({
  meta_s: {
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
