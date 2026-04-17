<template>
  <div
    ref="sceneRef"
    :role="isToggleable ? 'button' : 'region'"
    aria-roledescription="carousel"
    :aria-label="
      isToggleable ? `${ariaLabel}, ${isPlaying ? 'click to pause' : 'click to resume'}` : ariaLabel
    "
    :aria-pressed="isToggleable ? !isPlaying : undefined"
    :aria-live="isPlaying ? 'off' : 'polite'"
    :tabindex="isToggleable ? 0 : undefined"
    :class="
      cn(
        'relative isolate grid [min-height:calc(var(--c3d-w)*1.5)] outline-none [perspective:var(--c3d-perspective)]',
        clipOverflow ? 'overflow-hidden' : 'overflow-visible',
        fadeEdges &&
          '[-webkit-mask-image:linear-gradient(90deg,transparent_0%,#000_20%,#000_80%,transparent_100%)] [mask-image:linear-gradient(90deg,transparent_0%,#000_20%,#000_80%,transparent_100%)]',
        isToggleable && 'focus-visible:ring-ring cursor-pointer focus-visible:ring-2',
        props.class,
      )
    "
    :style="sceneStyle"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
    @click="onSceneClick"
    @keydown="onSceneKeydown"
  >
    <template v-if="count === 0">
      <slot name="empty" />
    </template>

    <template v-else-if="count === 1">
      <!-- Single item: no ring, just center it -->
      <div
        :class="
          cn(
            '[grid-area:1/1] place-self-center overflow-hidden backface-hidden [aspect-ratio:var(--c3d-aspect)] [border-radius:var(--c3d-radius)] [transform:scale(var(--c3d-card-scale,1))] [transition:transform_0.35s_ease] [width:var(--c3d-w)] hover:z-[2] hover:[--c3d-card-scale:var(--c3d-hover-scale,1)] motion-reduce:transition-none',
            interactive &&
              'focus-visible:ring-ring cursor-pointer outline-none focus-visible:ring-2',
            cardClass,
          )
        "
        :role="interactive ? 'button' : undefined"
        :tabindex="interactive ? 0 : undefined"
        data-c3d-card="0"
        @click="onCardClick($event, items[0]!, 0)"
        @keydown="interactive && onCardKeydown($event, items[0]!, 0)"
      >
        <slot name="item" :item="items[0]!" :index="0">
          <div
            v-if="imageErrors.has(0)"
            role="img"
            :aria-label="items[0]?.alt || 'Image failed to load'"
            class="bg-muted text-muted-foreground flex h-full w-full items-center justify-center"
          >
            <Icon name="lucide:image-off" class="size-6" aria-hidden="true" />
          </div>
          <img
            v-else
            class="block h-full w-full object-cover"
            :src="items[0]?.src"
            :alt="items[0]?.alt ?? ''"
            :loading="imageLoading"
            fetchpriority="high"
            @error="onImageError(0)"
          />
        </slot>
      </div>
    </template>

    <template v-else>
      <div
        class="pointer-events-none grid place-self-center [transform-style:preserve-3d] [transform:rotateX(var(--c3d-tilt))]"
      >
        <div
          class="pointer-events-none grid place-self-center will-change-transform [animation-play-state:paused] [animation:c3d-ry_var(--c3d-duration)_linear_infinite] [transform-style:preserve-3d] motion-reduce:[animation-duration:128s]"
          :class="[
            isPlaying && '[animation-play-state:running]',
            reverse && '[animation-direction:reverse]',
          ]"
        >
          <div
            v-for="(item, i) in items"
            :key="i"
            :class="
              cn(
                'pointer-events-auto [grid-area:1/1] overflow-hidden backface-hidden [aspect-ratio:var(--c3d-aspect)] [border-radius:var(--c3d-radius)] [transform:rotateY(calc(var(--c3d-init)+var(--i)*1turn/var(--n)))_translateZ(calc(-1*var(--c3d-ring-radius)))_scale(var(--c3d-card-scale,1))] [transition:transform_0.35s_ease] [width:var(--c3d-w)] hover:z-[2] hover:[--c3d-card-scale:var(--c3d-hover-scale,1)] motion-reduce:transition-none',
                interactive &&
                  'focus-visible:ring-ring cursor-pointer outline-none focus-visible:ring-2',
                cardClass,
              )
            "
            :style="{ '--i': i }"
            :role="interactive ? 'button' : undefined"
            :tabindex="interactive ? (i === focusedIndex ? 0 : -1) : undefined"
            :data-c3d-card="i"
            @click="onCardClick($event, item, i)"
            @keydown="interactive && onCardKeydown($event, item, i)"
            @focus="interactive && (focusedIndex = i)"
          >
            <slot name="item" :item="item" :index="i">
              <div
                v-if="imageErrors.has(i)"
                role="img"
                :aria-label="item.alt || 'Image failed to load'"
                class="bg-muted text-muted-foreground flex h-full w-full items-center justify-center"
              >
                <Icon name="lucide:image-off" class="size-6" aria-hidden="true" />
              </div>
              <img
                v-else
                class="block h-full w-full object-cover"
                :src="item.src"
                :alt="item.alt ?? ''"
                :loading="imageLoading"
                :fetchpriority="i === 0 ? 'high' : 'low'"
                @error="onImageError(i)"
              />
            </slot>
          </div>
        </div>
      </div>
      <div
        v-if="showShadow"
        aria-hidden="true"
        class="pointer-events-none absolute bottom-[8%] left-1/2 h-[1.75em] w-[55%] -translate-x-1/2 blur-[4px] [background:radial-gradient(ellipse_at_center,color-mix(in_oklab,var(--color-foreground)_22%,transparent),transparent_70%)]"
      />
    </template>
  </div>
