<template>
  <Carousel
    v-if="event.photos?.length"
    v-slot="{ scrollPrev, scrollNext, canScrollPrev, canScrollNext }"
    class="focus-outline group relative isolate overflow-hidden"
    :opts="{
      loop: true,
      align: 'start',
      dragFree: false,
    }"
    :plugins="[$wheelGesturesPlugin()]"
  >
    <CarouselContent class="relative z-10 -ml-0 *:select-none">
      <CarouselItem
        v-for="(item, index) in event.photos.slice(0, 10)"
        :key="index"
        class="carousel-item pl-0"
      >
        <NuxtLink
          :to="`/events/${event.slug}`"
          class="bg-muted block aspect-[3/2] w-full overflow-hidden"
        >
          <NuxtImg
            :src="`${item.src}`"
            alt=""
            :width="item.w"
            :height="item.h"
            class="h-full w-full object-cover"
            sizes="400px"
            format="webp"
            loading="lazy"
          />
        </NuxtLink>
      </CarouselItem>
    </CarouselContent>

    <div class="absolute bottom-2.5 left-1/2 z-20 -translate-x-1/2">
      <div class="flex items-center justify-center gap-1.5">
        <CarouselDotButtons
          class="size-1.5 rounded-full"
          activeClass="bg-white"
          inactiveClass="bg-white/50"
        />
      </div>
    </div>

    <button
      @click="scrollPrev"
      :disabled="!canScrollPrev"
      class="absolute top-1/2 left-0 z-20 flex aspect-square h-8 -translate-x-full -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-white text-black transition group-hover:translate-x-2 active:scale-95"
      aria-label="previous"
    >
      <IconChevronLeft class="h-3.5" />
    </button>

    <button
      @click="scrollNext"
      :disabled="!canScrollNext"
      class="absolute top-1/2 right-0 z-20 flex aspect-square h-8 translate-x-full -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-white text-black transition group-hover:-translate-x-2 active:scale-95"
      aria-label="next"
    >
      <IconChevronRight class="h-3.5" />
    </button>
  </Carousel>
</template>

<script setup>
const props = defineProps({
  event: Object,
});
</script>
