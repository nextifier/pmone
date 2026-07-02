<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { computed, ref } from "vue";
import { cn } from "@/lib/utils";
import { ratingVariants, starVariants, valueVariants } from ".";

type RatingSize = "sm" | "default" | "lg";
type RatingColor = "yellow" | "warning" | "primary" | "foreground";

const props = withDefaults(
  defineProps<{
    /** Nilai rating saat ini. v-model. Nilai pecahan dirender sebagai bintang terisi sebagian. */
    modelValue?: number;
    /** Jumlah bintang maksimum. */
    max?: number;
    size?: RatingSize;
    /** Display-only: tanpa hover, klik, atau keyboard. */
    readonly?: boolean;
    /** Nonaktif: dimmed dan tidak bisa diinteraksikan (untuk form). */
    disabled?: boolean;
    /** Tampilkan angka nilai di samping bintang. */
    showValue?: boolean;
    /** Izinkan input setengah bintang (deteksi posisi pointer + step keyboard 0.5). */
    allowHalf?: boolean;
    /** Klik nilai yang sama me-reset rating ke 0. Default off (paritas ReUI). */
    clearable?: boolean;
    color?: RatingColor;
    class?: HTMLAttributes["class"];
    /** Kelas tambahan untuk tiap wrapper bintang. */
    starClass?: HTMLAttributes["class"];
  }>(),
  {
    modelValue: 0,
    max: 5,
    size: "default",
    readonly: false,
    disabled: false,
    showValue: false,
    allowHalf: false,
    clearable: false,
    color: "yellow",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: number];
}>();

// Warna bintang terisi (stroke). Solid fill dipaksa lewat scoped CSS di bawah
// (path lucide:star hardcoded fill="none"). Default `yellow` = persis ReUI.
const colorClass = computed(() => {
  switch (props.color) {
    case "primary":
      return "text-foreground";
    case "foreground":
      return "text-foreground";
    case "warning":
      return "text-warning";
    case "yellow":
    default:
      return "text-yellow-400";
  }
});

// Root menampilkan semantik slider kecuali display-only (readonly).
const showsSlider = computed(() => !props.readonly);
// Bisa menerima hover/klik/keyboard/fokus.
const interactive = computed(() => !props.readonly && !props.disabled);

const rootRef = ref<HTMLElement | null>(null);

// Hover preview hanya untuk mode interaktif. null = tidak sedang hover.
// SSR-safe: nilai awal null; getBoundingClientRect/clientX hanya dipanggil di handler client.
const hovered = ref<number | null>(null);

// Clamp ke [0, max] supaya nilai di luar batas tidak merusak fill/ARIA.
const clampedValue = computed(() =>
  Math.min(Math.max(props.modelValue ?? 0, 0), props.max),
);

const displayRating = computed(() =>
  interactive.value && hovered.value != null
    ? hovered.value
    : clampedValue.value,
);

const step = computed(() => (props.allowHalf ? 0.5 : 1));
const stars = computed(() =>
  Array.from({ length: props.max }, (_, idx) => idx + 1),
);

function fillPercent(i: number): number {
  const v = displayRating.value;
  if (v >= i) {
    return 100;
  }
  if (v > i - 1) {
    return (v - (i - 1)) * 100;
  }
  return 0;
}

function valueFromPointer(event: PointerEvent, i: number): number {
  if (!props.allowHalf) {
    return i;
  }
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
  const isLeftHalf = event.clientX - rect.left < rect.width / 2;
  return isLeftHalf ? i - 0.5 : i;
}

function onStarMove(event: PointerEvent, i: number): void {
  if (!interactive.value) {
    return;
  }
  hovered.value = valueFromPointer(event, i);
}

