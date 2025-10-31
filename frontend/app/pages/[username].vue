<template>
  <div>
    <div v-if="status === 'pending'" class="min-h-screen-offset grid place-items-center">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-base tracking-tight">Loading</span>
      </div>
    </div>

    <div
      v-else-if="status === 'error'"
      class="min-h-screen-offset flex flex-col items-center justify-center overflow-hidden"
    >
      <div class="container flex flex-col items-center justify-center gap-y-3 text-center">
        <span v-if="error?.statusCode" class="text-sm">
          {{ error.statusCode }}
        </span>

        <h1
          v-if="error?.statusMessage"
          class="text-primary w-full text-4xl font-bold tracking-tighter wrap-break-word"
        >
          {{ error.statusMessage }}
        </h1>

        <p class="mx-auto mt-1 max-w-2xl tracking-tight text-pretty">
          We couldn't find the page you're looking for. It might have moved, been renamed, or maybe
          it never existed in the first place.
        </p>

        <pre
          v-if="error?.stack && error?.statusCode === 500"
          class="text-muted-foreground mt-3 w-full max-w-xl overflow-auto rounded-2xl border px-4 py-6 text-left text-xs leading-normal!"
          >{{ error.stack }}</pre
        >

        <button
          @click="() => clearError({ redirect: '/' })"
          class="bg-primary/8 text-foreground hover:bg-primary/14 mt-2 flex items-center gap-x-1 rounded-md px-3 py-2 text-sm font-medium tracking-tight transition active:scale-98"
        >
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          <span>Back to Home</span>
        </button>
      </div>
    </div>

    <div
      v-else-if="status === 'success'"
      class="min-h-screen-offset mx-auto flex max-w-xl flex-col"
    >
      <div class="px-1">
        <div class="bg-muted aspect-[3/1] overflow-hidden rounded-xl">
          <img
            v-if="user.cover_image"
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
              v-if="user.profile_image"
              :src="user.profile_image.sm"
              :alt="user.name"
              class="size-full rounded-full object-cover"
              width="1080"
              height="1080"
              loading="lazy"
            />
            <div v-else class="bg-muted flex size-full items-center justify-center">
              <Icon name="hugeicons:user" class="size-12" />
            </div>

            <!-- <span
            class="absolute top-1/2 left-0 z-[-1] size-8 -translate-x-[calc(100%+0px)] -translate-y-full rounded-br-[16px] bg-transparent shadow-[16px_16px_0_var(--color-background)]"
          /> -->

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
            :key="link.url"
            :to="link.url"
            :target="link.url.startsWith('http') ? '_blank' : ''"
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
            :key="link.url"
            :to="link.url"
            :target="link.url.startsWith('http') ? '_blank' : ''"
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
              <div class="relative isolate">
                <canvas ref="qrcodeCanvas" class="size-24"></canvas>
                <div class="absolute -inset-2 z-[-1] rounded-xl bg-white"></div>
              </div>

              <p class="text-muted-foreground text-xs tracking-tight">
                {{ currentDomain }}/{{ user.username }}
              </p>

              <button
                @click="saveNamecard"
                class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition active:scale-98"
              >
                <Icon name="hugeicons:download-01" class="size-4" />
                <span class="tracking-tight">Save namecard</span>
              </button>
            </div>
          </ClientOnly>
        </div>
      </div>

      <!-- <DevOnly>
        <div
          class="border-border text-foreground mt-8 w-full overflow-x-scroll rounded-xl border p-4"
        >
          <pre class="text-foreground/80 text-sm !leading-[1.5]">{{ user }}</pre>
        </div>
      </DevOnly> -->
    </div>
  </div>
</template>

<script setup>
import QRCode from "qrcode";

const route = useRoute();
const username = computed(() => route.params.username);
const qrcodeCanvas = ref(null);

// Get current domain dynamically (client-side only)
const currentDomain = ref("");
onMounted(() => {
  currentDomain.value = window.location.host;
});

const {
  data,
  status,
  error: fetchError,
} = await useFetch(() => `/api/${username.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `user-profile-${username.value}`,
});

const user = computed(() => data.value?.data || null);

// Format error object to prioritize backend custom message
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

// Handle short link redirect
watch(
  data,
  (newResponse) => {
    if (newResponse?.data?.destination_url) {
      window.location.href = newResponse.data.destination_url;
    }
  },
  { immediate: true }
);

// Generate QR code
const generateQRCode = async () => {
  if (qrcodeCanvas.value && user.value) {
    try {
      const url = `${window.location.origin}/${user.value.username}`;

      // Get canvas size from element (respects Tailwind classes)
      const canvasSize = qrcodeCanvas.value.clientWidth || 96; // default to 96px (size-24)

      // QR code always uses black on white background for best readability
      // This is the standard for QR codes regardless of theme
      await QRCode.toCanvas(qrcodeCanvas.value, url, {
        width: canvasSize,
        margin: 0,
        color: {
          dark: "#000000", // QR code modules (always black)
          light: "#FFFFFF", // Background (always white)
        },
      });
    } catch (err) {
      console.error("Failed to generate QR code:", err);
    }
  }
};

// Computed properties
const socialLinks = computed(() => {
  if (!user.value?.links) {
    return [];
  }

  const socialLabels = ["website", "instagram", "facebook", "x", "tiktok", "linkedin", "youtube"];
  return user.value.links.filter((link) => socialLabels.includes(link.label?.toLowerCase()));
});

const customLinks = computed(() => {
  if (!user.value?.links) {
    return [];
  }

  const socialLabels = ["website", "instagram", "facebook", "x", "tiktok", "linkedin", "youtube"];
  return user.value.links.filter((link) => !socialLabels.includes(link.label?.toLowerCase()));
});

// Get social icon
const getSocialIcon = (label) => {
  const iconMap = {
    website: "hugeicons:globe-02",
    instagram: "mdi:instagram",
    facebook: "mdi:facebook",
    x: "mdi:twitter",
    tiktok: "mdi:tiktok",
    linkedin: "mdi:linkedin",
    youtube: "mdi:youtube",
  };

  return iconMap[label?.toLowerCase()] || "hugeicons:link-02";
};

// Track link click
const trackClick = async (linkLabel) => {
  try {
    await $fetch("/api/track/click", {
      method: "POST",
      baseURL: useRuntimeConfig().public.apiUrl,
      body: {
        clickable_type: "App\\Models\\User",
        clickable_id: user.value.id,
        link_label: linkLabel,
      },
    });
  } catch (err) {
    console.error("Failed to track click:", err);
  }
};

// Save namecard
const saveNamecard = () => {
  const vcard = `BEGIN:VCARD
VERSION:3.0
FN:${user.value.name}
EMAIL:${user.value.email || ""}
URL:${window.location.href}
END:VCARD`;

  const blob = new Blob([vcard], { type: "text/vcard" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${user.value.username}.vcf`;
  link.click();
  URL.revokeObjectURL(url);
};

// Generate QR code when component is mounted on client side
onMounted(async () => {
  if (user.value) {
    await nextTick();
    await generateQRCode();
  }
});

// Re-generate QR code when user changes (e.g., route change)
watch(user, async (newUser) => {
  if (newUser && qrcodeCanvas.value) {
    await nextTick();
    await generateQRCode();
  }
});

// SEO
useHead({
  title: () => (user.value ? `${user.value.name} (@${user.value.username})` : "Profile"),
  meta: [
    {
      name: "description",
      content: () => user.value?.bio || "View profile",
    },
  ],
});
</script>
