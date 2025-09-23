<template>
  <div
    class="inverted-border-radius absolute isolate flex bg-[var(--ibr-background)] *:relative *:z-50 *:flex *:items-center *:justify-center *:gap-x-1 *:rounded-full *:transition before:absolute before:z-0 before:size-[var(--ibr-size)] before:rounded-full before:bg-transparent after:absolute after:z-0 after:size-[var(--ibr-size)] after:rounded-full after:bg-transparent *:active:scale-98"
    :class="{
      'top-0 left-0 rounded-br-[var(--ibr-radius)] pr-[var(--ibr-padding)] pb-[var(--ibr-padding)] before:top-0 before:right-0 before:translate-x-full before:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)] after:bottom-0 after:left-0 after:translate-y-full after:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'top-left',

      'top-0 right-0 rounded-bl-[var(--ibr-radius)] pb-[var(--ibr-padding)] pl-[var(--ibr-padding)] before:top-0 before:left-0 before:-translate-x-full before:shadow-[calc(var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)] after:right-0 after:bottom-0 after:translate-y-full after:shadow-[calc(var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'top-right',

      'bottom-0 left-0 rounded-tr-[var(--ibr-radius)] pt-[var(--ibr-padding)] pr-[var(--ibr-padding)] before:top-0 before:left-0 before:-translate-y-full before:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)] after:right-0 after:bottom-0 after:translate-x-full after:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'bottom-left',

      'right-0 bottom-0 rounded-tl-[var(--ibr-radius)] pt-[var(--ibr-padding)] pl-[var(--ibr-padding)] before:top-0 before:right-0 before:-translate-y-full before:shadow-[calc(var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)] after:bottom-0 after:left-0 after:-translate-x-full after:shadow-[calc(var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'bottom-right',

      'top-0 left-1/2 -translate-x-1/2 rounded-b-[var(--ibr-radius)] px-[var(--ibr-padding)] pb-[var(--ibr-padding)] before:top-0 before:left-0 before:-translate-x-full before:shadow-[calc(var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)] after:top-0 after:right-0 after:translate-x-full after:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'top-center',

      'bottom-0 left-1/2 -translate-x-1/2 rounded-t-[var(--ibr-radius)] px-[var(--ibr-padding)] pt-[var(--ibr-padding)] before:bottom-0 before:left-0 before:-translate-x-full before:shadow-[calc(var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)] after:right-0 after:bottom-0 after:translate-x-full after:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'bottom-center',

      'top-1/2 left-0 -translate-y-1/2 rounded-r-[var(--ibr-radius)] py-[var(--ibr-padding)] pr-[var(--ibr-padding)] before:top-0 before:left-0 before:-translate-y-full before:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)] after:bottom-0 after:left-0 after:translate-y-full after:shadow-[calc(-1*var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'left-middle',

      'top-1/2 right-0 -translate-y-1/2 rounded-l-[var(--ibr-radius)] py-[var(--ibr-padding)] pl-[var(--ibr-padding)] before:top-0 before:right-0 before:-translate-y-full before:shadow-[calc(var(--ibr-shadow-offset))_calc(var(--ibr-shadow-offset))_0_var(--ibr-background)] after:right-0 after:bottom-0 after:translate-y-full after:shadow-[calc(var(--ibr-shadow-offset))_calc(-1*var(--ibr-shadow-offset))_0_var(--ibr-background)]':
        position === 'right-middle',
    }"
  >
    <slot>
      <component
        :is="link ? NuxtLink : 'span'"
        :to="link || null"
        :target="openInNewTab ? '_blank' : null"
        class="bg-muted text-primary hover:bg-border size-14"
        v-ripple
      >
        <Icon
          v-if="props.iconName"
          :name="props.iconName"
          class="size-[var(--ibr-icon-size)]"
        />
      </component>
    </slot>
  </div>
</template>

<script setup>
import { NuxtLink } from "#components";

const props = defineProps({
  position: {
    type: String,
    default: "bottom-right",
    validator: (value) =>
      [
        "top-right",
        "top-left",
        "bottom-left",
        "bottom-right",
        "top-center",
        "bottom-center",
        "left-middle",
        "right-middle",
      ].includes(value),
  },
  link: {
    type: String,
    default: null,
  },
  openInNewTab: {
    type: Boolean,
    default: false,
  },
  iconName: {
    type: String,
    default: "hugeicons:arrow-up-right-01",
  },
});
</script>

<style>
.inverted-border-radius {
  --ibr-background: var(--color-background);
  --ibr-radius: 2.25rem;
  --ibr-padding: 0.5rem;
  --ibr-size: 2.5rem;
  --ibr-shadow-offset: 24px;
  --ibr-icon-size: 1.25rem;
}
</style>
