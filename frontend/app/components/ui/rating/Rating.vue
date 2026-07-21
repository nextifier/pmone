<script setup lang="ts">
/**
 * Rating — bintang interaktif di atas primitive Rating reka-ui, plus jalur
 * render statis untuk mode `readonly`.
 *
 * Kenapa dua jalur: reka hanya bisa menyalakan nilai yang ada di grid `step`,
 * sedangkan nilai display seperti 4.2 tidak punya step yang cocok kecuali
 * step dibuat sangat kecil (50 radio per rating). Mode readonly juga bukan
 * radiogroup secara semantik, jadi ia dirender sebagai `role="img"` dengan
 * overlay lebar-persen seperti sebelumnya.
 */
import type { HTMLAttributes } from "vue";
import { computed, defineComponent, ref } from "vue";
import {
  RatingItem,
  RatingItemIndicator,
  RatingRoot,
  injectRatingRootContext,
} from "reka-ui";
import { cn } from "@/lib/utils";
import {
  ratingVariants,
  starActiveColorVariants,
  starColorVariants,
  starVariants,
  valueVariants,
} from ".";

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
    /** Izinkan input setengah bintang (step 0.5: dua radio per bintang). */
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

/**
 * `RatingRoot` menyimpan `hoveredRating` di context-nya, tidak mengeksposnya
 * lewat slot, dan tidak pernah me-resetnya saat pointer meninggalkan komponen.
 * Component renderless ini dirender sebagai anak root (satu-satunya cara
 * meng-inject context tersebut) lalu meneruskan nilai + reset-nya ke sini.
 */
const RatingHoverBridge = defineComponent({
  name: "RatingHoverBridge",
  setup(_props, { expose }) {
    const context = injectRatingRootContext();

    expose({
      hovered: context.hoveredRating,
      reset: () => context.changeHoveredRating(0),
    });

    return () => null;
  },
});

const bridge = ref<{ hovered: number; reset: () => void } | null>(null);
const hovered = computed(() => bridge.value?.hovered ?? 0);

// Clamp ke [0, max] supaya nilai di luar batas tidak merusak fill/ARIA.
const clampedValue = computed(() =>
  Math.min(Math.max(props.modelValue ?? 0, 0), props.max),
);

const step = computed(() => (props.allowHalf ? 0.5 : 1));

const stars = computed(() =>
  Array.from({ length: props.max }, (_, idx) => idx + 1),
);

const displayRating = computed(() =>
  hovered.value > 0 ? hovered.value : clampedValue.value,
);

function fillPercent(i: number): number {
  const value = displayRating.value;
  if (value >= i) {
    return 100;
  }
  if (value > i - 1) {
    return (value - (i - 1)) * 100;
  }
  return 0;
}

// Nilai yang tidak jatuh di grid step (mis. 3.7 saat step 1) tidak punya
// indicator yang bisa menyala, jadi sisa pecahannya digambar overlay sendiri.
const isOnStepGrid = computed(() => {
  const ratio = displayRating.value / step.value;
  return Math.abs(ratio - Math.round(ratio)) < 1e-6;
});

function partialPercent(i: number): number {
  if (isOnStepGrid.value) {
    return 0;
  }
  const percent = fillPercent(i);
  return percent > 0 && percent < 100 ? percent : 0;
}

/**
 * Shim keyboard di atas RovingFocus milik reka:
 * - ArrowUp/ArrowDown diabaikan RovingFocus karena orientation horizontal, jadi
 *   fokus digeser manual; `RadioGroupItem` sendiri yang menyeleksi item yang
 *   baru difokus. Sengaja TIDAK emit manual: dengan `clearable`, emit + seleksi
 *   otomatis saling membatalkan jadi 0.
 * - Home/End di RovingFocus hanya memindah fokus, jadi nilainya di-emit sendiri
 *   supaya paritas dengan perilaku sebelumnya.
 */
function onKeydown(event: KeyboardEvent): void {
  if (props.disabled) {
    return;
  }

  if (event.key === "Home") {
    event.preventDefault();
    emit("update:modelValue", 0);

    return;
  }

  if (event.key === "End") {
    event.preventDefault();
    emit("update:modelValue", props.max);

    return;
  }

  if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
    return;
  }

  event.preventDefault();

  const radios = Array.from(
    (event.currentTarget as HTMLElement).querySelectorAll<HTMLElement>(
      '[role="radio"]:not([disabled])',
    ),
  );
  const current = radios.indexOf(document.activeElement as HTMLElement);

  if (current < 0) {
    return;
  }

  const next = Math.min(
    Math.max(current + (event.key === "ArrowUp" ? 1 : -1), 0),
    radios.length - 1,
  );

  radios[next]?.focus();
}

const valueText = computed(() => {
  const value = clampedValue.value;
  const label = Number.isInteger(value) ? String(value) : value.toFixed(1);

  return `${label} of ${props.max}`;
});

const displayLabel = computed(() => displayRating.value.toFixed(1));
</script>

