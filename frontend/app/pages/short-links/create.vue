<template>
  <div class="mx-auto max-w-xl space-y-9">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/short-links" />
      <h1 class="page-title">Create Short Link</h1>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="space-y-2">
        <label for="slug" class="text-sm font-medium">Slug *</label>
        <input
          id="slug"
          v-model="form.slug"
          type="text"
          required
          placeholder="my-link"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:outline-none focus:ring-2"
          :class="{ 'border-destructive': errors.slug }"
        />
        <p v-if="errors.slug" class="text-destructive text-xs">{{ errors.slug[0] }}</p>
        <p class="text-muted-foreground text-xs">The short URL slug (letters, numbers, dots, underscores, hyphens)</p>
      </div>

      <div class="space-y-2">
        <label for="destination_url" class="text-sm font-medium">Destination URL *</label>
        <input
          id="destination_url"
          v-model="form.destination_url"
          type="url"
          required
          placeholder="https://example.com"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:outline-none focus:ring-2"
          :class="{ 'border-destructive': errors.destination_url }"
        />
        <p v-if="errors.destination_url" class="text-destructive text-xs">{{ errors.destination_url[0] }}</p>
        <p class="text-muted-foreground text-xs">The full URL where the short link will redirect</p>
      </div>

      <div class="flex items-center gap-2">
        <input
          id="is_active"
          v-model="form.is_active"
          type="checkbox"
          class="border-border bg-background focus:ring-primary size-4 rounded"
        />
        <label for="is_active" class="text-sm font-medium">Active</label>
      </div>

      <div class="flex gap-2">
        <button
          type="submit"
          :disabled="loading"
          class="bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 flex items-center gap-x-2 rounded-md px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed"
        >
          <Spinner v-if="loading" class="size-4" />
          <span>{{ loading ? 'Creating...' : 'Create Short Link' }}</span>
        </button>
        <nuxt-link
          to="/short-links"
          class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          Cancel
        </nuxt-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Create Short Link",
  description: "Create a new short link",
});

const sanctumFetch = useSanctumClient();

// Form state
const form = ref({
  slug: "",
  destination_url: "",
  is_active: true,
});

const loading = ref(false);
const errors = ref({});

// Handle submit
async function handleSubmit() {
  loading.value = true;
  errors.value = {};

  try {
    const response = await sanctumFetch("/api/short-links", {
      method: "POST",
      body: form.value,
    });

    if (response.data) {
      toast.success("Short link created successfully!");
      navigateTo("/short-links");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || "Failed to create short link";
      toast.error(errorMessage);
    }
    console.error("Error creating short link:", err);
  } finally {
    loading.value = false;
  }
}
</script>
