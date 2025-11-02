<template>
  <div
    v-if="model"
    :class="[
      'border-primary/5 @container relative flex aspect-square shrink-0 items-center justify-center border text-center',
      rounded,
    ]"
    :style="avatarStyle"
  >
    <img
      v-if="model?.profile_image"
      :src="profileImageSrc"
      :alt="model?.name"
      :class="['pointer-events-none size-full object-cover select-none', rounded]"
      width="100"
      height="100"
      loading="lazy"
      referrerPolicy="no-referrer"
    />
    <div v-else>
      <span class="initial text-[40cqw] font-medium tracking-wide" :style="textStyle">{{
        initial
      }}</span>
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
  rounded: {
    type: String,
    default: "rounded-lg", // Can be overridden with rounded-full, rounded-xl, etc.
  },
});

const { colorMode } = useThemeSync();

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
  const isDark = colorMode.value === "dark";

  // Map name length to hue (0-360 degrees)
  const minHue = 0; // Red
  const maxHue = 360; // Full spectrum

  // Normalize name length to 0-1 range (assuming max name length ~50 chars)
  const normalizedLength = Math.min(nameLength / 50, 1);

  // Calculate hue based on name length
  const hue = minHue + normalizedLength * (maxHue - minHue);

  // Adjust lightness and chroma based on color mode
  let bgLightness, bgChroma;

  if (isDark) {
    // Dark mode: darker backgrounds (30-35% lightness)
    bgLightness = 0.32;
    bgChroma = 0.14; // Slightly lower saturation for dark mode
  } else {
    // Light mode: lighter backgrounds (85-90% lightness)
    bgLightness = 0.9;
    bgChroma = 0.14; // Lower saturation for light mode to keep it soft
  }

  // Create two colors for gradient (main color and slightly rotated hue)
  const color1 = `oklch(${bgLightness} ${bgChroma} ${hue})`;
  const color2 = `oklch(${bgLightness * (isDark ? 0.9 : 1.1)} ${bgChroma * 1.1} ${(hue + 20) % 360})`;

  return {
    background: `linear-gradient(135deg, ${color1} 0%, ${color2} 100%)`,
  };
});

const textStyle = computed(() => {
  // Only apply text color when there's no profile image
  if (props.model?.profile_image) {
    return {};
  }

  const nameLength = props.model?.name?.length || 0;
  const isDark = colorMode.value === "dark";

  // Use same hue calculation as background
  const minHue = 0;
  const maxHue = 360;
  const normalizedLength = Math.min(nameLength / 50, 1);
  const hue = minHue + normalizedLength * (maxHue - minHue);

  // Adjust text lightness based on color mode
  let textLightness, textChroma;

  if (isDark) {
    // Dark mode: lighter text (85-90% lightness) on dark background
    textLightness = 0.88;
    textChroma = 0.15;
  } else {
    // Light mode: darker text (30-35% lightness) on light background
    textLightness = 0.32;
    textChroma = 0.18;
  }

  const textColor = `oklch(${textLightness} ${textChroma} ${hue})`;

  return {
    color: textColor,
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
