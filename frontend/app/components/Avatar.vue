<template>
  <div
    v-if="model"
    :style="{
      '--hue': hue,
      '--hue-end': (hue + 20) % 360,
    }"
    :class="[
      'outline-primary/10 @container relative flex aspect-square shrink-0 items-center justify-center text-center outline -outline-offset-1 [--bg-chroma:0.16] [--bg-lightness:0.9] [--text-chroma:0.16] [--text-lightness:0.32] dark:[--bg-chroma:0.14] dark:[--bg-lightness:0.28] dark:[--text-chroma:0.16] dark:[--text-lightness:0.8]',
      rounded,
      !model?.profile_image &&
        'bg-[linear-gradient(135deg,oklch(var(--bg-lightness)_var(--bg-chroma)_var(--hue)),oklch(calc(var(--bg-lightness)*0.97)_calc(var(--bg-chroma)*0.9)_var(--hue-end)))]',
    ]"
  >
    <img
      v-if="model?.profile_image"
      :src="model.profile_image[size] || model.profile_image.sm"
      :alt="model?.name"
      :class="['size-full object-cover select-none', rounded]"
      width="100"
      height="100"
      loading="lazy"
      referrerPolicy="no-referrer"
    />
    <span
      v-else
      class="initial text-[45cqw] font-medium tracking-tight text-[oklch(var(--text-lightness)_var(--text-chroma)_var(--hue))]"
    >
      {{
        (() => {
          const names = model?.name?.split(" ") || [];
          const first = names[0]?.[0]?.toUpperCase() || "";
          const last =
            names.length === 1
              ? names[0]?.[1]?.toUpperCase() || ""
              : names[names.length - 1]?.[0]?.toUpperCase() || "";
          return first + last;
        })()
      }}
    </span>

    <span
      v-if="showIndicator"
      class="ring-background bg-success absolute -right-0.5 -bottom-0.5 size-2 rounded-full ring-2"
    ></span>
  </div>
</template>

<script setup>
const props = defineProps({
  model: Object,
  showIndicator: {
    type: Boolean,
    default: false,
  },
  size: {
    type: String,
    default: "sm",
  },
  rounded: {
    type: String,
    default: "rounded-lg",
  },
});

const hue = computed(() => {
  const name = props.model?.name || "";
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return Math.abs(hash) % 360;
});
</script>
