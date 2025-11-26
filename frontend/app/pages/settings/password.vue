<template>
  <div class="mx-auto max-w-sm space-y-6 pt-4 pb-16">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:reset-password" class="size-5 sm:size-6" />
      <h1 class="page-title">Change Password</h1>
    </div>

    <form @submit.prevent="handleSubmit" class="mt-8 grid gap-6">
      <!-- Current Password (only if user has existing password) -->
      <div v-if="userHasPassword" class="input-group">
        <label for="current_password">Current Password</label>
        <input
          id="current_password"
          v-model="form.current_password"
          type="password"
          :class="{ 'border-destructive': errors?.current_password }"
          required
        />
        <InputErrorMessage v-if="errors?.current_password" :errors="errors.current_password" />
      </div>

      <!-- New Password -->
      <div class="input-group">
        <label for="password">New Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          :class="{ 'border-destructive': errors?.password }"
          required
        />
        <InputErrorMessage v-if="errors?.password" :errors="errors.password" />
      </div>

      <!-- Confirm New Password -->
      <div class="input-group">
        <label for="password_confirmation">Confirm New Password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          :class="{ 'border-destructive': errors?.password_confirmation }"
          required
        />
        <InputErrorMessage
          v-if="errors?.password_confirmation"
          :errors="errors.password_confirmation"
        />
      </div>

      <!-- Submit Button -->
      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="isSubmitting"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-3 text-sm font-semibold tracking-tight transition active:scale-98"
        >
          <span>{{ isSubmitting ? "Updating.." : "Update Password" }}</span>

          <LoadingSpinner v-if="isSubmitting" class="border-primary-foreground size-4" />
        </button>
      </div>

      <div
        v-if="message"
        class="flex items-center gap-x-1.5 text-sm tracking-tight text-green-700 dark:text-green-500"
      >
        <Icon name="lucide:check" class="size-4" />
        <span>{{ message }}</span>
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

usePageMeta("settingsPassword");

const sanctumFetch = useSanctumClient();

// Form state
const form = reactive({
  current_password: "",
  password: "",
  password_confirmation: "",
});

const message = ref();
const errors = ref();
const isSubmitting = ref(false);
const userHasPassword = ref(true);

// Check password status with lazy loading
const { data: passwordStatusResponse } = await useLazySanctumFetch("/api/user/password-status", {
  key: "user-password-status",
});

watchEffect(() => {
  if (passwordStatusResponse.value?.has_password !== undefined) {
    userHasPassword.value = passwordStatusResponse.value.has_password;
  }
});

const checkPasswordStatus = async () => {
  try {
    const response = await sanctumFetch("/api/user/password-status");
    userHasPassword.value = response.has_password;
  } catch (error) {
    console.error("Failed to check password status:", error);
  }
};

// Submit handler
const handleSubmit = async () => {
  try {
    errors.value = null;
    isSubmitting.value = true;

    const payload = {
      password: form.password,
      password_confirmation: form.password_confirmation,
      current_password: userHasPassword.value ? form.current_password : "",
    };

    const response = await sanctumFetch("/api/user/password", {
      method: "PUT",
      body: payload,
    });

    // Show success message
    toast.success(response?.message);
    message.value = response?.message;

    // Clear form
    Object.assign(form, {
      current_password: "",
      password: "",
      password_confirmation: "",
    });
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
      toast.error("Failed to update password. Please try again.");
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>
