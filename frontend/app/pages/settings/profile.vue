<template>
  <div class="min-h-screen-offset mx-auto max-w-xl space-y-6 pt-4 pb-16">
    <template v-if="userData">
      <div class="flex flex-col gap-y-6">
        <div class="flex w-full items-center justify-between">
          <div class="flex items-center gap-x-2.5">
            <Icon name="hugeicons:edit-user-02" class="size-5 sm:size-6" />
            <h1 class="page-title">{{ $t('settings.editProfile') }}</h1>
          </div>

          <Button size="sm" :disabled="isSubmitting" @click="formUserRef?.handleSubmit()">
            <Spinner v-if="isSubmitting" />
            {{ $t('common.save') }}
            <KbdGroup>
              <Kbd>{{ metaSymbol }}</Kbd>
              <Kbd>S</Kbd>
            </KbdGroup>
          </Button>
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
const { metaSymbol } = useShortcuts();

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
      userDataResponse.value = response.data;
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

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      formUserRef.value?.handleSubmit();
    },
  },
});
</script>
