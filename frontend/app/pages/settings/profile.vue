<template>
  <div class="mx-auto max-w-md space-y-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:edit-user-02" class="size-5 sm:size-6" />
      <h1 class="page-title">Edit Profile</h1>
    </div>

    <!-- Success message -->
    <div
      v-if="message"
      class="flex items-center gap-x-1.5 rounded-lg border border-green-500 bg-green-100 p-4 text-sm tracking-tight text-green-700 dark:bg-green-900 dark:text-green-500"
    >
      <Icon name="lucide:check" class="size-4" />
      <span>{{ message }}</span>
    </div>

    <FormProfile
      :initial-data="userData"
      :loading="isSubmitting"
      :errors="errors"
      :is-create="false"
      :show-password="false"
      :show-account-settings="false"
      :show-roles="false"
      :show-images="true"
      :show-reset="false"
      submit-text="Update Profile"
      submit-loading-text="Updating.."
      @submit="handleSubmit"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";
import FormProfile from "@/components/FormProfile.vue";

definePageMeta({
  middleware: ["sanctum:auth", "sanctum-verified"],
  layout: "app",
});

usePageMeta("settingsProfile");

const sanctumFetch = useSanctumClient();
const { user } = useSanctumAuth();

const userData = ref(null);
const message = ref();
const errors = ref();
const isSubmitting = ref(false);

// Fetch user data with media on mount
onMounted(async () => {
  try {
    const response = await sanctumFetch("/api/user");
    userData.value = response;
  } catch (error) {
    console.error("Failed to fetch user data:", error);
  }
});

// Submit handler
const handleSubmit = async (payload) => {
  try {
    errors.value = null;
    isSubmitting.value = true;

    const response = await sanctumFetch("/api/user/profile", {
      method: "PUT",
      body: payload,
    });

    // Show success message
    toast.success(response?.message);
    message.value = response?.message;

    // Update local user data with response
    if (response.data) {
      userData.value = response.data;
      if (user.value) {
        Object.assign(user.value, response.data);
      }
    }
  } catch (error) {
    if (error.response?.status === 422) {
      // For validation errors, show the first validation error message
      const validationErrors = error.response?._data.errors;
      if (validationErrors) {
        // Get the first error message from the first field that has an error
        const firstErrorField = Object.keys(validationErrors)[0];
        const firstErrorMessage = validationErrors[firstErrorField][0];
        toast.error(firstErrorMessage);
      } else {
        toast.error(error.response?._data.message);
      }
      errors.value = validationErrors;
    } else {
      toast.error("Failed to update profile. Please try again.");
    }
  } finally {
    isSubmitting.value = false;
  }
};

defineShortcuts({
  meta_s: {
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
