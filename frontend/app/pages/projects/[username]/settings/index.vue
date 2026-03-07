<template>
  <div class="flex flex-col gap-y-5">
    <template v-if="settingsProject">
      <div class="flex w-full items-start justify-between">
        <h2 class="page-title">General</h2>

        <Button size="sm" :disabled="loading" @click="formProjectRef?.handleSubmit()">
          <Spinner v-if="loading" />
          Save
          <KbdGroup>
            <Kbd>{{ metaSymbol }}</Kbd>
            <Kbd>S</Kbd>
          </KbdGroup>
        </Button>
      </div>

      <FormProject
        ref="formProjectRef"
        :initial-data="settingsProject"
        :eligible-members="eligibleMembers"
        :loading="loading"
        :errors="errors"
        :is-create="false"
        :hide-contact-form="true"
        submit-text="Update Project"
        submit-loading-text="Updating.."
        @submit="updateProject"
      />

      <div
        class="*:bg-muted text-muted-foreground mt-20 flex flex-wrap gap-x-2 gap-y-2.5 text-sm tracking-tight *:rounded-md *:px-2 *:py-1"
      >
        <span
          >ID: <span class="text-foreground">{{ settingsProject.id }}</span></span
        >
        <span
          >ULID: <span class="text-foreground">{{ settingsProject.ulid }}</span></span
        >
        <span
          >Created:
          <span class="text-foreground">{{
            $dayjs(settingsProject.created_at).format("MMM D, YYYY [at] h:mm A")
          }}</span></span
        >
      </div>
    </template>

    <template v-else>
      <div v-if="settingsLoading" class="flex items-center justify-center py-20">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { signalRefresh } = useDataRefresh();

const formProjectRef = ref(null);
const { metaSymbol } = useShortcuts();

const loading = ref(false);
const errors = ref({});

const { isAdminOrMaster: canEditprojects } = usePermission();

const { data: projectResponse, pending: settingsLoading } = await useLazySanctumFetch(
  () => `/api/projects/${route.params.username}`,
  {
    key: `project-settings-${route.params.username}`,
  }
);

const settingsProject = computed(() => projectResponse.value?.data || null);

const { data: eligibleMembersResponse } = await useLazySanctumFetch(
  "/api/projects/eligible-members",
  {
    key: "projects-eligible-members",
  }
);

const eligibleMembers = computed(() => eligibleMembersResponse.value?.data || []);

async function updateProject(payload) {
  loading.value = true;
  errors.value = {};

  try {
    if (!canEditprojects.value) {
      const allowedFields = [
        "name",
        "username",
        "email",
        "phone",
        "birth_date",
        "gender",
        "bio",
        "visibility",
        "tmp_profile_image",
        "tmp_cover_image",
      ];
      Object.keys(payload).forEach((key) => {
        if (!allowedFields.includes(key)) {
          delete payload[key];
        }
      });
    }

    const response = await sanctumFetch(`/api/projects/${settingsProject.value.username}`, {
      method: "PUT",
      body: payload,
    });

    if (response.data) {
      toast.success("Project updated successfully!");
      signalRefresh("projects-list");
      navigateTo(`/projects/${response.data.username}/settings`);
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || "Failed to update project";
      toast.error(errorMessage);
    }
  } finally {
    loading.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => `General · ${props.project?.name || ""}`),
});
</script>