</template>

<script setup lang="ts" generic="T extends Carousel3dItem = Carousel3dItem">
import { cn } from "@/lib/utils";
import type { Carousel3dItem, Carousel3dProps } from "./types";

const props = withDefaults(defineProps<Carousel3dProps<T>>(), {
  items: () => [] as T[],
  cardWidth: "clamp(10em, 35vw, 17.5em)",
  cardAspect: "7 / 10",
  cardRadius: "1.5em",
  gap: "0.5em",
  perspective: "35em",
  tilt: "0deg",
  initialRotation: "0deg",
  centerIndex: 0,
  duration: "32s",
  animated: true,
  reverse: false,
  pauseOnHover: false,
  pauseOffScreen: true,
  pauseOnHidden: true,
  hoverScale: "1",
  fadeEdges: true,
  showShadow: false,
  clickToToggle: true,
  clipOverflow: true,
  interactive: false,
  imageLoading: "lazy",
  ariaLabel: "3D rotating carousel",
});

const emit = defineEmits<{
  "item-click": [item: T, index: number, event: MouseEvent | KeyboardEvent];
  "update:animated": [value: boolean];
}>();

const sceneRef = ref<HTMLElement | null>(null);
const isVisible = ref(true);
const isHovered = ref(false);
const isPaused = ref(!props.animated);
const focusedIndex = ref(0);
const imageErrors = ref<Set<number>>(new Set());

const documentVisibility = useDocumentVisibility();

useIntersectionObserver(
  sceneRef,
  ([entry]) => {
    isVisible.value = entry?.isIntersecting ?? true;
  },
  { threshold: 0 },
);

const count = computed(() => props.items.length);

const isToggleable = computed<boolean>(() => props.clickToToggle && count.value > 1);

const ringRadius = computed<string>(() => {
  const n = count.value;
  if (n < 2) return "0em";
  if (n === 2) return "calc(0.5 * var(--c3d-w) + 2em)";
  return `calc((0.5 * var(--c3d-w) + 0.5 * var(--c3d-gap)) / tan(0.5turn / ${n}))`;
});

const effectiveInitialRotation = computed<string>(() => {
  const n = count.value || 1;
  const offsetDeg = -props.centerIndex * (360 / n);
  return `calc(${props.initialRotation} + ${offsetDeg}deg)`;
});

const isPlaying = computed<boolean>(() => {
  if (isPaused.value) return false;
  if (props.pauseOffScreen && !isVisible.value) return false;
  if (props.pauseOnHover && isHovered.value) return false;
  if (props.pauseOnHidden && documentVisibility.value === "hidden") return false;
  return true;
});

const sceneStyle = computed<Record<string, string | number>>(() => ({
  "--n": count.value || 1,
  "--c3d-w": props.cardWidth,
  "--c3d-aspect": props.cardAspect,
  "--c3d-radius": props.cardRadius,
  "--c3d-gap": props.gap,
  "--c3d-perspective": props.perspective,
  "--c3d-tilt": props.tilt,
  "--c3d-init": effectiveInitialRotation.value,
  "--c3d-duration": props.duration,
  "--c3d-hover-scale": props.hoverScale,
  "--c3d-ring-radius": ringRadius.value,
}));

watch(
  () => props.animated,
  (v) => {
    isPaused.value = !v;
  },
);

watch(
  () => props.items,
  () => {
    imageErrors.value.clear();
    if (focusedIndex.value >= count.value) focusedIndex.value = 0;
  },
);

function togglePlay(): void {
  isPaused.value = !isPaused.value;
  emit("update:animated", !isPaused.value);
}

function onSceneClick(): void {
  if (isToggleable.value) togglePlay();
}

function onSceneKeydown(event: KeyboardEvent): void {
  if (!isToggleable.value) return;
  if (event.key === "Enter" || event.key === " ") {
    event.preventDefault();
    togglePlay();
  }
}

function onCardClick(event: MouseEvent, item: T, index: number): void {
  if (props.interactive) event.stopPropagation();
  emit("item-click", item, index, event);
}

function onCardKeydown(event: KeyboardEvent, item: T, index: number): void {
  const n = count.value;
  if (event.key === "Enter" || event.key === " ") {
    event.preventDefault();
    event.stopPropagation();
    emit("item-click", item, index, event);
  } else if (event.key === "ArrowRight") {
    event.preventDefault();
    event.stopPropagation();
    focusedIndex.value = (index + 1) % n;
    nextTick(() => focusCard(focusedIndex.value));
  } else if (event.key === "ArrowLeft") {
    event.preventDefault();
    event.stopPropagation();
    focusedIndex.value = (index - 1 + n) % n;
    nextTick(() => focusCard(focusedIndex.value));
  } else if (event.key === "Home") {
    event.preventDefault();
    event.stopPropagation();
    focusedIndex.value = 0;
    nextTick(() => focusCard(0));
  } else if (event.key === "End") {
    event.preventDefault();
    event.stopPropagation();
    focusedIndex.value = n - 1;
    nextTick(() => focusCard(n - 1));
  }
}

function focusCard(idx: number): void {
  const card = sceneRef.value?.querySelector<HTMLElement>(`[data-c3d-card="${idx}"]`);
  card?.focus();
}

function onImageError(index: number): void {
  imageErrors.value.add(index);
}
</script>

<style>
@keyframes c3d-ry {
  to {
    rotate: y 1turn;
  }
}
</style>
