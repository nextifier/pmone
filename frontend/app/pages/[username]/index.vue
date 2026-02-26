<template>
  <div v-if="resolvedType === 'shortlink'">
    <ErrorState v-if="error" :error="error" />
    <div v-else class="flex min-h-screen items-center justify-center">
      <div class="flex items-center justify-center gap-x-1.5 font-medium tracking-tight">
        <SpinnerAlt size="2rem" border-width="4px" />
      </div>
    </div>
  </div>

  <ProfileView
    v-else
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
definePageMeta({
  layout: "empty",
});

const route = useRoute();
const slug = computed(() => route.params.username);

const { user: authUser } = useSanctumAuth();

const {
  data,
  status,
  error: fetchError,
} = await useLazyFetch(() => `/api/resolve/${slug.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `resolve-slug-${slug.value}`,
});

const resolvedType = computed(() => data.value?.type || null);
const user = computed(() => (resolvedType.value === "user" ? data.value?.data : null));
const shortLinkData = computed(() =>
  resolvedType.value === "shortlink" ? data.value?.data : null
);

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
    message: err.data?.message || err.message || "Page not found",
    stack: err.stack,
  };
});

// SEO for user profiles
const title = computed(() => {
  if (resolvedType.value === "shortlink") {
    const link = shortLinkData.value;
    return link?.og_title || link?.slug || "Redirecting...";
  }
  return user.value ? `${user.value.name} (@${user.value.username})` : "Profile";
});

const description = computed(() => {
  if (resolvedType.value === "shortlink") {
    const link = shortLinkData.value;
    return link?.og_description || "Click to visit this link";
  }
  return user.value?.bio || "View profile";
});

// Handle short link OG meta (with custom titleTemplate)
if (resolvedType.value === "shortlink") {
  const ogImage = computed(() => shortLinkData.value?.og_image || null);
  const ogType = computed(() => shortLinkData.value?.og_type || "website");

  watchEffect(() => {
    useSeoMeta({
      titleTemplate: "%s",
      title: title.value,
      ogTitle: title.value,
      description: description.value,
      ogDescription: description.value,
      ogImage: ogImage.value,
      ogType: ogType.value,
      ogUrl: useAppConfig().app.url + route.fullPath,
      twitterCard: "summary_large_image",
    });
  });
} else {
  usePageMeta(null, {
    title: title,
    description: description,
  });
}

// Short link: track click then redirect (client-side only)
if (import.meta.client && shortLinkData.value) {
  const { id, destination_url: destinationUrl } = shortLinkData.value;

  // Track click with credentials (same pattern as trackProfileVisit/trackClick)
  if (id) {
    await $fetch("/api/track/click", {
      method: "POST",
      baseURL: useRuntimeConfig().public.apiUrl,
      credentials: "include",
      body: {
        clickable_type: "App\\Models\\ShortLink",
        clickable_id: id,
        link_label: shortLinkData.value.slug,
      },
    }).catch((err) => {
      console.error("Failed to track short link click:", err);
    });
  }

  if (destinationUrl) {
    try {
      await navigateTo(destinationUrl, {
        external: true,
        replace: true,
      });
    } catch (err) {
      console.error("Navigation failed, using fallback:", err);
      window.location.replace(destinationUrl);
    }
  }
}

// User profile logic
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
