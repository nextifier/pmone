<template>
  <div
    v-if="model"
    class="border-border @container relative flex aspect-square shrink-0 items-center justify-center rounded-lg border text-center"
    :style="avatarStyle"
  >
    <img
      v-if="model?.profile_image"
      :src="profileImageSrc"
      :alt="model?.name"
      class="pointer-events-none size-full rounded-lg object-cover select-none"
      width="100"
      height="100"
      loading="lazy"
      referrerPolicy="no-referrer"
    />
    <div v-else>
      <span class="initial text-[40cqw] font-medium tracking-wide text-white">{{ initial }}</span>
    </div>

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
    default: "sm", // Available: 'sm', 'md', 'lg', 'xl', 'original'
  },
});

const profileImageSrc = computed(() => {
  if (!props.model?.profile_image) return null;

  const sizeMap = {
    sm: props.model.profile_image.sm,
    md: props.model.profile_image.md,
    lg: props.model.profile_image.lg,
    xl: props.model.profile_image.xl,
    original: props.model.profile_image.original,
  };

  return sizeMap[props.size] || props.model.profile_image.sm;
});

const avatarStyle = computed(() => {
  // Only apply gradient background when there's no profile image
  if (props.model?.profile_image) {
    return {};
  }

  const nameLength = props.model?.name?.length || 0;

  // Map name length to hue (0-360 degrees)
  // Using a range that provides good contrast with white text
  // Avoiding very light colors (yellow ~60°) by using ranges with better contrast
  const minHue = 0; // Red
  const maxHue = 360; // Purple-blue range (avoiding yellows/light greens)

  // Normalize name length to 0-1 range (assuming max name length ~50 chars)
  const normalizedLength = Math.min(nameLength / 50, 1);

  // Calculate hue based on name length
  const hue = minHue + normalizedLength * (maxHue - minHue);

  // OKLCH values: lightness between 45-55% for good contrast with white text
  // Chroma (saturation) at 0.15-0.18 for vibrant but not overwhelming colors
  const lightness = 0.5; // 50% lightness for good contrast
  const chroma = 0.16; // Medium saturation

  // Create two colors for gradient (main color and slightly rotated hue)
  const color1 = `oklch(${lightness} ${chroma} ${hue})`;
  const color2 = `oklch(${lightness * 0.8} ${chroma * 1.2} ${(hue + 20) % 360})`;

  return {
    background: `linear-gradient(135deg, ${color1} 0%, ${color2} 100%)`,
  };
});

const initial = computed(() => {
  let names = props?.model?.name?.split(" "),
    initials = names[0].substring(0, 1).toUpperCase();
  if (names.length == 1) {
    initials += names[0].substring(1, 2).toUpperCase();
  } else if (names.length > 1) {
    initials += names[names.length - 1].substring(0, 1).toUpperCase();
  }
  return initials;
});
</script>
