<template>
  <div class="mx-auto max-w-sm space-y-6 pt-4 pb-16">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:reset-password" class="size-5 sm:size-6" />
      <h1 class="page-title">{{ userHasPassword ? $t('settings.changePassword') : $t('settings.createPassword') }}</h1>
    </div>

    <form @submit.prevent="handleSubmit" class="mt-8 grid gap-6">
      <!-- Current Password (only if user has existing password) -->
      <Field v-if="userHasPassword" :data-invalid="!!errors?.current_password">
        <FieldLabel for="current_password">{{ $t('settings.currentPassword') }}</FieldLabel>
        <InputPassword
          id="current_password"
          v-model="form.current_password"
          :aria-invalid="!!errors?.current_password"
          required
        />
        <FieldError v-if="errors?.current_password" :errors="errors.current_password" />
      </Field>

      <!-- New Password -->
      <Field :data-invalid="!!errors?.password">
        <FieldLabel for="password">{{ $t('settings.newPassword') }}</FieldLabel>
        <InputPassword
          id="password"
          v-model="form.password"
          :aria-invalid="!!errors?.password"
          required
        />
        <FieldError v-if="errors?.password" :errors="errors.password" />
      </Field>

      <!-- Confirm New Password -->
      <Field :data-invalid="!!errors?.password_confirmation">
        <FieldLabel for="password_confirmation">{{ $t('settings.confirmNewPassword') }}</FieldLabel>
        <InputPassword
          id="password_confirmation"
          v-model="form.password_confirmation"
          :aria-invalid="!!errors?.password_confirmation"
          required
        />
        <FieldError
          v-if="errors?.password_confirmation"
          :errors="errors.password_confirmation"
        />
      </Field>

      <!-- Submit Button -->
      <div class="flex justify-end">
        <Button type="submit" :disabled="isSubmitting">
          <Spinner v-if="isSubmitting" />
          {{ isSubmitting ? $t('settings.updatingPassword') : $t('settings.updatePassword') }}
          <KbdGroup>
            <Kbd>{{ metaSymbol }}</Kbd>
            <Kbd>S</Kbd>
          </KbdGroup>
        </Button>
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

const { t } = useI18n();

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

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

const pageTitle = computed(() => userHasPassword.value ? t('settings.changePassword') : t('settings.createPassword'));
usePageMeta(null, { title: pageTitle });

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
      toast.error(t('settings.failedToUpdatePassword'));
    }
  } finally {
    isSubmitting.value = false;
  }
};

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => handleSubmit(),
  },
});
</script>
