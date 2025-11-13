<template>
  <div>
    <div v-if="loading" class="min-h-screen-offset grid place-items-center">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-base tracking-tight">Loading</span>
      </div>
    </div>

    <ErrorState v-else-if="error" :error="error" />

    <div
      v-else-if="profile"
      class="min-h-screen-offset mx-auto flex max-w-xl flex-col px-4 pt-4 pb-16"
    >
      <div class="relative -mx-3">
        <div
          class="aspect-[3/1] overflow-hidden rounded-xl"
          :style="{
            '--hue': Math.min((profile?.name?.length || 0) / 50, 1) * 360,
          }"
          :class="[
            'outline-primary/5 @container relative flex items-center justify-center outline -outline-offset-1 [--bg-chroma:0.16] [--bg-lightness:0.9] [--text-chroma:0.16] [--text-lightness:0.32] dark:[--bg-chroma:0.14] dark:[--bg-lightness:0.28] dark:[--text-chroma:0.16] dark:[--text-lightness:0.8]',
            !profile?.cover_image &&
              !profile?.profile_image &&
              'bg-[linear-gradient(135deg,oklch(var(--bg-lightness)_var(--bg-chroma)_var(--hue)),oklch(calc(var(--bg-lightness)*1.1)_calc(var(--bg-chroma)*1.5)_calc(var(--hue)+20)))]',
          ]"
        >
          <img
            v-if="profile.cover_image?.md"
            :src="profile.cover_image.md"
            :alt="`${profile.name} cover`"
            class="size-full object-cover"
            width="1500"
            height="500"
            loading="lazy"
          />

          <img
            v-else-if="profile?.profile_image?.sm"
            :src="profile.profile_image.sm"
            alt=""
            class="size-full scale-150 object-cover blur-[80px]"
            loading="lazy"
          />

          <img
            v-else
            src="/img/placeholder/placeholder-cover-image.jpg"
            alt=""
            class="size-full object-cover opacity-25 mix-blend-luminosity contrast-150"
            width="1500"
            height="500"
            loading="lazy"
          />
        </div>

        <BackButton v-if="showBackButton" v-slot="{ goBack }" :destination="backDestination">
          <button
            @click="goBack"
            class="absolute top-2.5 left-2.5 flex size-10 items-center justify-center rounded-full bg-white text-black shadow backdrop-blur-sm transition hover:bg-white/80 active:scale-98"
          >
            <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          </button>
        </BackButton>
      </div>

      <div class="-mt-12 flex grow flex-col justify-between gap-y-8 lg:-mt-16">
        <div class="flex flex-col gap-y-6">
          <div class="flex flex-col items-start gap-y-2">
            <div class="relative flex w-full items-end justify-between gap-2">
              <div class="relative isolate">
                <Avatar
                  :model="profile"
                  size="sm"
                  rounded="rounded-full"
                  class="ring-background size-24 ring-4 lg:size-32"
                />

                <span
                  class="absolute top-1/2 right-0 z-[-1] size-8 translate-x-[calc(100%+0px)] -translate-y-full rounded-bl-[16px] bg-transparent shadow-[-16px_16px_0_var(--color-background)]"
                />
              </div>

              <div v-if="canEdit" class="flex flex-wrap justify-end gap-2">
                <NuxtLink
                  :to="editUrl"
                  class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight backdrop-blur-sm transition active:scale-98"
                >
                  <Icon name="hugeicons:pencil-edit-02" class="size-4.5 shrink-0" />
                  <span>Edit Profile</span>
                </NuxtLink>

                <NuxtLink
                  :to="analyticsUrl"
                  class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight backdrop-blur-sm transition active:scale-98"
                >
                  <Icon name="hugeicons:analytics-01" class="size-4.5 shrink-0" />
                  <span>Analytics</span>
                </NuxtLink>
              </div>
            </div>

            <div class="space-y-0.5">
              <div class="relative">
                <h1 class="line-clamp-1 text-xl font-semibold tracking-tighter">
                  {{ profile.name }}
                </h1>
                <Icon
                  v-if="
                    profileType === 'user' &&
                    profile?.roles?.some((role) => ['master', 'admin', 'staff'].includes(role))
                  "
                  name="mdi:verified"
                  class="text-info absolute top-1/2 right-0 size-4.5 translate-x-[calc(100%+4px)] -translate-y-1/2"
                />
              </div>

              <p class="text-muted-foreground text-sm tracking-tight">@{{ profile.username }}</p>
            </div>

            <p v-if="profile.title" class="text-primary text-base font-medium tracking-tight">
              {{ profile.title }}
            </p>

            <p v-if="profile.bio" class="text-sm leading-relaxed tracking-tight">
              {{ profile.bio }}
            </p>

            <!-- Contact Buttons -->
            <div v-if="hasContactMethods" class="mt-1.5 flex flex-wrap gap-2">
              <!-- WhatsApp for User (single phone) -->
              <NuxtLink
                v-if="profileType === 'user' && profile.phone"
                :to="`https://wa.me/${profile.phone.replace(/\D/g, '')}`"
                target="_blank"
                @click="$emit('track-click', 'WhatsApp')"
                @contextmenu.prevent
                class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-semibold tracking-tight transition active:scale-98"
              >
                <Icon name="hugeicons:whatsapp" class="size-4.5 shrink-0" />
                <span>WhatsApp</span>
              </NuxtLink>

              <!-- WhatsApp for Project (multiple phones) -->
              <template v-if="profileType === 'project' && phoneNumbers.length > 0">
                <NuxtLink
                  v-for="phone in phoneNumbers"
                  :key="phone.number"
                  :to="`https://wa.me/${phone.number.replace(/\D/g, '')}`"
                  target="_blank"
                  @click="$emit('track-click', phone.label || 'WhatsApp')"
                  @contextmenu.prevent
                  class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-semibold tracking-tight transition active:scale-98"
                >
                  <Icon name="hugeicons:whatsapp" class="size-4.5 shrink-0" />
                  <span>{{ phone.label || "WhatsApp" }}</span>
                </NuxtLink>
              </template>

              <!-- Email -->
              <NuxtLink
                v-if="profile.email"
                :to="`mailto:${profile.email}`"
                @click="$emit('track-click', 'Email')"
                @contextmenu.prevent
                class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-semibold tracking-tight transition active:scale-98"
              >
                <Icon name="hugeicons:mail-02" class="size-4.5 shrink-0" />
                <span>Email</span>
              </NuxtLink>
            </div>
          </div>

          <div v-if="socialLinks.length > 0" class="flex flex-wrap gap-2">
            <NuxtLink
              v-for="link in socialLinks"
              :key="link.url || link.id"
              :to="link.url || '#'"
              :target="link.url?.startsWith('http') ? '_blank' : ''"
              @click="$emit('track-click', link.label)"
              @contextmenu.prevent
              class="bg-muted text-foreground hover:bg-border flex size-12 shrink-0 items-center justify-center rounded-full transition active:scale-98"
              v-tippy="link.label"
            >
              <Icon :name="getSocialIcon(link.label)" class="size-5" />
            </NuxtLink>
          </div>

          <div v-if="customLinks.length > 0" class="flex flex-col gap-y-3">
            <NuxtLink
              v-for="link in customLinks"
              :key="link.url || link.id"
              :to="link.url || '#'"
              :target="link.url?.startsWith('http') ? '_blank' : ''"
              @click="$emit('track-click', link.label)"
              @contextmenu.prevent
              class="bg-muted text-foreground hover:bg-border flex h-9 items-center justify-center gap-1.5 rounded-lg px-4 text-sm font-medium tracking-tight transition active:scale-98"
            >
              {{ link.label }}
            </NuxtLink>
          </div>
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
                {{ qrCodeText }}
              </p>
            </div>
          </ClientOnly>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  profile: {
    type: Object,
    default: null,
  },
  profileType: {
    type: String,
    required: true,
    validator: (value) => ["user", "project"].includes(value),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: Object,
    default: null,
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
  showBackButton: {
    type: Boolean,
    default: false,
  },
  backDestination: {
    type: String,
    default: "/",
  },
});

