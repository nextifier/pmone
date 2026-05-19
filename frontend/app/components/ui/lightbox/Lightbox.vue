<script setup lang="ts">
import { cn } from "@/lib/utils";
import { computed } from "vue";
import LightboxBody from "./LightboxBody.vue";
import LightboxContent from "./LightboxContent.vue";
import LightboxRoot from "./LightboxRoot.vue";
import type { LightboxEmits, LightboxImageSource, LightboxProps } from "./interface";
import { isVideoItem, pickImageSrc } from "./useLightbox";

const props = withDefaults(defineProps<LightboxProps>(), {
  loop: true,
  showThumbnails: true,
  showCounter: true,
  showDownload: true,
  showClose: true,
  showNavButtons: true,
  showCaption: true,
  showFullscreen: false,
  showShare: false,
  closeOnBackdropClick: true,
  keyboard: true,
  autoplay: false,
  zoomable: true,
  swipeToClose: false,
  thumbnailKey: "sm",
  fullKey: "lg",
  gridClass: "grid grid-cols-3 gap-1 sm:grid-cols-4 lg:grid-cols-6",
  rounded: "rounded",
  firstSpansLarge: false,
  limit: null,
});

const emits = defineEmits<LightboxEmits>();

const visibleItems = computed(() => {
  if (props.limit && props.limit > 0) {
    return props.items.slice(0, props.limit);
  }
  return props.items;
});

const remainingCount = computed(() => {
  if (!props.limit || props.limit <= 0) {
    return 0;
  }
  return Math.max(0, props.items.length - props.limit);
});

function thumbButtonClass(i: number, total: number): string {
  const isFirstLarge = props.firstSpansLarge && i === 0 && total > 1;
  return cn(
    "group relative block overflow-hidden cursor-zoom-in bg-muted",
    props.rounded,
    isFirstLarge ? "col-span-2 row-span-2 aspect-auto" : "aspect-square",
    props.itemClass,
  );
}

function altFor(item: LightboxImageSource, i: number): string {
  return item.alt || item.name || props.alt || `Image ${i + 1}`;
}
</script>

<template>
  <LightboxRoot
    v-bind="props"
    @update:open="emits('update:open', $event)"
    @update:index="emits('update:index', $event)"
    @change="emits('change', $event)"
    @download="emits('download', $event)"
  >
    <template #default="{ openAt, open: openLightbox }">
      <slot name="trigger" :open="openLightbox" :open-at="openAt" :items="props.items">
        <div v-if="visibleItems.length" :class="cn(props.gridClass, props.class)">
          <button
            v-for="(item, i) in visibleItems"
            :key="i"
            type="button"
            :class="thumbButtonClass(i, visibleItems.length)"
            @click="openAt(i)"
          >
            <img
              :src="pickImageSrc(item, props.thumbnailKey)"
              :alt="altFor(item as LightboxImageSource, i)"
              class="size-full object-cover"
              loading="lazy"
              decoding="async"
              draggable="false"
            />
            <span
              v-if="isVideoItem(item)"
              class="absolute inset-0 flex items-center justify-center bg-black/35 text-white"
            >
              <Icon name="lucide:play" class="size-6 drop-shadow" />
            </span>
            <span
              v-if="i === visibleItems.length - 1 && remainingCount > 0"
              class="absolute inset-0 flex items-center justify-center bg-black/55 text-base font-semibold tracking-tight text-white"
            >
              +{{ remainingCount }}
            </span>
          </button>
        </div>
      </slot>

      <LightboxContent>
        <LightboxBody
          :show-close="props.showClose"
          :show-counter="props.showCounter"
          :show-download="props.showDownload"
          :show-nav-buttons="props.showNavButtons"
          :show-thumbnails="props.showThumbnails"
          :show-caption="props.showCaption"
          :show-fullscreen="props.showFullscreen"
          :show-share="props.showShare"
        >
          <template v-if="$slots.counter" #counter="counterScope">
            <slot name="counter" v-bind="counterScope" />
          </template>
          <template v-if="$slots.actions" #actions="actionsScope">
            <slot name="actions" v-bind="actionsScope" />
          </template>
          <template v-if="$slots.previous" #previous="prevScope">
            <slot name="previous" v-bind="prevScope" />
          </template>
          <template v-if="$slots.next" #next="nextScope">
            <slot name="next" v-bind="nextScope" />
          </template>
          <template v-if="$slots.caption" #caption="captionScope">
            <slot name="caption" v-bind="captionScope" />
          </template>
          <template v-if="$slots.thumbnails" #thumbnails="thumbScope">
            <slot name="thumbnails" v-bind="thumbScope" />
          </template>
        </LightboxBody>
      </LightboxContent>
    </template>
  </LightboxRoot>
</template>
