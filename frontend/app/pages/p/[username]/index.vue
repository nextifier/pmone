<template>
  <ProfileView
    :profile="project"
    profile-type="project"
    :loading="status === 'pending'"
    :error="error"
    :can-edit="canEdit"
    :show-back-button="authUser?.roles?.some((role) => ['master', 'admin', 'staff'].includes(role))"
    back-destination="/projects"
    @track-click="trackClick"
  />
</template>

<script setup>
definePageMeta({
  layout: "empty",
});

const route = useRoute();
const username = computed(() => route.params.username);

const { user: authUser } = useSanctumAuth();

const {
  data,
  status,
  error: fetchError,
} = await useLazyFetch(() => `/api/projects/${username.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `project-profile-${username.value}`,
});

const project = computed(() => data.value?.data || null);

const error = computed(() => {
  if (!fetchError.value && project.value && project.value.status !== "active") {
    return {
      statusCode: 403,
      statusMessage: "Project Not Available",
      message: "This project is not available.",
      stack: null,
    };
  }

  if (!fetchError.value) return null;

  const err = fetchError.value;
  return {
    statusCode: err.statusCode || 500,
    statusMessage: err.data?.message || err.statusMessage || "Error",
    message: err.data?.message || err.message || "Failed to load project",
    stack: err.stack,
  };
});

const title = project.value ? `${project.value.name} (@${project.value.username})` : "Project";
const description = project.value?.bio || "View project";

usePageMeta(null, {
  title: title,
  description: description,
});

const canEdit = computed(() => {
  if (!authUser.value || !project.value) return false;
  if (authUser.value.id === project.value.user_id) return true;
  const userRoles = authUser.value.roles || [];
  return userRoles.some((role) => ["master", "admin"].includes(role));
});

const trackClick = (linkLabel) => {
  if (!import.meta.client || !project.value?.id) return;

  $fetch("/api/track/click", {
    method: "POST",
    baseURL: useRuntimeConfig().public.apiUrl,
    credentials: "include",
    body: {
      clickable_type: "App\\Models\\Project",
      clickable_id: project.value.id,
      link_label: linkLabel,
    },
  }).catch((err) => {
    console.error("Failed to track click:", err);
  });
};

const visitTracked = ref(false);

const trackProfileVisit = () => {
  if (!import.meta.client || !project.value?.id || visitTracked.value) return;

  visitTracked.value = true;

  $fetch("/api/track/visit", {
    method: "POST",
    baseURL: useRuntimeConfig().public.apiUrl,
    credentials: "include",
    body: {
      visitable_type: "App\\Models\\Project",
      visitable_id: project.value.id,
    },
  }).catch((err) => {
    console.error("Failed to track visit:", err);
    visitTracked.value = false;
  });
};

if (import.meta.client) {
  watch(
    project,
    (newProject) => {
      if (newProject?.id) {
        trackProfileVisit();
      }
    },
    { immediate: true }
  );
}
</script>
