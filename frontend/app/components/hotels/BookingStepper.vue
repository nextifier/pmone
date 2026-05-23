<script setup>
import { computed } from "vue";

const props = defineProps({
  step: { type: Number, default: 1 },
  canStep2: { type: Boolean, default: false },
  canStep3: { type: Boolean, default: false },
  canStep4: { type: Boolean, default: false },
  hasAddons: { type: Boolean, default: true },
});

const emit = defineEmits(["update:step"]);

const steps = computed(() => {
  const all = [
    { n: 1, label: "Dates & Rooms", enabled: true },
    { n: 2, label: "Add-ons", enabled: props.canStep2 },
    { n: 3, label: "Guest Info", enabled: props.canStep3 },
    { n: 4, label: "Review", enabled: props.canStep4 },
  ];
  return props.hasAddons ? all : all.filter((s) => s.n !== 2);
});

function goTo(n) {
  const target = steps.value.find((s) => s.n === n);
  if (!target || !target.enabled) return;
  emit("update:step", n);
}
</script>

<template>
  <nav
    aria-label="Booking steps"
    class="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky top-0 z-30 -mx-4 border-b px-4 py-3 backdrop-blur"
  >
    <ol class="mx-auto flex max-w-5xl items-center justify-between gap-2 sm:gap-4">
      <li
        v-for="(s, i) in steps"
        :key="s.n"
        class="flex flex-1 items-center justify-center gap-2 sm:justify-start"
      >
        <button
          type="button"
          class="group flex items-center gap-2 transition-opacity"
          :class="{
            'cursor-pointer': s.enabled,
            'cursor-not-allowed opacity-50': !s.enabled,
          }"
          :disabled="!s.enabled"
          @click="goTo(s.n)"
        >
          <span
            class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs sm:text-sm font-medium tabular-nums"
            :class="{
              'bg-primary text-primary-foreground': step === s.n,
              'bg-primary/15 text-primary': step > s.n,
              'bg-muted text-muted-foreground': step < s.n,
            }"
          >
            <Icon
              v-if="step > s.n"
              name="hugeicons:tick-02"
              class="size-3.5"
              aria-hidden="true"
            />
            <span v-else>{{ i + 1 }}</span>
          </span>
          <span
            class="hidden text-sm font-medium tracking-tight sm:inline-block"
            :class="{
              'text-foreground': step === s.n,
              'text-muted-foreground': step !== s.n,
            }"
          >
            {{ s.label }}
          </span>
        </button>
        <span
          v-if="i < steps.length - 1"
          class="hidden h-px flex-1 sm:block"
          :class="{
            'bg-primary': step > s.n,
            'bg-border': step <= s.n,
          }"
        />
      </li>
    </ol>
  </nav>
</template>
