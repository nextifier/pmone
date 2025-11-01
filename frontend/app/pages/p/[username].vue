<template>
  <div class="min-h-screen bg-black text-white">
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <Spinner class="mx-auto mb-4" />
        <p class="text-muted-foreground">Loading profile...</p>
      </div>
    </div>

    <div
      v-else-if="error"
      class="min-h-screen flex flex-col items-center justify-center overflow-hidden px-6"
    >
      <div class="container flex flex-col items-center justify-center gap-y-3 text-center">
        <span v-if="error.statusCode" class="text-sm text-muted-foreground">
          {{ error.statusCode }}
        </span>

        <h1
          v-if="error.statusMessage"
          class="text-primary w-full text-4xl font-bold tracking-tighter"
        >
          {{ error.statusMessage }}
        </h1>

        <p v-if="error.message" class="mx-auto mt-1 max-w-2xl text-balance text-muted-foreground">
          {{
            error.statusCode === 404
              ? "We couldn't find the project you're looking for. It might have moved, been renamed, or maybe it never existed in the first place."
              : error.message
          }}
        </p>

        <NuxtLink
          to="/"
          class="bg-primary text-primary-foreground hover:bg-primary/80 mt-4 flex items-center gap-x-2 rounded-xl px-4 py-3 font-medium tracking-tight transition"
        >
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          <span>Back to home</span>
        </NuxtLink>
      </div>
    </div>

    <div v-else-if="project" class="mx-auto max-w-2xl pb-20">
      <!-- Cover Image -->
      <div class="relative h-48 overflow-hidden bg-gradient-to-br from-orange-600 to-orange-400">
        <img
          v-if="project.cover_image"
          :src="project.cover_image"
          :alt="`${project.name} cover`"
          class="size-full object-cover"
        />
      </div>

      <!-- Profile Section -->
      <div class="relative px-6">
        <!-- Profile Image -->
        <div class="squircle absolute -top-16 size-32 overflow-hidden border-4 border-black bg-gray-800">
          <img
            v-if="project.profile_image"
            :src="project.profile_image"
            :alt="project.name"
            class="size-full object-cover"
          />
          <div v-else class="flex size-full items-center justify-center bg-gradient-to-br from-gray-700 to-gray-800">
            <Icon name="hugeicons:user" class="size-16 text-gray-400" />
          </div>
        </div>

        <!-- Name and Username -->
        <div class="pt-20">
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold">{{ project.name }}</h1>
            <Icon name="mdi:verified" class="size-6 text-blue-500" />
          </div>
          <p class="text-muted-foreground mt-1">@{{ project.username }}</p>
        </div>

        <!-- Bio -->
        <p v-if="project.bio" class="mt-4 text-sm leading-relaxed">
          {{ project.bio }}
        </p>

        <!-- Contact Buttons -->
        <div class="mt-6 grid grid-cols-2 gap-3">
          <!-- WhatsApp Button -->
          <a
            v-if="whatsappPhone"
            :href="whatsappLink"
            target="_blank"
            rel="noopener noreferrer"
            @click="trackClick('whatsapp')"
            class="flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-medium text-black transition hover:bg-gray-100"
          >
            <Icon name="mdi:whatsapp" class="size-5" />
            WhatsApp
          </a>

          <!-- Email Button -->
          <a
            v-if="project.email"
            :href="`mailto:${project.email}`"
            @click="trackClick('email')"
            class="flex items-center justify-center gap-2 rounded-xl bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-800"
          >
            <Icon name="hugeicons:mail-01" class="size-5" />
            Email
          </a>
        </div>

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

        <!-- Company Info -->
        <div v-if="project.members && project.members.length > 0" class="mt-8">
          <div class="flex items-start gap-4 rounded-2xl bg-gray-900 p-6">
            <div class="flex-shrink-0">
              <img
                src="https://via.placeholder.com/60"
                alt="Company logo"
                class="size-15 rounded-lg object-cover"
              />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-semibold">PT Panorama Media</div>
              <div class="mt-1 text-xs leading-relaxed text-gray-400">
                Jl. Tanjung Selor No 17A, RT.11/<br />
                RW.6, Cideng, Kec. Gambir,<br />
                Jakarta Pusat, 10150
              </div>
            </div>
          </div>
        </div>

        <!-- QR Code and Save Button -->
        <div class="mt-8 text-center">
          <ClientOnly>
            <div class="mx-auto mb-4 inline-block rounded-2xl bg-white p-4">
              <canvas ref="qrcodeCanvas" class="size-40"></canvas>
            </div>
          </ClientOnly>
          <button
            @click="saveNamecard"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 px-6 py-3 text-sm font-medium transition hover:bg-gray-800"
          >
            <Icon name="hugeicons:download-01" class="size-5" />
            Save namecard
          </button>
          <p class="mt-2 text-xs text-gray-500">pmone.id/{{ project.username }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import QRCode from "qrcode";

const route = useRoute();
const username = computed(() => route.params.username);

const project = ref(null);
const loading = ref(true);
const error = ref(null);
const qrcodeCanvas = ref(null);

// Fetch project profile
const fetchProfile = async () => {
  try {
    loading.value = true;
    error.value = null;

    const response = await $fetch(`/api/p/${username.value}`, {
      baseURL: useRuntimeConfig().public.apiUrl,
    });

    project.value = response.data;
  } catch (err) {
    console.error("Failed to fetch profile:", err);

    // Handle error response
    if (err.data) {
      error.value = {
        statusCode: err.statusCode || err.status || 500,
        statusMessage: err.data?.message || err.statusMessage || "Error",
        message: err.data?.message || err.message || "Failed to load profile",
      };
    } else {
      error.value = {
        statusCode: 500,
        statusMessage: "Error",
        message: err.message || "Failed to load profile",
      };
    }
  } finally {
    loading.value = false;
  }
};

// Generate QR code
const generateQRCode = async () => {
  if (!process.client) {
    console.log("QR: Not on client side, skipping");
    return;
  }

  if (!qrcodeCanvas.value) {
    console.log("QR: Canvas ref not available yet");
    return;
  }

  if (!project.value) {
    console.log("QR: Project data not available yet");
    return;
  }

  try {
    const url = `${window.location.origin}/p/${project.value.username}`;
    console.log("QR: Generating QR code for:", url);

    await QRCode.toCanvas(qrcodeCanvas.value, url, {
      width: 160,
      margin: 0,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    console.log("QR: QR code generated successfully");
  } catch (err) {
    console.error("QR: Failed to generate QR code:", err);
  }
};

// Computed properties
const whatsappPhone = computed(() => {
  if (!project.value?.phone || !Array.isArray(project.value.phone)) {
    return null;
  }

  const whatsapp = project.value.phone.find(
    (p) => p.label?.toLowerCase() === "whatsapp" || p.label?.toLowerCase() === "sales"
  );
  return whatsapp?.number || project.value.phone[0]?.number || null;
});

const whatsappLink = computed(() => {
  if (!whatsappPhone.value) {
    return "#";
  }
  const cleanNumber = whatsappPhone.value.replace(/\D/g, "");
  return `https://wa.me/${cleanNumber}`;
});

const socialLinks = computed(() => {
  if (!project.value?.links) {
    return [];
  }

  const socialLabels = ["website", "instagram", "facebook", "x", "tiktok", "linkedin", "youtube"];
  return project.value.links.filter((link) =>
    socialLabels.includes(link.label?.toLowerCase())
  );
});

const customLinks = computed(() => {
  if (!project.value?.links) {
    return [];
  }

  const socialLabels = ["website", "instagram", "facebook", "x", "tiktok", "linkedin", "youtube"];
  return project.value.links.filter(
    (link) => !socialLabels.includes(link.label?.toLowerCase())
  );
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
        clickable_type: "App\\Models\\Project",
        clickable_id: project.value.id,
        link_label: linkLabel,
      },
    });
  } catch (err) {
    console.error("Failed to track click:", err);
  }
};

// Save namecard
const saveNamecard = () => {
  if (!process.client) {
    return;
  }

  const vcard = `BEGIN:VCARD
VERSION:3.0
FN:${project.value.name}
EMAIL:${project.value.email || ""}
TEL:${whatsappPhone.value || ""}
URL:${window.location.href}
END:VCARD`;

  const blob = new Blob([vcard], { type: "text/vcard" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${project.value.username}.vcf`;
  link.click();
  URL.revokeObjectURL(url);
};

// Lifecycle
onMounted(async () => {
  await fetchProfile();
});

// Watch for project data to be available and generate QR code
watch(() => project.value, (newProject) => {
  if (newProject && process.client) {
    // Use setTimeout to ensure canvas is rendered in DOM
    setTimeout(() => {
      generateQRCode();
    }, 100);
  }
});

// SEO
useHead({
  title: () => (project.value ? `${project.value.name} (@${project.value.username})` : "Profile"),
  meta: [
    {
      name: "description",
      content: () => project.value?.bio || "View profile",
    },
  ],
});
</script>
