<template>
  <div class="min-h-screen-offset mx-auto max-w-xl space-y-6 pt-4 pb-16">
    <template v-if="userData">
      <div class="flex flex-col gap-y-6">
        <div class="flex w-full items-center justify-between">
          <div class="flex items-center gap-x-2.5">
            <Icon name="hugeicons:edit-user-02" class="size-5 sm:size-6" />
            <h1 class="page-title">{{ $t('settings.editProfile') }}</h1>
          </div>

          <button
            @click="formUserRef?.handleSubmit()"
            :disabled="isSubmitting"
            class="text-primary-foreground hover:bg-primary/80 bg-primary flex items-center justify-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
          >
            <Spinner v-if="isSubmitting" />
            <span>{{ $t('common.save') }}</span>
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
        :submit-text="$t('settings.updateProfile')"
        :submit-loading-text="$t('common.updating')"
        @submit="handleSubmit"
      />
    </template>

    <template v-else>
      <LoadingState />
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "sanctum-verified"],
  layout: "app",
});

const { t } = useI18n();

usePageMeta(null, { title: t('settings.editProfile') });

const sanctumFetch = useSanctumClient();
const { user } = useSanctumAuth();

// Refs
const formUserRef = ref(null);

// State
const errors = ref({});
const isSubmitting = ref(false);

// Fetch user data with lazy loading
const { data: userDataResponse } = await useLazySanctumFetch("/api/user", {
  key: "user-profile-settings",
});

const userData = computed(() => userDataResponse.value || null);

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
    toast.success(response?.message || t('settings.profileUpdatedSuccess'));

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
