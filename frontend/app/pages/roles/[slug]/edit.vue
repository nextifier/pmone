<template>
  <div class="mx-auto max-w-lg space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/roles" />

      <div class="flex w-full items-center justify-between gap-2">
        <h1 class="page-title">Edit Role</h1>
      </div>
    </div>

    <LoadingState v-if="loadingData" label="Loading role.." />

    <FormRole v-else-if="role" ref="formRef" mode="edit" :role="role" />

    <div v-else class="py-12 text-center">
      <p class="text-muted-foreground">Role not found</p>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["roles.update"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

usePageMeta("", {
  title: `Edit Role - ${slug.value}`,
  description: "Edit role",
});

const { user } = useSanctumAuth();

// Check if user has master role
if (!user.value?.roles?.includes("master")) {
  navigateTo("/dashboard");
}

// Data state
const formRef = ref(null);

const {
  data: roleResponse,
  pending: loadingData,
  error: fetchError,
  refresh: loadRole,
} = await useLazySanctumFetch(() => `/api/roles/${slug.value}`, {
  key: `role-edit-${slug.value}`,
});

const role = computed(() => roleResponse.value?.data || null);

const error = computed(() => {
  if (!fetchError.value) return null;
  if (fetchError.value.statusCode === 404) return "Role not found";
  if (fetchError.value.statusCode === 403) return "You do not have permission";
  return fetchError.value.message || "Failed to load role";
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
