<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Basic Information -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="name">Consumer Name</Label>
            <Input
              id="name"
              v-model="formData.name"
              type="text"
              required
              placeholder="My Blog Website"
            />
            <InputErrorMessage :errors="errors.name" />
            <p class="text-muted-foreground text-xs">Friendly name for this API consumer</p>
          </div>

          <div class="space-y-2">
            <Label for="website_url">Website URL</Label>
            <Input
              id="website_url"
              v-model="formData.website_url"
              type="url"
              required
              placeholder="https://example.com"
            />
            <InputErrorMessage :errors="errors.website_url" />
            <p class="text-muted-foreground text-xs">The primary URL of your website</p>
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea
              id="description"
              v-model="formData.description"
              placeholder="Brief description of this API consumer..."
              rows="3"
            />
            <InputErrorMessage :errors="errors.description" />
            <p class="text-muted-foreground text-xs">Optional description for this consumer</p>
          </div>
        </div>
      </div>
    </div>

    <!-- API Configuration -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">API Configuration</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="rate_limit">Rate Limit (requests per minute)</Label>
            <Input
              id="rate_limit"
              v-model="formData.rate_limit"
              type="text"
              inputmode="numeric"
              placeholder="60"
            />
            <InputErrorMessage :errors="errors.rate_limit" />
            <p class="text-muted-foreground text-xs">
              Set to <strong>0</strong> for unlimited access, or <strong>10-10000</strong> to limit
              requests per minute. Default is 60.
            </p>
          </div>

          <div class="space-y-2">
            <Label for="allowed_origins">Allowed Origins (CORS)</Label>
            <TagsInput v-model="formData.allowed_origins">
              <TagsInputItem v-for="item in formData.allowed_origins" :key="item" :value="item">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="https://example.com" />
            </TagsInput>
            <InputErrorMessage :errors="errors.allowed_origins" />
            <p class="text-muted-foreground text-xs">
              URLs allowed to make API requests from browsers. Add multiple origins for www/non-www
              variants. <strong>Leave empty to allow all origins</strong> (not recommended for
              production).
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Switch id="is_active" v-model="formData.is_active" />
            <Label for="is_active">Active</Label>
            <p class="text-muted-foreground ml-auto text-xs">
              Enable this API consumer to access the API
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- API Key Display (Edit/View Mode) -->
    <div v-if="mode === 'edit' && apiConsumer" class="frame">
      <div class="frame-header">
        <div class="frame-title">API Key</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-4">
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-4">
              <Label>Current API Key</Label>
              <DialogResponsive v-model:open="regenerateDialogOpen">
                <template #trigger="{ open }">
                  <button
                    type="button"
                    @click="open()"
                    :disabled="regenerating"
                    class="text-destructive-foreground hover:text-destructive-foreground/80 flex items-center gap-x-1 text-sm tracking-tight disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <Icon
                      name="lucide:refresh-cw"
                      class="size-4"
                      :class="{ 'animate-spin': regenerating }"
                    />
                    <span>Regenerate</span>
                  </button>
                </template>
                <template #default>
                  <div class="px-4 pb-10 md:px-6 md:py-5">
                    <div class="text-primary text-lg font-semibold tracking-tight">
                      Regenerate API Key?
                    </div>
                    <p class="text-body mt-1.5 text-sm tracking-tight">
                      Are you sure you want to regenerate the API key? The old key will stop working immediately.
                    </p>
                    <div class="mt-3 flex justify-end gap-2">
                      <button
                        class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                        @click="regenerateDialogOpen = false"
                        :disabled="regenerating"
                      >
                        Cancel
                      </button>
                      <button
                        @click="handleRegenerateKey"
                        :disabled="regenerating"
                        class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        <Spinner v-if="regenerating" class="size-4 text-white" />
                        <span v-else>Regenerate</span>
                      </button>
                    </div>
                  </div>
                </template>
              </DialogResponsive>
            </div>
            <div class="bg-muted flex items-center gap-2 rounded-md p-3">
              <code class="text-foreground/80 grow font-mono text-sm">
                {{ maskedApiKey }}
              </code>
              <ButtonCopy :text="currentApiKey" @click.prevent />
            </div>
            <p class="text-warning-foreground text-xs font-medium">
              Keep this key secure! Regenerating will invalidate the old key.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Consumer Information (Edit Mode Only) -->
    <div v-if="mode === 'edit' && apiConsumer" class="frame">
      <div class="frame-header">
        <div class="frame-title">Consumer Information</div>
      </div>
      <div class="frame-panel">
        <div class="text-muted-foreground space-y-3 text-sm">
          <div class="flex items-start justify-between gap-4">
            <span class="font-medium">Last Used:</span>
            <span class="text-right">
              <template v-if="apiConsumer.last_used_at">
                {{ $dayjs(apiConsumer.last_used_at).fromNow() }}
              </template>
              <template v-else>
                <span class="text-warning-foreground">Never used</span>
              </template>
            </span>
          </div>
          <div class="flex items-start justify-between gap-4">
            <span class="font-medium">Created:</span>
            <span>{{ $dayjs(apiConsumer.created_at).format("MMM D, YYYY") }}</span>
          </div>
          <div v-if="apiConsumer.updated_at" class="flex items-start justify-between gap-4">
            <span class="font-medium">Last Modified:</span>
            <span>{{ $dayjs(apiConsumer.updated_at).format("MMM D, YYYY") }}</span>
          </div>
          <div v-if="apiConsumer.creator" class="flex items-start justify-between gap-4">
            <span class="font-medium">Created By:</span>
            <span>{{ apiConsumer.creator.name }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex gap-2">
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" class="size-4" />
        <span>{{ loading ? loadingText : submitText }}</span>
      </Button>
      <Button type="button" variant="outline" as-child>
        <nuxt-link to="/api-consumers">Cancel</nuxt-link>
      </Button>
    </div>
  </form>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  apiConsumer: {
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
  website_url: "",
  description: "",
  rate_limit: 60,
  allowed_origins: [],
  is_active: true,
});