function onStarDown(event: PointerEvent, i: number): void {
  if (!interactive.value) {
    return;
  }
  const next = valueFromPointer(event, i);
  // Klik nilai yang sama me-reset ke 0 hanya bila `clearable`.
  const cleared = props.clearable && next === clampedValue.value;
  emit("update:modelValue", cleared ? 0 : next);
  // `@pointerdown.prevent` memblokir fokus default; fokuskan root manual supaya
  // panah keyboard langsung bekerja setelah klik.
  rootRef.value?.focus();
}

function onLeave(): void {
  if (!interactive.value) {
    return;
  }
  hovered.value = null;
}

function clampStep(v: number): number {
  return Math.min(Math.max(v, 0), props.max);
}

function onKeydown(event: KeyboardEvent): void {
  if (!interactive.value) {
    return;
  }
  let next: number | null = null;
  switch (event.key) {
    case "ArrowRight":
    case "ArrowUp":
      next = clampStep(clampedValue.value + step.value);
      break;
    case "ArrowLeft":
    case "ArrowDown":
      next = clampStep(clampedValue.value - step.value);
      break;
    case "Home":
      next = 0;
      break;
    case "End":
      next = props.max;
      break;
    default:
      return;
  }
  event.preventDefault();
  emit("update:modelValue", next);
}

const valueText = computed(() => {
  const v = clampedValue.value;
  const label = Number.isInteger(v) ? String(v) : v.toFixed(1);
  return `${label} of ${props.max}`;
});

const displayLabel = computed(() => displayRating.value.toFixed(1));
</script>

<template>
  <div
    ref="rootRef"
    data-slot="rating"
    :role="showsSlider ? 'slider' : 'img'"
    :tabindex="interactive ? 0 : undefined"
    :aria-valuemin="showsSlider ? 0 : undefined"
    :aria-valuemax="showsSlider ? max : undefined"
    :aria-valuenow="showsSlider ? clampedValue : undefined"
    :aria-valuetext="showsSlider ? valueText : undefined"
    :aria-label="!showsSlider ? `Rating: ${valueText}` : undefined"
    :aria-readonly="readonly ? 'true' : undefined"
    :aria-disabled="disabled ? 'true' : undefined"
    :class="
      cn(
        ratingVariants({ size }),
        interactive &&
          'focus-visible:border-ring focus-visible:ring-ring cursor-pointer rounded-md transition-[color,box-shadow] focus-visible:ring-[1px]',
        disabled && 'cursor-not-allowed opacity-50',
        props.class,
      )
    "
    @pointerleave="onLeave"
    @keydown="onKeydown"
  >
    <span
      v-for="i in stars"
      :key="i"
      data-slot="rating-star"
      class="relative inline-flex shrink-0"
      :class="props.starClass"
      @pointermove="onStarMove($event, i)"
      @pointerdown.prevent="onStarDown($event, i)"
    >
      <Icon
        name="lucide:star"
        aria-hidden="true"
        :class="cn(starVariants({ size }), 'text-muted-foreground/30')"
      />
      <span
        class="absolute inset-0 overflow-hidden"
        :style="{ width: `${fillPercent(i)}%` }"
        aria-hidden="true"
      >
        <Icon
          name="lucide:star"
          aria-hidden="true"
          :class="cn(starVariants({ size }), 'rating-star__fill', colorClass)"
        />
      </span>
    </span>

    <span
      v-if="showValue"
      data-slot="rating-value"
      :class="cn(valueVariants({ size }), 'text-muted-foreground ms-1')"
    >
      {{ displayLabel }}
    </span>
  </div>
</template>

<style scoped>
/*
 * lucide:star adalah ikon stroke (path-nya `fill="none"`), jadi mewarnai `text-*`
 * hanya menghasilkan outline. @nuxt/icon meng-inline <svg><path>, sehingga fill
 * solid dipaksa di sini lewat CSS (menang atas atribut presentasi `fill="none"`).
 * currentColor mengambil warna dari `colorClass` -> bintang terisi solid,
 * identik dengan ReUI (`fill-yellow-400 text-yellow-400`).
 */
:deep(.rating-star__fill path) {
  fill: currentColor;
}
</style>
