<template>
  <section id="cta">
    <div class="sm:container">
      <div
        class="flex flex-col gap-x-6 gap-y-16 lg:flex-row lg:justify-between xl:gap-x-10"
      >
        <div class="flex flex-col items-center gap-y-8 lg:items-start">
          <div
            class="bg-muted aspect-square w-full overflow-hidden sm:aspect-auto sm:rounded-2xl"
          >
            <NuxtImg
              src="/img/cta-img.jpg"
              alt=""
              class="pointer-events-none size-full object-cover select-none"
              sizes="100vw lg:1600px"
              width="1600"
              height="897"
              loading="lazy"
              format="webp"
            />
          </div>

          <div
            class="flex flex-col items-center px-4 text-center sm:px-0 lg:items-start lg:text-left"
          >
            <h2
              class="text-primary text-4xl !leading-[1.2] font-semibold tracking-tighter text-balance sm:text-5xl lg:text-6xl xl:text-8xl"
            >
              Let's Plan Your Team's Most
              <span class="text-gradient-accent font-serif"
                >Impactful Outing</span
              >
            </h2>
            <p
              class="text-primary/70 mt-3 max-w-2xl text-base font-medium tracking-tight text-balance sm:text-lg"
            >
              Tell us what you're dreaming of, and we'll handle all the details
              to create a perfectly tailored and impactful outing experience for
              your employees.
            </p>

            <button
              @click="openInquiryDialog"
              class="bg-primary text-primary-foreground hover:bg-primary/80 mt-6 flex items-center gap-x-1 rounded-xl px-4 py-3 font-semibold tracking-tighter backdrop-blur-md transition active:scale-95 sm:px-6 sm:py-4"
              v-ripple
            >
              <span>Discuss Your Next Outing</span>
              <Icon name="hugeicons:arrow-up-right-01" class="size-4" />
            </button>
          </div>
        </div>

        <div
          class="no-scrollbar flex justify-center-safe gap-x-4 gap-y-12 overflow-auto px-4 pb-4 lg:max-w-[240px] lg:flex-col lg:justify-start lg:overflow-visible lg:p-0"
        >
          <div
            v-for="(banner, index) in banners"
            :key="index"
            class="flex w-[240px] shrink-0 flex-col items-start gap-2 lg:w-full"
          >
            <nuxt-link
              :to="banner.cta.link"
              :target="banner.cta.openInNewTab ? '_blank' : ''"
              class="lg:shadow-wrapper rounded-xl transition duration-500 sm:rounded-2xl lg:hover:rotate-6"
            >
              <NuxtImg
                v-if="banner.image"
                :src="banner.image"
                :alt="banner.title"
                class="bg-muted border-border size-full rounded-lg border object-cover select-none sm:rounded-xl lg:shadow-md"
                sizes="240px"
                width="1080"
                height="1350"
                loading="lazy"
                format="webp"
              />
            </nuxt-link>

            <span
              v-if="banner.subtitle"
              class="mt-2 text-sm font-semibold tracking-tighter"
              :style="{
                color: banner.accentColor,
              }"
              >{{ banner.subtitle }}</span
            >

            <h6
              v-if="banner.title"
              class="text-primary text-base !leading-[1.3] font-semibold tracking-tighter sm:text-lg"
            >
              {{ banner.title }}
            </h6>

            <p
              v-if="banner.description"
              class="text-primary/70 text-sm tracking-tight"
            >
              {{ banner.description }}
            </p>

            <nuxt-link
              v-if="banner.cta"
              :to="banner.cta.link"
              :target="banner.cta.openInNewTab ? '_blank' : ''"
              class="bg-border/60 hover:bg-border/80 mt-1 flex items-center justify-center gap-x-1 rounded-full py-2 pr-2 pl-3 text-sm font-semibold tracking-tight transition active:scale-95"
              v-ripple
            >
              <span>{{ banner.cta.label }}</span>
              <Icon name="hugeicons:arrow-up-right-01" class="size-4" />
            </nuxt-link>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const uiStore = useUiStore();
const openInquiryDialog = () => {
  uiStore.openInquiryDialog();
};

const banners = [
  {
    image: "/img/banners/campx-poster.jpg",
    subtitle: "CampX",
    title: "Panorama Events' Top Outing Pick!",
    description:
      "Stuck for outing ideas? Visit CampX, recommended by Panorama Events! With fun activities and stunning, refreshing nature, it's perfect for your team.",
    accentColor: "#22C55E",
    cta: {
      label: "Explore CampX",
      link: "https://campx.id",
      openInNewTab: true,
    },
  },
  {
    image: "/img/banners/indooutingexpo-2025-poster.jpg",
    subtitle: "Indonesia Outing Expo",
    title: "Need Fun Travel Ideas & Promos?",
    description:
      "Find fresh inspiration and awesome travel deals for your team at Indonesia Outing Expo 2025! Don't miss out. (JICC, 14-16 Nov)",
    accentColor: "#38BDF8",
    cta: {
      label: "Explore IOE 2025",
      link: "https://indooutingexpo.co.id",
      openInNewTab: true,
    },
  },
];
</script>
