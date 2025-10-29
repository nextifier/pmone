<template>
  <div class="min-h-screen bg-black text-white">
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <Spinner class="mx-auto mb-4" />
        <p class="text-muted-foreground">Loading profile...</p>
      </div>
    </div>

    <div v-else-if="error" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <h1 class="text-2xl font-bold mb-2">Error</h1>
        <p class="text-muted-foreground">{{ error }}</p>
      </div>
    </div>

    <div v-else-if="user" class="mx-auto max-w-2xl pb-20">
      <!-- Cover Image -->
      <div class="relative h-48 overflow-hidden bg-gradient-to-br from-blue-600 to-blue-400">
        <img
          v-if="user.cover_image"
          :src="user.cover_image"
          :alt="`${user.name} cover`"
          class="size-full object-cover"
        />
      </div>

      <!-- Profile Section -->
      <div class="relative px-6">
        <!-- Profile Image -->
        <div class="squircle absolute -top-16 size-32 overflow-hidden border-4 border-black bg-gray-800">
          <img
            v-if="user.profile_image"
            :src="user.profile_image"
            :alt="user.name"
            class="size-full object-cover"
          />
          <div v-else class="flex size-full items-center justify-center bg-gradient-to-br from-gray-700 to-gray-800">
            <Icon name="hugeicons:user" class="size-16 text-gray-400" />
          </div>
        </div>

        <!-- Name and Username -->
        <div class="pt-20">
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold">{{ user.name }}</h1>
            <Icon name="mdi:verified" class="size-6 text-blue-500" />
          </div>
          <p class="text-muted-foreground mt-1">@{{ user.username }}</p>
        </div>

        <!-- Bio -->
        <p v-if="user.bio" class="mt-4 text-sm leading-relaxed">
          {{ user.bio }}
        </p>

        <!-- Social Media Icons -->
        <div v-if="socialLinks.length > 0" class="mt-6 flex justify-center gap-4">
          <a
            v-for="link in socialLinks"
            :key="link.url"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            @click="trackClick(link.label)"
            class="flex size-12 items-center justify-center rounded-full bg-gray-900 text-white transition hover:bg-gray-800"
          >
            <Icon :name="getSocialIcon(link.label)" class="size-5" />
          </a>
        </div>

        <!-- Custom Links -->
        <div v-if="customLinks.length > 0" class="mt-6 space-y-3">
          <a
            v-for="link in customLinks"
            :key="link.url"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            @click="trackClick(link.label)"
            class="block rounded-2xl bg-gray-900 px-6 py-4 text-center text-sm font-medium transition hover:bg-gray-800"
          >
            {{ link.label }}
          </a>
        </div>

        <!-- QR Code and Save Button -->
        <div class="mt-8 text-center">
          <div class="mx-auto mb-4 inline-block rounded-2xl bg-white p-4">
            <canvas ref="qrcodeCanvas" class="size-40"></canvas>
          </div>
          <button
            @click="saveNamecard"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 px-6 py-3 text-sm font-medium transition hover:bg-gray-800"
          >
            <Icon name="hugeicons:download-01" class="size-5" />
            Save namecard
          </button>
          <p class="mt-2 text-xs text-gray-500">pmone.id/{{ user.username }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import QRCode from "qrcode";

const route = useRoute();
const username = computed(() => route.params.username as string);

const user = ref(null);
const loading = ref(true);
const error = ref("");
const qrcodeCanvas = ref(null);

// Fetch user profile
const fetchProfile = async () => {
  try {
    loading.value = true;
    error.value = "";

    const response = await $fetch(`/api/${username.value}`, {
      baseURL: useRuntimeConfig().public.apiUrl,
    });

    // Check if response is a short link redirect
    if (response.data?.destination_url) {
      // Redirect to destination URL
      window.location.href = response.data.destination_url;
      return;
    }

    user.value = response.data;
  } catch (err: any) {
    console.error("Failed to fetch profile:", err);
    error.value = err.data?.message || "Failed to load profile";
  } finally {
    loading.value = false;
  }
};

// Generate QR code
const generateQRCode = async () => {
  if (qrcodeCanvas.value && user.value) {
    try {
      const url = `${window.location.origin}/${user.value.username}`;
      await QRCode.toCanvas(qrcodeCanvas.value, url, {
        width: 160,
        margin: 0,
        color: {
          dark: "#000000",
          light: "#FFFFFF",
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
const getSocialIcon = (label: string) => {
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
const trackClick = async (linkLabel: string) => {
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

// Lifecycle
onMounted(async () => {
  await fetchProfile();
  await nextTick();
  await generateQRCode();
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