const errors = ref({});
const regenerating = ref(false);
const regenerateDialogOpen = ref(false);
const currentApiKey = ref(props.apiConsumer?.api_key || "");

// Initialize form data in edit mode
if (props.mode === "edit" && props.apiConsumer) {
  formData.value = {
    name: props.apiConsumer.name || "",
    website_url: props.apiConsumer.website_url || "",
    description: props.apiConsumer.description || "",
    rate_limit: props.apiConsumer.rate_limit !== null && props.apiConsumer.rate_limit !== undefined ? props.apiConsumer.rate_limit : 60,
    allowed_origins: props.apiConsumer.allowed_origins || [],
    is_active: props.apiConsumer.is_active ?? true,
  };
  currentApiKey.value = props.apiConsumer.api_key || "";
}

// Computed properties
const submitText = computed(() =>
  props.mode === "create" ? "Create Consumer" : "Update Consumer"
);
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Updating..."));

const maskedApiKey = computed(() => {
  if (!currentApiKey.value) return "";
  const key = currentApiKey.value;
  return key.substring(0, 10) + "•••••••••••••••••••" + key.substring(key.length - 5);
});

// Form submission
const handleSubmit = async () => {
  errors.value = {};
  emit("update:loading", true);

  try {
    // Parse rate_limit to ensure it's a number
    let rateLimitValue = 60; // default
    if (formData.value.rate_limit !== "" && formData.value.rate_limit !== null && formData.value.rate_limit !== undefined) {
      rateLimitValue = parseInt(formData.value.rate_limit, 10);
      // Handle NaN case
      if (isNaN(rateLimitValue)) {
        rateLimitValue = 60;
      }
    }

    const payload = {
      name: formData.value.name,
      website_url: formData.value.website_url,
      description: formData.value.description || null,
      rate_limit: rateLimitValue,
      allowed_origins:
        formData.value.allowed_origins.length > 0 ? formData.value.allowed_origins : null,
      is_active: formData.value.is_active,
    };

    let response;
    if (props.mode === "create") {
      response = await sanctumFetch("/api/api-consumers", {
        method: "POST",
        body: payload,
      });
    } else {
      response = await sanctumFetch(`/api/api-consumers/${props.apiConsumer.id}`, {
        method: "PUT",
        body: payload,
      });
    }

    toast.success(
      response.message ||
        `API Consumer ${props.mode === "create" ? "created" : "updated"} successfully`
    );

    if (props.mode === "create" && response.data?.api_key) {
      // Show API key to user (only shown once on creation)
      toast.success("Save your API key!", {
        description: `API Key: ${response.data.api_key}`,
        duration: 10000,
      });
    }

    emit("submit", response.data);
  } catch (error) {
    console.error("Form submission error:", error);

    if (error?.data?.errors) {
      errors.value = error.data.errors;
    }

    toast.error(error?.data?.message || "Failed to save API consumer");
  } finally {
    emit("update:loading", false);
  }
};

// Regenerate API key
const handleRegenerateKey = async () => {
  regenerating.value = true;

  try {
    const response = await sanctumFetch(
      `/api/api-consumers/${props.apiConsumer.id}/regenerate-key`,
      {
        method: "POST",
      }
    );

    if (response.data?.api_key) {
      // Update the reactive API key
      currentApiKey.value = response.data.api_key;

      // Also update the prop for consistency
      props.apiConsumer.api_key = response.data.api_key;

      // Close the dialog
      regenerateDialogOpen.value = false;

      toast.success("API key regenerated successfully!", {
        description: `New API Key: ${response.data.api_key}`,
        duration: 10000,
      });
    }
  } catch (error) {
    console.error("Failed to regenerate API key:", error);
    toast.error(error?.data?.message || "Failed to regenerate API key");
  } finally {
    regenerating.value = false;
  }
};

// Keyboard shortcuts
defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