<template>
  <!-- Interaktif: radiogroup, roving focus, hover preview, dan half-step dari reka. -->
  <RatingRoot
    v-if="!readonly"
    data-slot="rating"
    :model-value="clampedValue"
    :length="max"
    :step="step"
    :clearable="clearable"
    :hoverable="!disabled"
    :disabled="disabled"
    :loop="false"
    :class="
      cn(
        ratingVariants({ size }),
        'has-[:focus-visible]:border-ring has-[:focus-visible]:ring-ring rounded-md transition-[color,box-shadow] has-[:focus-visible]:ring-[1px]',
        disabled && 'cursor-not-allowed opacity-50',
        props.class,
      )
    "
    @update:model-value="emit('update:modelValue', $event)"
    @pointerleave="bridge?.reset()"
    @pointercancel="bridge?.reset()"
    @keydown="onKeydown"
  >
    <template #default="{ items }">
      <RatingHoverBridge ref="bridge" />

      <RatingItem
        v-for="i in items"
        :key="i"
        :item="i"
        as="span"
        data-slot="rating-star"
        :class="cn('relative inline-flex shrink-0', props.starClass)"
      >
        <template #default="{ steps }">
          <Icon
            name="lucide:star"
            aria-hidden="true"
            :class="cn(starVariants({ size }), 'rating-star__base text-border')"
          />

          <!-- Sisa pecahan di luar grid step (mis. 3.7 saat step 1). -->
          <span
            v-if="partialPercent(i) > 0"
            data-slot="rating-star-fill"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden"
            :style="{ width: `${partialPercent(i)}%` }"
          >
            <Icon
              name="lucide:star"
              aria-hidden="true"
              :class="
                cn(
                  starVariants({ size }),
                  'rating-star__fill',
                  starColorVariants({ color }),
                )
              "
            />
          </span>

          <!--
            `--reka-rating-item-step-opacity` tidak dipakai DAN nilainya ditimpa
            jadi konstan: reka menghitungnya dari `document.activeElement`,
            sehingga server menulis 1 dan klien 0 lalu memicu hydration mismatch
            pada tiap indikator setengah bintang. Tidak dibutuhkan di sini karena
            indikator yang belum aktif sudah transparan lewat warnanya sendiri.
          -->
          <RatingItemIndicator
            v-for="itemStep in steps"
            :key="itemStep"
            :step="itemStep"
            data-slot="rating-star-fill"
            :aria-label="`${itemStep} of ${max}`"
            :class="
              cn(
                'absolute inset-0 cursor-pointer overflow-hidden outline-none disabled:cursor-not-allowed',
                starActiveColorVariants({ color }),
              )
            "
            :style="{
              '--reka-rating-item-step-opacity': '1',
              width: 'var(--reka-rating-item-step-width)',
              zIndex: 'var(--reka-rating-item-step-z-index)',
            }"
          >
            <Icon
              name="lucide:star"
              aria-hidden="true"
              :class="cn(starVariants({ size }), 'rating-star__fill')"
            />
          </RatingItemIndicator>
        </template>
      </RatingItem>

      <span
        v-if="showValue"
        data-slot="rating-value"
        :class="cn(valueVariants({ size }), 'text-muted-foreground ms-1')"
      >
        {{ displayLabel }}
      </span>
    </template>
  </RatingRoot>

  <!-- Display-only: tanpa radio, supaya nilai pecahan sembarang tetap presisi. -->
  <div
    v-else
    data-slot="rating"
    role="img"
    :aria-label="`Rating: ${valueText}`"
    :class="
      cn(
        ratingVariants({ size }),
        disabled && 'cursor-not-allowed opacity-50',
        props.class,
      )
    "
  >
    <span
      v-for="i in stars"
      :key="i"
      data-slot="rating-star"
      class="relative inline-flex shrink-0"
      :class="props.starClass"
    >
      <Icon
        name="lucide:star"
        aria-hidden="true"
        :class="cn(starVariants({ size }), 'rating-star__base text-border')"
      />
      <span
        data-slot="rating-star-fill"
        class="absolute inset-0 overflow-hidden"
        :style="{ width: `${fillPercent(i)}%` }"
        aria-hidden="true"
      >
        <Icon
          name="lucide:star"
          aria-hidden="true"
          :class="
            cn(
              starVariants({ size }),
              'rating-star__fill',
              starColorVariants({ color }),
            )
          "
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
 * Berlaku untuk bintang kosong (`text-border`) maupun bintang terisi
 * (`colorClass`), supaya keduanya tampil solid dengan siluet yang sama.
 *
 * `stroke: none` wajib: dengan warna semi-transparan seperti `--border`, stroke
 * menumpuk di atas fill sehingga tepi jadi dua kali lebih pekat dan terbaca
 * sebagai outline.
 */
:deep(.rating-star__base path),
:deep(.rating-star__fill path) {
  fill: currentColor;
  stroke: none;
}
</style>
