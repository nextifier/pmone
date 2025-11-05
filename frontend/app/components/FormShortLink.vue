<template>
  <div class="space-y-10">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="space-y-2">
        <label for="slug" class="text-sm font-medium">Slug</label>
        <input
          id="slug"
          v-model="formData.slug"
          type="text"
          required
          placeholder="my-link"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.slug }"
        />
        <p v-if="errors.slug" class="text-destructive text-xs">{{ errors.slug[0] }}</p>
        <p class="text-muted-foreground text-xs">
          The short URL slug (letters, numbers, dots, underscores, hyphens)
        </p>
      </div>

      <div class="space-y-2">
        <label for="destination_url" class="text-sm font-medium">Destination URL</label>
        <input
          id="destination_url"
          v-model="formData.destination_url"
          type="url"
          required
          placeholder="https://example.com"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.destination_url }"
        />
        <p v-if="errors.destination_url" class="text-destructive text-xs">
          {{ errors.destination_url[0] }}
        </p>
        <p class="text-muted-foreground text-xs">The full URL where the short link will redirect</p>
      </div>

      <div class="flex items-center gap-2">
        <Switch id="is_active" v-model="formData.is_active" />
        <label for="is_active" class="text-sm font-medium">Active</label>
      </div>

      <div class="flex gap-2">
        <button
          type="submit"
          :disabled="loading"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-2 rounded-md px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="loading" class="size-4" />
          <span>{{ loading ? loadingText : submitText }}</span>
        </button>
        <nuxt-link
          to="/short-links"
          class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          Cancel
        </nuxt-link>
      </div>
    </form>

    <div v-if="mode === 'edit' && shortLink" class="frame !bg-background p-4">
      <div class="flex items-end justify-between gap-2">
        <div class="flex flex-col items-start gap-y-2">
          <div class="text-sm font-medium tracking-tight">Total Clicks</div>
          <div class="text-3xl font-bold tracking-tighter">
            {{ shortLink.clicks_count?.toLocaleString() || 0 }}
          </div>
        </div>

        <nuxt-link
          :to="`/short-links/${shortLink.slug}/analytics`"
          class="text-primary bg-muted hover:bg-border inline-flex items-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight"
        >
          <Icon name="lucide:chart-no-axes-combined" class="size-4" />
          <span>View Analytics</span>
        </nuxt-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  shortLink: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["submit", "update:loading"]);

const sanctumFetch = useSanctumClient();

// Form state
const formData = ref({
  slug: "",
  destination_url: "",
  is_active: true,
});

const errors = ref({});
const internalLoading = ref(false);

// Computed texts based on mode
const submitText = computed(() => (props.mode === "create" ? "Create Short Link" : "Save Changes"));
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Saving..."));
const loading = computed(() => props.loading || internalLoading.value);

// Populate form when editing
watch(
  () => props.shortLink,
  (newShortLink) => {
    if (newShortLink && props.mode === "edit") {
      formData.value = {
        slug: newShortLink.slug,
        destination_url: newShortLink.destination_url,
        is_active: newShortLink.is_active,
      };
    }
  },
  { immediate: true }
);

// Handle submit
async function handleSubmit() {
  internalLoading.value = true;
  errors.value = {};

  try {
    const endpoint =
      props.mode === "create" ? "/api/short-links" : `/api/short-links/${props.shortLink.slug}`;

    const method = props.mode === "create" ? "POST" : "PUT";

    const response = await sanctumFetch(endpoint, {
      method,
      body: formData.value,
    });

    if (response.data) {
      const successMessage =
        props.mode === "create"
          ? "Short link created successfully!"
          : "Short link updated successfully!";
      toast.success(successMessage);
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
        err.response?._data?.message || err.message || `Failed to ${props.mode} short link`;
      toast.error(errorMessage);
    }
    console.error(`Error ${props.mode}ing short link:`, err);
  } finally {
    internalLoading.value = false;
  }
}

// Expose submit handler for keyboard shortcuts
defineExpose({
  handleSubmit,
});
</script>