defineEmits(["track-click"]);

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

const phoneNumbers = computed(() => {
  if (props.profileType === "project" && Array.isArray(props.profile?.phone)) {
    return props.profile.phone;
  }
  return [];
});

const hasContactMethods = computed(() => {
  if (props.profileType === "user") {
    return props.profile?.phone || props.profile?.email;
  }
  if (props.profileType === "project") {
    return phoneNumbers.value.length > 0 || props.profile?.email;
  }
  return false;
});

const editUrl = computed(() => {
  const base = props.profileType === "user" ? "/users" : "/projects";
  return `${base}/${props.profile?.username}/edit`;
});

const analyticsUrl = computed(() => {
  const base = props.profileType === "user" ? "/users" : "/projects";
  return `${base}/${props.profile?.username}/analytics`;
});

const qrCodeUrl = computed(() => {
  if (!props.profile?.username) return "";
  return `${window.location.origin}/${props.profile.username}`;
});

const qrCodeText = computed(() => {
  if (!props.profile?.username) return "";
  const siteUrl = useRuntimeConfig().public.siteUrl.replace(/^https?:\/\//, "");
  return `${siteUrl}/${props.profile.username}`;
});

const { socialLinks, customLinks } = computed(() => {
  const links = props.profile?.links || [];
  const social = [];
  const custom = [];

  for (const link of links) {
    if (!link?.label) continue;

    const labelLower = link.label.toLowerCase();
    // Skip Email and WhatsApp as they are displayed in Contact Buttons section
    if (labelLower === "email" || labelLower === "whatsapp" || labelLower.startsWith("whatsapp ")) {
      continue;
    }

    if (SOCIAL_LABELS.includes(labelLower)) {
      social.push(link);
    } else {
      custom.push(link);
    }
  }

  return { socialLinks: social, customLinks: custom };
}).value;

const getSocialIcon = (label) => SOCIAL_ICON_MAP[label?.toLowerCase()] || "hugeicons:link-02";
</script>
