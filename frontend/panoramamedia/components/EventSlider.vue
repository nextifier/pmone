<template>
  <Carousel
    v-if="events?.length"
    v-slot="{ scrollPrev, scrollNext, canScrollPrev, canScrollNext }"
    class="focusable relative overflow-hidden"
    :opts="{
      loop: false,
      align: 'center',
      dragFree: false,
      skipSnaps: true,
    }"
    :plugins="[$wheelGesturesPlugin()]"
  >
    <CarouselContent class="carousel-mx-wider -ml-3 *:select-none">
      <CarouselItem
        v-for="(event, index) in events.slice(0, 10)"
        :key="index"
        class="carousel-item basis-[280px] pl-3 lg:basis-[320px]"
      >
        <EventCard :event="event" />
      </CarouselItem>
    </CarouselContent>

    <div class="mt-6 h-8">
      <div
        v-if="canScrollPrev || canScrollNext"
        class="container-wider flex h-full justify-end gap-2"
      >
        <button
          @click="scrollPrev"
          :disabled="!canScrollPrev"
          class="bg-muted hover:bg-border text-primary flex aspect-square h-full items-center justify-center rounded-md transition active:scale-95"
          aria-label="previous"
        >
          <Icon name="lucide:arrow-left" class="size-4" />
        </button>

        <button
          @click="scrollNext"
          :disabled="!canScrollNext"
          class="bg-muted hover:bg-border text-primary flex aspect-square h-full items-center justify-center rounded-md transition active:scale-95"
          aria-label="next"
        >
          <Icon name="lucide:arrow-right" class="size-4" />
        </button>

        <nuxt-link
          to="/events"
          class="text-primary hover:bg-primary hover:text-primary-foreground flex h-full items-center justify-center rounded-md border px-4 text-sm font-semibold tracking-tight transition active:scale-95"
        >
          <span>View all</span>
        </nuxt-link>
      </div>
    </div>
  </Carousel>
</template>

<script setup>
const props = defineProps({
  events: Array,
});
</script>
