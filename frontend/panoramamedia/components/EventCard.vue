<template>
  <div>
    <div class="relative">
      <nuxt-link
        :to="`/events/${event.slug}`"
        class="bg-muted block aspect-[4/5] overflow-hidden rounded-3xl"
      >
        <NuxtImg
          v-if="event.photos[0]"
          :src="event.photos[0]?.src"
          :alt="event.title"
          class="pointer-events-none h-full w-full object-cover select-none"
          width="1080"
          height="1350"
          sizes="400px"
          loading="lazy"
        />
      </nuxt-link>

      <InvertedBorderRadius v-if="event.client.logo" position="bottom-right">
        <span
          class="group bg-background border-border text-primary flex size-16 items-center justify-center overflow-hidden border transition hover:bg-white"
        >
          <NuxtImg
            :src="event.client.logo"
            :alt="event.client.name"
            class="w-[90%] object-contain transition select-none hover:grayscale-0 dark:brightness-90 dark:contrast-200 dark:grayscale dark:invert-[75%] dark:hover:bg-white dark:hover:filter-none"
            sizes="100px"
            loading="lazy"
            :format="event.client.isVector ? '' : 'webp'"
          />
        </span>
      </InvertedBorderRadius>
    </div>

    <div class="relative mt-4 flex flex-col">
      <div class="flex flex-wrap items-center gap-2">
        <span
          v-for="(category, index) in event.categories.slice(0, 2)"
          :key="index"
          class="bg-muted rounded-full px-2.5 py-1.5 text-sm tracking-tight text-nowrap"
          >{{ category }}</span
        >
      </div>

      <nuxt-link
        :to="`/events/${event.slug}`"
        class="text-primary mt-2 line-clamp-3 text-lg font-semibold tracking-tighter"
        >{{ event.title }}</nuxt-link
      >

      <div class="mt-3 flex flex-col gap-y-3">
        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:calendar-03" class="size-4 flex-shrink-0" />
          <span class="text-sm tracking-tight">{{ event.date }}</span>
        </div>

        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:location-01" class="size-4 flex-shrink-0" />
          <span class="text-sm tracking-tight">{{ event.venue }}</span>
        </div>
      </div>

      <nuxt-link
        :to="`/events/${event.slug}`"
        class="bg-muted text-primary hover:bg-border mt-3 flex items-center gap-x-1.5 self-start rounded-lg px-3 py-2 text-sm font-medium tracking-tight transition active:scale-95"
      >
        <span>View details</span>
        <Icon name="hugeicons:arrow-right-02" class="size-4" />
      </nuxt-link>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  event: Object,
});
</script>
