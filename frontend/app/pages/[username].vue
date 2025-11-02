<template>
  <div>
    <div v-if="status === 'pending'" class="min-h-screen-offset grid place-items-center">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-base tracking-tight">Loading</span>
      </div>
    </div>

    <ErrorState v-else-if="status === 'error'" :error="error" />

    <div
      v-else-if="status === 'success'"
      class="min-h-screen-offset mx-auto flex max-w-xl flex-col"
    >
      <div class="px-1">
        <div class="bg-muted aspect-[3/1] overflow-hidden rounded-xl">
          <img
            v-if="user.cover_image?.md"
            :src="user.cover_image.md"
            :alt="`${user.name} cover`"
            class="size-full object-cover"
            width="1500"
            height="500"
            loading="lazy"
          />
        </div>
      </div>

      <div class="-mt-12 flex grow flex-col px-4 lg:-mt-16">
        <div class="flex flex-col items-start space-y-2">
          <div class="ring-background relative isolate size-24 rounded-full ring-4 lg:size-32">
            <img
              v-if="user.profile_image?.sm"
              :src="user.profile_image.sm"
              :alt="user.name"
              class="size-full rounded-full object-cover"
              width="1080"
              height="1080"
              loading="lazy"
            />
            <div v-else class="bg-muted flex size-full items-center justify-center rounded-full">
              <Icon name="hugeicons:user" class="size-12" />
            </div>

            <span
              class="absolute top-1/2 right-0 z-[-1] size-8 translate-x-[calc(100%+0px)] -translate-y-full rounded-bl-[16px] bg-transparent shadow-[-16px_16px_0_var(--color-background)]"
            />
          </div>

          <div class="space-y-0.5">
            <div class="relative">
              <h1 class="line-clamp-1 text-xl font-semibold tracking-tighter">{{ user.name }}</h1>
              <Icon
                v-if="user?.roles?.some((role) => ['master', 'admin', 'staff'].includes(role))"
                name="mdi:verified"
                class="text-info absolute top-1/2 right-0 size-4.5 translate-x-[calc(100%+4px)] -translate-y-1/2"
              />
            </div>

            <p class="text-muted-foreground text-sm tracking-tight">@{{ user.username }}</p>
          </div>

          <p v-if="user.title" class="text-primary text-base font-medium tracking-tight">
            {{ user.title }}
          </p>

          <p v-if="user.bio" class="text-sm leading-relaxed tracking-tight">
            {{ user.bio }}
          </p>
        </div>

        <div v-if="socialLinks.length > 0" class="mt-4 flex gap-x-2">
          <NuxtLink
            v-for="link in socialLinks"
            :key="link.url || link.id"
            :to="link.url || '#'"
            :target="link.url?.startsWith('http') ? '_blank' : ''"
            @click="trackClick(link.label)"
            class="bg-muted text-foreground hover:bg-border flex size-12 items-center justify-center rounded-full transition active:scale-98"
            v-tippy="link.label"
          >
            <Icon :name="getSocialIcon(link.label)" class="size-5" />
          </NuxtLink>
        </div>

        <div v-if="customLinks.length > 0" class="mt-4 flex flex-col gap-y-2">
          <NuxtLink
            v-for="link in customLinks"
            :key="link.url || link.id"
            :to="link.url || '#'"
            :target="link.url?.startsWith('http') ? '_blank' : ''"
            @click="trackClick(link.label)"
            class="bg-muted text-foreground hover:bg-border block rounded-2xl px-6 py-4 text-center text-sm font-medium transition active:scale-98"
          >
            {{ link.label }}
          </NuxtLink>
        </div>

        <div class="mt-auto flex items-end justify-between gap-2 pb-8">
          <div></div>

          <ClientOnly>
            <div class="flex flex-col items-end gap-y-3 text-center">
              <QRCode :url="qrCodeUrl" canvas-class="size-24" />

              <p
                v-if="useRuntimeConfig().public.siteUrl"
                class="text-muted-foreground text-xs tracking-tight"
              >
                {{ useRuntimeConfig().public.siteUrl.replace(/^https?:\/\//, "") }}/{{
                  user.username
                }}
              </p>
            </div>
          </ClientOnly>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Constants extracted to top level (outside setup)
const SOCIAL_LABELS = ["website", "instagram", "facebook", "x", "tiktok", "linkedin", "youtube"];

const SOCIAL_ICON_MAP = {
  website: "hugeicons:globe-02",
  instagram: "hugeicons:instagram",
  facebook: "hugeicons:facebook-01",
  x: "hugeicons:new-twitter-rectangle",
  tiktok: "hugeicons:tiktok",
  linkedin: "hugeicons:linkedin-01",
  youtube: "hugeicons:youtube",
};

const route = useRoute();
const username = computed(() => route.params.username);

const {
  data,
  status,
  error: fetchError,
} = await useFetch(() => `/api/${username.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `user-profile-${username.value}`,
});

const user = computed(() => data.value?.data || null);

const error = computed(() => {
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

// Combined link filtering - single iteration instead of two
const { socialLinks, customLinks } = computed(() => {
  const links = user.value?.links || [];
  const social = [];
  const custom = [];

  for (const link of links) {
    if (!link?.label) continue;

    const labelLower = link.label.toLowerCase();
    if (SOCIAL_LABELS.includes(labelLower)) {
      social.push(link);
    } else {
      custom.push(link);
    }
  }

  return { socialLinks: social, customLinks: custom };
}).value;

const qrCodeUrl = computed(() => {
  if (!user.value?.username) return "";
  return `${window.location.origin}/${user.value.username}`;
});

const getSocialIcon = (label) => SOCIAL_ICON_MAP[label?.toLowerCase()] || "hugeicons:link-02";

if (import.meta.client) {
  watch(
    data,
    (newResponse) => {
      if (newResponse?.data?.destination_url) {
        window.location.href = newResponse.data.destination_url;
      }
    },
    { immediate: true }
  );
}

const trackClick = (linkLabel) => {
  if (!import.meta.client || !user.value?.id) return;

  // Fire and forget - non-blocking
  $fetch("/api/track/click", {
    method: "POST",
    baseURL: useRuntimeConfig().public.apiUrl,
    body: {
      clickable_type: "App\\Models\\User",
      clickable_id: user.value.id,
      link_label: linkLabel,
    },
  }).catch((err) => {
    console.error("Failed to track click:", err);
  });
};
</script>
