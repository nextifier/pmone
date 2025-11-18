<template>
  <div class="mx-auto max-w-lg space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/roles" />

      <div class="flex w-full items-center justify-between gap-2">
        <h1 class="page-title">Edit Role</h1>
      </div>
    </div>

    <div v-if="loadingData" class="flex justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <FormRole v-else-if="role" ref="formRef" mode="edit" :role="role" />

    <div v-else class="py-12 text-center">
      <p class="text-muted-foreground">Role not found</p>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
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

const sanctumFetch = useSanctumClient();

// Data state
const role = ref(null);
const loadingData = ref(true);
const formRef = ref(null);

// Load role
async function loadRole() {
  try {
    const response = await sanctumFetch(`/api/roles/${slug.value}`);
    role.value = response.data;
  } catch (err) {
    console.error("Error loading role:", err);
    toast.error("Failed to load role");
  } finally {
    loadingData.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await loadRole();
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
