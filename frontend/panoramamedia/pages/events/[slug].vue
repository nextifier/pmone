<template>
  <div v-if="event" id="event-page" class="pb-14 lg:pt-4 lg:pb-20">
    <div class="sm:container">
      <div class="grid grid-cols-1 gap-y-4 lg:gap-y-8">
        <div class="relative">
          <GallerySlider :event="event" class="lg:hidden" />
          <GalleryBentoGrid :event="event" class="hidden lg:grid" />

          <DialogResponsive
            v-model:open="isDialogOpen"
            :isResponsive="false"
            :overflowContent="true"
            :drawerCloseButton="false"
          >
            <template #trigger="{ open }">
              <button
                type="button"
                @click="open"
                class="absolute right-4 bottom-4 z-20 flex items-center gap-x-1 rounded-xl bg-white px-3 py-2 text-sm font-semibold tracking-tight text-black transition hover:bg-gray-200 active:scale-95"
              >
                <Icon
                  name="mingcute:dot-grid-fill"
                  class="sizes-5 flex-shrink-0"
                />
                <span>Show all</span>
              </button>
            </template>

            <template #default="{ data }">
              <div class="mx-auto w-full max-w-[1920px] px-1 pb-8">
                <div
                  class="columns-1 gap-1 space-y-1 sm:columns-2 xl:columns-3"
                >
                  <div
                    v-for="(item, index) in event.photos"
                    :key="index"
                    class="bg-muted break-inside-avoid"
                  >
                    <img
                      :src="`${item.src}`"
                      :alt="item.alt"
                      :width="item.w"
                      :height="item.h"
                      class="pointer-events-none h-full w-full rounded-none object-cover select-none"
                      sizes="400px"
                      loading="lazy"
                      format="webp"
                    />
                  </div>
                </div>
              </div>
            </template>
          </DialogResponsive>
        </div>

        <div
          class="flex items-center justify-between px-4 sm:px-0 lg:order-first"
        >
          <BackButton />

          <DialogShare :pageTitle="title" />
        </div>
      </div>

      <div class="mt-6 flex flex-col px-4 sm:px-0 lg:mt-10">
        <div
          class="grid grid-cols-1 items-start gap-x-8 gap-y-8 lg:grid-cols-12"
        >
          <div class="flex flex-col items-start gap-y-4 lg:col-span-8">
            <div class="flex flex-shrink-0 items-center gap-x-1.5">
              <IconCompleted
                v-if="event.status.toLowerCase() === 'completed'"
                class="size-5 text-green-500"
              />
              <IconUpcoming
                v-if="event.status.toLowerCase() === 'upcoming'"
                class="size-5 text-gray-400 dark:text-gray-500"
              />
              <span class="uppercase">{{ event.status }}</span>
            </div>

            <h1 class="section-title">{{ event.title }}</h1>

            <div class="flex flex-wrap items-center gap-1">
              <span
                v-for="(category, index) in event.categories"
                :key="index"
                class="bg-muted rounded-full px-3 py-2 tracking-tight text-nowrap"
                >{{ category }}</span
              >
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 lg:col-span-4">
            <template
              v-for="(item, index) in [
                { label: 'Date', value: event.date },
                { label: 'Venue', value: event.venue },
                { label: 'Total Participants', value: event.totalParticipants },
                { label: 'Client', value: event.client.name },
              ]"
              :key="index"
            >
              <div
                v-if="item.value"
                class="bg-muted flex flex-col items-start gap-y-1 rounded-xl p-6"
              >
                <span class="text-primary/70 text-sm tracking-tight">{{
                  item.label
                }}</span>
                <span
                  class="text-primary text-base font-semibold tracking-tighter sm:text-lg"
                  >{{ item.value }}</span
                >
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div class="container mt-16 lg:mt-24">
      <h2 class="section-title">Other Events</h2>
    </div>

    <EventSlider :events="otherEvents" class="mt-6" />
  </div>
</template>

<script setup>
const route = useRoute();
const events = useEventStore();
const event = events.getEventBySlug(route.params.slug);

const otherEvents = computed(() => {
  return events.events.filter((event) => event.slug !== route.params.slug);
});

const uiStore = useUiStore();
const isDialogOpen = computed({
  get() {
    return uiStore.isEventGalleryDialogOpen;
  },
  set(value) {
    if (value) {
      uiStore.openEventGalleryDialog();
    } else {
      uiStore.closeEventGalleryDialog();
    }
  },
});

const config = useRuntimeConfig();
const title = `${event.title}`;
const description = "";

usePageMeta("", {
  title: title,
  description: description,
});

const hideScrollbar = () => {
  document.querySelector("html").classList.add("hide-scrollbar");
};

const showScrollbar = () => {
  document.querySelector("html").classList.remove("hide-scrollbar");
};
</script>

<style>
@reference "@/assets/css/main.css";

.event-stat-box {
  @apply 3xl:px-8 3xl:py-10 @container flex aspect-square flex-col items-start justify-end gap-y-2 rounded-3xl px-4 py-6 sm:px-6 sm:py-8;

  & .stat-icon {
    @apply size-7 @[240px]:size-8;
  }

  & .stat-label {
    @apply tracking-wide uppercase;
  }

  & .stat-value {
    @apply text-xl font-bold tracking-tighter @[200px]:text-2xl @[240px]:text-3xl;
  }
}

#event-page .my-gallery {
  @apply grid grid-cols-3;

  & .gallery-thumbnail {
    @apply mx-0.5 my-0.5 aspect-square;

    & img {
      @apply h-full w-full bg-gray-100 object-cover dark:bg-gray-900;
    }
  }
}
</style>
