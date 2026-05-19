<script setup lang="ts">
import { cn } from "@/lib/utils";
import emblaCarouselVue from "embla-carousel-vue";
import { computed, onBeforeUnmount, onMounted, watch, type HTMLAttributes } from "vue";
import { isVideoItem, pickImageSrc, useLightbox } from "./useLightbox";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  itemClass?: HTMLAttributes["class"];
}>();

const state = useLightbox();
const { items, index, goTo, props: lightboxProps } = state;

const thumbnailKey = computed(() => lightboxProps.thumbnailKey || "sm");

const [thumbsRef, thumbsApi] = emblaCarouselVue({
  containScroll: "keepSnaps",
  dragFree: true,
  loop: false,
  align: "center",
});

onMounted(() => {
  if (thumbsApi.value) {
    state.thumbsApi.value = thumbsApi.value;
    thumbsApi.value.scrollTo(index.value, true);
  }
});

onBeforeUnmount(() => {
  if (state.thumbsApi.value === thumbsApi.value) {
    state.thumbsApi.value = null;
  }
});

watch(
  () => index.value,
  (i) => {
    thumbsApi.value?.scrollTo(i);
  }
);
</script>

<template>
  <div
    v-if="items.length > 1"
    data-slot="lightbox-thumbnails"
    role="tablist"
    aria-label="Gallery thumbnails"
    :class="cn('w-full', props.class)"
  >
    <div
      ref="thumbsRef"
      class="mx-auto max-w-full overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_6%,black_94%,transparent)]"
    >
      <div class="flex justify-center-safe gap-1 px-4 sm:px-10">
        <button
          v-for="(item, i) in items"
          :key="i"
          type="button"
          role="tab"
          :aria-selected="index === i"
          :aria-current="index === i ? 'true' : undefined"
          :aria-label="`Go to slide ${i + 1}`"
          :class="
            cn(
              'group relative h-14 w-20 shrink-0 cursor-pointer overflow-hidden rounded-md border-2 transition-[border-color,opacity] sm:h-16 sm:w-24',
              index === i
                ? 'border-white opacity-100'
                : 'border-transparent opacity-60 hover:opacity-90',
              props.itemClass
            )
          "
          @click="goTo(i)"
        >
          <img
            :src="pickImageSrc(item, thumbnailKey)"
            :alt="item.name || `Slide ${i + 1}`"
            class="size-full bg-black/40 object-cover"
            loading="lazy"
            decoding="async"
            draggable="false"
          />
          <span
            v-if="isVideoItem(item)"
            class="absolute inset-0 flex items-center justify-center bg-black/40 text-white"
          >
            <Icon name="lucide:play" class="size-4" />
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
