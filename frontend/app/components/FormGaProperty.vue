<template>
  <div class="space-y-10">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Property Name</label>
        <input
          id="name"
          v-model="formData.name"
          type="text"
          required
          placeholder="Main Website"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.name }"
        />
        <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name[0] }}</p>
        <p class="text-muted-foreground text-xs">Friendly name for this GA4 property</p>
      </div>

      <div class="space-y-2">
        <label for="property_id" class="text-sm font-medium">GA4 Property ID</label>
        <input
          id="property_id"
          v-model="formData.property_id"
          type="text"
          required
          placeholder="123456789"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.property_id }"
        />
        <p v-if="errors.property_id" class="text-destructive text-xs">
          {{ errors.property_id[0] }}
        </p>
        <p class="text-muted-foreground text-xs">
          The Google Analytics 4 property ID (numbers only)
        </p>
      </div>

      <div class="space-y-2">
        <label for="account_name" class="text-sm font-medium">Account Name</label>
        <input
          id="account_name"
          v-model="formData.account_name"
          type="text"
          required
          placeholder="Company Analytics Account"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.account_name }"
        />
        <p v-if="errors.account_name" class="text-destructive text-xs">
          {{ errors.account_name[0] }}
        </p>
        <p class="text-muted-foreground text-xs">The Google Analytics account name</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="sync_frequency" class="text-sm font-medium">Sync Frequency (minutes)</label>
          <input
            id="sync_frequency"
            v-model.number="formData.sync_frequency"
            type="number"
            min="5"
            max="60"
            placeholder="10"
            class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
            :class="{ 'border-destructive': errors.sync_frequency }"
          />
          <p v-if="errors.sync_frequency" class="text-destructive text-xs">
            {{ errors.sync_frequency[0] }}
          </p>
          <p class="text-muted-foreground text-xs">How often to sync data (5-60 min)</p>
        </div>

        <div class="space-y-2">
          <label for="rate_limit_per_hour" class="text-sm font-medium"
            >Rate Limit (per hour)</label
          >
          <input
            id="rate_limit_per_hour"
            v-model.number="formData.rate_limit_per_hour"
            type="number"
            min="1"
            max="100"
            placeholder="12"
            class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
            :class="{ 'border-destructive': errors.rate_limit_per_hour }"
          />
          <p v-if="errors.rate_limit_per_hour" class="text-destructive text-xs">
            {{ errors.rate_limit_per_hour[0] }}
          </p>
          <p class="text-muted-foreground text-xs">Max API requests per hour (1-100)</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Switch id="is_active" v-model="formData.is_active" />
        <label for="is_active" class="text-sm font-medium">Active</label>
        <p class="text-muted-foreground text-xs ml-auto">
          Enable automatic data syncing for this property
        </p>
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
          to="/ga-properties"
          class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          Cancel
        </nuxt-link>
      </div>
    </form>

    <div v-if="mode === 'edit' && gaProperty" class="frame !bg-background p-4">
      <div class="flex items-start justify-between gap-4">
        <div class="flex flex-col gap-y-2">
          <div class="text-sm font-medium tracking-tight">Property Information</div>
          <div class="text-muted-foreground space-y-1 text-xs">
            <p>
              <span class="font-medium">Last Synced:</span>
              {{
                gaProperty.last_synced_at
                  ? $dayjs(gaProperty.last_synced_at).fromNow()
                  : "Never"
              }}
            </p>
            <p>
              <span class="font-medium">Created:</span>
              {{ $dayjs(gaProperty.created_at).format("MMM D, YYYY") }}
            </p>
          </div>
        </div>
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
  gaProperty: {
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
const { $dayjs } = useNuxtApp();

// Form state
const formData = ref({
  name: "",
  property_id: "",
  account_name: "",
  is_active: true,
  sync_frequency: 10,
  rate_limit_per_hour: 12,
});

const errors = ref({});
const internalLoading = ref(false);

// Computed texts based on mode
const submitText = computed(() => (props.mode === "create" ? "Create Property" : "Save Changes"));
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Saving..."));
const loading = computed(() => props.loading || internalLoading.value);

// Populate form when editing
watch(
  () => props.gaProperty,
  (newProperty) => {
    if (newProperty && props.mode === "edit") {
      formData.value = {
        name: newProperty.name,
        property_id: newProperty.property_id,
        account_name: newProperty.account_name,
        is_active: newProperty.is_active,
        sync_frequency: newProperty.sync_frequency || 10,
        rate_limit_per_hour: newProperty.rate_limit_per_hour || 12,
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
      props.mode === "create"
        ? "/api/google-analytics/ga-properties"
        : `/api/google-analytics/ga-properties/${props.gaProperty.id}`;

    const method = props.mode === "create" ? "POST" : "PUT";

    const response = await sanctumFetch(endpoint, {
      method,
      body: formData.value,
    });

    toast.success(
      props.mode === "create" ? "GA property created successfully" : "GA property updated successfully"
    );

    // Redirect to index page
    await navigateTo("/ga-properties");
  } catch (error) {
    if (error.status === 422 && error.data?.errors) {
      errors.value = error.data.errors;
      toast.error("Validation error", {
        description: "Please check the form for errors",
      });
    } else {
      toast.error(
        props.mode === "create" ? "Failed to create GA property" : "Failed to update GA property",
        {
          description: error?.data?.message || error?.message || "An error occurred",
        }
      );
    }
  } finally {
    internalLoading.value = false;
  }
}

// Expose handleSubmit for parent component
defineExpose({ handleSubmit });
</script>
