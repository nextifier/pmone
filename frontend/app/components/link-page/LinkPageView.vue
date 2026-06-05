<template>
  <div class="relative mx-auto flex min-h-dvh max-w-lg flex-col pt-2 pb-10 sm:pt-4">
    <!-- Title & Description -->
    <div class="flex items-start gap-x-2 px-4 pt-2">
      <div class="flex grow flex-col gap-y-1">
        <h1 class="text-primary text-2xl leading-[1.2]! font-medium tracking-tighter text-balance">
          {{ linkPage.title }}
        </h1>
        <p v-if="linkPage.description" class="text-body tracking-tight text-pretty">
          {{ linkPage.description }}
        </p>
      </div>
      <ColorModeToggle class="shrink-0" />
    </div>

    <!-- Banners -->
    <div v-if="visibleBanners.length" class="mt-3 px-4">
      <component
        :is="visibleBanners[0].url ? 'a' : 'div'"
        v-if="visibleBanners.length === 1"
        :href="visibleBanners[0].url || undefined"
        :target="visibleBanners[0].url ? '_blank' : undefined"
        :rel="visibleBanners[0].url ? 'noopener noreferrer' : undefined"
        class="block overflow-hidden rounded-xl"
        @click="visibleBanners[0].url && $emit('trackBannerClick', visibleBanners[0])"
      >
        <NuxtImg
          :src="visibleBanners[0].image?.md || visibleBanners[0].image?.url"
          :alt="visibleBanners[0].caption || linkPage.title"
          sizes="(max-width: 512px) 100vw, 512px"
          format="webp"
          loading="eager"
          class="aspect-video w-full object-cover"
        />
      </component>

      <Carousel
        v-else
        class="relative"
        :opts="{ loop: true, align: 'start' }"
        :plugins="[Autoplay({ delay: 4000, stopOnInteraction: false })]"
      >
        <CarouselContent>
          <CarouselItem
            v-for="(banner, index) in visibleBanners"
            :key="banner.id"
            class="basis-full"
          >
            <component
              :is="banner.url ? 'a' : 'div'"
              :href="banner.url || undefined"
              :target="banner.url ? '_blank' : undefined"
              :rel="banner.url ? 'noopener noreferrer' : undefined"
              class="block overflow-hidden rounded-xl"
              @click="banner.url && $emit('trackBannerClick', banner)"
            >
              <NuxtImg
                :src="banner.image?.md || banner.image?.url"
                :alt="banner.caption || linkPage.title"
                sizes="(max-width: 512px) 100vw, 512px"
                format="webp"
                :loading="index === 0 ? 'eager' : 'lazy'"
                class="aspect-video w-full object-cover"
              />
            </component>
          </CarouselItem>
        </CarouselContent>
        <div class="mt-2.5 flex justify-center gap-1.5">
          <CarouselDotButtons
            class="size-1.5 rounded-full transition-colors"
            active-class="bg-primary"
            inactive-class="bg-muted-foreground/30"
          />
        </div>
      </Carousel>
    </div>

    <!-- Links -->
    <div class="mt-auto grid grid-cols-1 gap-y-2 px-4 pt-6 sm:mt-0">
      <a
        v-for="item in activeItems"
        :key="item.id"
        :href="item.url"
        target="_blank"
        rel="noopener noreferrer"
        class="border-border flex items-center gap-3 rounded-xl border p-1 transition duration-300 ease-out hover:scale-105 active:scale-98"
        @click="$emit('trackClick', item)"
      >
        <div v-if="item.poster" class="w-16 shrink-0">
          <img
            :src="item.poster.sm || item.poster.url"
            :alt="item.label"
            class="w-full rounded-lg object-contain"
          />
        </div>
        <div class="flex grow flex-col gap-y-1" :class="{ 'py-2 pl-3': !item.poster }">
          <h2 class="text-base leading-tight! font-medium tracking-tighter">{{ item.label }}</h2>
          <p
            v-if="item.description"
            class="text-muted-foreground line-clamp-2 text-xs tracking-tight sm:text-sm"
          >
            {{ item.description }}
          </p>
        </div>
        <span class="bg-muted mr-1 flex size-9 shrink-0 items-center justify-center rounded-full">
          <Icon name="lucide:arrow-up-right" class="text-muted-foreground size-4 shrink-0" />
        </span>
      </a>
    </div>
  </div>
</template>

<script setup>
import Autoplay from "embla-carousel-autoplay";
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
import {
  Carousel,
  CarouselContent,
  CarouselDotButtons,
  CarouselItem,
} from "@/components/ui/carousel";

const props = defineProps({
  linkPage: { type: Object, required: true },
});

defineEmits(["trackClick", "trackBannerClick"]);

const activeItems = computed(() => {
  return (props.linkPage.items || []).filter((item) => item.is_active);
});

// Banners: render all on server, filter by schedule window on client (avoids hydration mismatch).
const isMounted = ref(false);
const now = ref(null);

onMounted(() => {
  isMounted.value = true;
  now.value = new Date();
});

const activeBanners = computed(() => (props.linkPage.banners || []).filter((b) => b.is_active));

function isWithinWindow(banner) {
  const t = now.value;
  if (!t) return true;
  const start = banner.starts_at ? new Date(banner.starts_at) : null;
  const end = banner.ends_at ? new Date(banner.ends_at) : null;
  if (start && t < start) return false;
  if (end && t > end) return false;
  return true;
}

const visibleBanners = computed(() =>
  isMounted.value ? activeBanners.value.filter(isWithinWindow) : activeBanners.value
);
</script>
