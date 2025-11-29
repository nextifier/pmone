<template>
  <div
    v-if="event.photos?.length"
    class="relative aspect-[16/5] w-full grid-cols-4 grid-rows-2 gap-2 overflow-hidden rounded-2xl"
  >
    <button
      v-for="(item, index) in event.photos.slice(0, 5)"
      @click="openEventGalleryDialog"
      :key="index"
      class="group bg-muted relative z-10"
      :class="{
        'col-span-4 row-span-2': event.photos.length == 1,
        'col-span-2 row-span-2': event.photos.length == 2,
        'col-span-2 first-of-type:row-span-2': event.photos.length == 3,
        'first-of-type:col-span-2 first-of-type:row-span-2 last-of-type:col-span-2':
          event.photos.length == 4,
        'first-of-type:col-span-2 first-of-type:row-span-2':
          event.photos.length > 4,
      }"
    >
      <NuxtImg
        :src="`${item.src}`"
        :alt="item.alt"
        :width="item.w"
        :height="item.h"
        class="h-full w-full object-cover"
        sizes="800px"
        loading="lazy"
        format="webp"
      />

      <div
        class="absolute inset-0 bg-black/0 transition group-hover:bg-black/20"
      ></div>
    </button>
  </div>
</template>

<script setup>
const props = defineProps({
  event: Object,
});

const uiStore = useUiStore();
const openEventGalleryDialog = () => {
  uiStore.openEventGalleryDialog();
};
</script>
