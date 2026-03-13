<template>
  <div
    v-if="model"
    :style="!model?.profile_image && colorful ? meshGradientStyle : undefined"
    :class="[
      'outline-primary/10 @container relative flex aspect-square shrink-0 items-center justify-center text-center outline -outline-offset-1',
      !model?.profile_image && !colorful ? 'bg-muted' : '',
      rounded,
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
      :class="['initial text-[45cqw] font-medium tracking-tight', colorful ? 'text-white' : 'text-muted-foreground']"
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
  colorful: {
    type: Boolean,
    default: true,
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

const meshGradientStyle = computed(() => {
  const h = hue.value;
  return {
    background: [
      `radial-gradient(at 15% 15%, oklch(0.78 0.26 ${h}) 0%, transparent 50%)`,
      `radial-gradient(at 85% 80%, oklch(0.52 0.28 ${(h + 30) % 360}) 0%, transparent 50%)`,
      `radial-gradient(at 60% 40%, oklch(0.65 0.3 ${(h + 12) % 360}) 0%, transparent 55%)`,
      `oklch(0.45 0.2 ${(h + 18) % 360})`,
    ].join(", "),
  };
});
</script>
