<template>
  <ProfileView
    :profile="user"
    profile-type="user"
    :loading="status === 'pending'"
    :error="error"
    :can-edit="canEdit"
    :show-back-button="false"
    @track-click="trackClick"
  />
</template>

<script setup>
const route = useRoute();
const username = computed(() => route.params.username);

const { user: authUser } = useSanctumAuth();

const {
  data,
  status,
  error: fetchError,
} = await useLazyFetch(() => `/api/users/${username.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `user-profile-public-${username.value}`,
});

const user = computed(() => data.value?.data || null);

const error = computed(() => {
  if (!fetchError.value && user.value && user.value.status !== "active") {
    return {
      statusCode: 403,
      statusMessage: "Profile Not Available",
      message: "This profile is not available.",
      stack: null,
    };
  }

  if (!fetchError.value) return null;

  const err = fetchError.value;
  return {
    statusCode: err.statusCode || 500,
    statusMessage: err.data?.message || err.statusMessage || "Error",
    message: err.data?.message || err.message || "Failed to load profile",
    stack: err.stack,
  };
});

const title = user.value ? `${user.value.name} (@${user.value.username})` : "Profile";
const description = user.value?.bio || "View profile";

usePageMeta("", {
  title: title,
  description: description,
});

const canEdit = computed(() => {
  if (!authUser.value || !user.value) return false;
  if (authUser.value.id === user.value.id) return true;
  const userRoles = authUser.value.roles || [];
  return userRoles.some((role) => ["master", "admin"].includes(role));
});

const trackClick = (linkLabel) => {
  if (!import.meta.client || !user.value?.id) return;
  if (authUser.value?.id === user.value.id) return;

  $fetch("/api/track/click", {
    method: "POST",
    baseURL: useRuntimeConfig().public.apiUrl,
    credentials: "include",
    body: {
      clickable_type: "App\\Models\\User",
      clickable_id: user.value.id,
      link_label: linkLabel,
    },
  }).catch((err) => {
    console.error("Failed to track click:", err);
  });
};

const visitTracked = ref(false);

const trackProfileVisit = () => {
  if (!import.meta.client || !user.value?.id || visitTracked.value) return;
  if (authUser.value?.id === user.value.id) return;

  visitTracked.value = true;

  $fetch("/api/track/visit", {
    method: "POST",
    baseURL: useRuntimeConfig().public.apiUrl,
    credentials: "include",
    body: {
      visitable_type: "App\\Models\\User",
      visitable_id: user.value.id,
    },
  }).catch((err) => {
    console.error("Failed to track visit:", err);
    visitTracked.value = false;
  });
};

if (import.meta.client) {
  watch(
    user,
    (newUser) => {
      if (newUser?.id) {
        trackProfileVisit();
      }
    },
    { immediate: true }
  );
}
</script>
