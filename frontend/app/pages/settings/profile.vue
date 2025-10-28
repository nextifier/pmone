<template>
  <div class="min-h-screen-offset mx-auto max-w-xl space-y-6">
    <template v-if="userData">
      <div class="flex flex-col gap-y-6">
        <div class="flex w-full items-center justify-between">
          <div class="flex items-center gap-x-2.5">
            <Icon name="hugeicons:edit-user-02" class="size-5 sm:size-6" />
            <h1 class="page-title">Edit Profile</h1>
          </div>

          <button
            @click="formUserRef?.handleSubmit()"
            :disabled="isSubmitting"
            class="text-primary-foreground hover:bg-primary/80 bg-primary flex items-center justify-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
          >
            <Spinner v-if="isSubmitting" />
            <span>Save</span>
          </button>
        </div>
      </div>

      <FormUser
        ref="formUserRef"
        :initial-data="userData"
        :loading="isSubmitting"
        :errors="errors"
        :is-create="false"
        :show-password="false"
        :show-account-settings="false"
        :show-roles="false"
        :show-images="true"
        submit-text="Update Profile"
        submit-loading-text="Updating.."
        @submit="handleSubmit"
      />
    </template>

    <template v-else>
      <div class="min-h-screen-offset flex w-full items-center justify-center">
        <div class="flex items-center gap-1.5">
          <Spinner class="size-4 shrink-0" />
          <span class="tracking-tight">Loading</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "sanctum-verified"],
  layout: "app",
});

usePageMeta("settingsProfile");

const sanctumFetch = useSanctumClient();
const { user } = useSanctumAuth();

// Refs
const formUserRef = ref(null);

// State
const userData = ref(null);
const errors = ref({});
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
    errors.value = {};
    isSubmitting.value = true;

    const response = await sanctumFetch("/api/user/profile", {
      method: "PUT",
      body: payload,
    });

    // Show success message
    toast.success(response?.message || "Profile updated successfully!");

    // Update local user data with response
    if (response.data) {
      userData.value = response.data;
      if (user.value) {
        Object.assign(user.value, response.data);
      }
    }

    // Stay on the same page (no navigation)
  } catch (error) {
    if (error.response?.status === 422 && error.response?._data?.errors) {
      errors.value = error.response._data.errors;
      const firstErrorField = Object.keys(error.response._data.errors)[0];
      const firstErrorMessage = error.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        error.response?._data?.message || error.message || "Failed to update profile";
      toast.error(errorMessage);
    }
    console.error("Error updating profile:", error);
  } finally {
    isSubmitting.value = false;
  }
};
</script>
