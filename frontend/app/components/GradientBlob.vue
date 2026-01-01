<template>
  <div
    ref="containerRef"
    class="pointer-events-none absolute inset-0 overflow-hidden"
    :class="[containerClass, { paused: !isVisible }]"
  >
    <div class="blob-container" :style="containerStyle">
      <!-- Blob 1 - Large -->
      <div class="blob blob-1" :style="blob1Style">
        <div class="blob-inner blob-morph-1" :style="{ background: gradient1 }" />
      </div>
      <!-- Blob 2 - Medium -->
      <div class="blob blob-2" :style="blob2Style">
        <div class="blob-inner blob-morph-2" :style="{ background: gradient2 }" />
      </div>
      <!-- Blob 3 - Small -->
      <div class="blob blob-3" :style="blob3Style">
        <div class="blob-inner blob-morph-3" :style="{ background: gradient3 }" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
type PresetName =
  | "aurora"
  | "sunset"
  | "ocean"
  | "forest"
  | "neon"
  | "candy"
  | "midnight"
  | "ember"
  | "cosmic"
  | "mint"
  | "lavender"
  | "gold"
  | "framer-wireframer"
  | "framer-translate"
  | "framer-plugins";

interface Props {
  preset?: PresetName;
  scale?: number;
  position?:
    | "center"
    | "top"
    | "bottom"
    | "left"
    | "right"
    | "top-left"
    | "top-right"
    | "bottom-left"
    | "bottom-right";
  speed?: "slow" | "normal" | "fast";
  intensity?: "subtle" | "normal" | "vivid";
  containerClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
  preset: "aurora",
  scale: 6,
  position: "center",
  speed: "normal",
  intensity: "normal",
});

// Intersection Observer for performance - pause when not visible
const containerRef = ref<HTMLElement | null>(null);
const isVisible = ref(true);

onMounted(() => {
  if (!containerRef.value || typeof IntersectionObserver === "undefined") return;

  const observer = new IntersectionObserver(
    (entries) => {
      isVisible.value = entries[0]?.isIntersecting ?? true;
    },
    { threshold: 0.1 }
  );

  observer.observe(containerRef.value);

  onUnmounted(() => observer.disconnect());
});

// Static preset data - no reactivity needed for these
const presets: Record<PresetName, { gradients: string[]; filter: string }> = {
  aurora: {
    gradients: [
      "conic-gradient(oklch(65% 0.32 330) 0deg, oklch(55% 0.35 290) 60deg, oklch(50% 0.3 260) 120deg, oklch(60% 0.28 220) 180deg, oklch(55% 0.32 280) 240deg, oklch(62% 0.3 310) 300deg, oklch(65% 0.32 330) 360deg)",
      "conic-gradient(oklch(70% 0.28 340) 0deg, oklch(58% 0.32 280) 120deg, oklch(65% 0.25 230) 240deg, oklch(70% 0.28 340) 360deg)",
      "conic-gradient(oklch(75% 0.22 350) 0deg, oklch(62% 0.28 270) 180deg, oklch(68% 0.2 200) 360deg)",
    ],
    filter: "contrast(1.3) saturate(1.45)",
  },
  sunset: {
    gradients: [
      "conic-gradient(oklch(65% 0.35 35) 0deg, oklch(58% 0.38 20) 60deg, oklch(52% 0.34 5) 120deg, oklch(60% 0.32 50) 180deg, oklch(55% 0.36 25) 240deg, oklch(62% 0.33 40) 300deg, oklch(65% 0.35 35) 360deg)",
      "conic-gradient(oklch(70% 0.3 45) 0deg, oklch(55% 0.35 15) 120deg, oklch(62% 0.28 55) 240deg, oklch(70% 0.3 45) 360deg)",
      "conic-gradient(oklch(75% 0.25 50) 0deg, oklch(58% 0.32 25) 180deg, oklch(68% 0.22 60) 360deg)",
    ],
    filter: "contrast(1.35) saturate(1.5)",
  },
  ocean: {
    gradients: [
      "conic-gradient(oklch(50% 0.28 240) 0deg, oklch(58% 0.32 220) 60deg, oklch(65% 0.28 200) 120deg, oklch(70% 0.22 185) 180deg, oklch(62% 0.26 210) 240deg, oklch(55% 0.3 230) 300deg, oklch(50% 0.28 240) 360deg)",
      "conic-gradient(oklch(55% 0.3 235) 0deg, oklch(68% 0.25 195) 120deg, oklch(60% 0.28 215) 240deg, oklch(55% 0.3 235) 360deg)",
      "conic-gradient(oklch(60% 0.25 230) 0deg, oklch(72% 0.2 190) 180deg, oklch(65% 0.22 205) 360deg)",
    ],
    filter: "contrast(1.3) saturate(1.4)",
  },
  forest: {
    gradients: [
      "conic-gradient(oklch(52% 0.3 150) 0deg, oklch(58% 0.32 135) 60deg, oklch(48% 0.28 165) 120deg, oklch(55% 0.3 145) 180deg, oklch(50% 0.32 155) 240deg, oklch(56% 0.28 140) 300deg, oklch(52% 0.3 150) 360deg)",
      "conic-gradient(oklch(58% 0.28 155) 0deg, oklch(50% 0.32 140) 120deg, oklch(55% 0.25 165) 240deg, oklch(58% 0.28 155) 360deg)",
      "conic-gradient(oklch(62% 0.24 148) 0deg, oklch(52% 0.28 160) 180deg, oklch(58% 0.22 138) 360deg)",
    ],
    filter: "contrast(1.32) saturate(1.45)",
  },
  neon: {
    gradients: [
      "conic-gradient(oklch(72% 0.38 330) 0deg, oklch(68% 0.4 300) 60deg, oklch(75% 0.35 200) 120deg, oklch(80% 0.32 100) 180deg, oklch(70% 0.38 280) 240deg, oklch(74% 0.36 320) 300deg, oklch(72% 0.38 330) 360deg)",
      "conic-gradient(oklch(78% 0.35 340) 0deg, oklch(72% 0.38 260) 120deg, oklch(82% 0.3 120) 240deg, oklch(78% 0.35 340) 360deg)",
      "conic-gradient(oklch(82% 0.3 350) 0deg, oklch(75% 0.35 220) 180deg, oklch(85% 0.25 150) 360deg)",
    ],
    filter: "contrast(1.4) saturate(1.55)",
  },
  candy: {
    gradients: [
      "conic-gradient(oklch(75% 0.3 350) 0deg, oklch(70% 0.32 320) 60deg, oklch(72% 0.28 280) 120deg, oklch(78% 0.25 250) 180deg, oklch(74% 0.3 300) 240deg, oklch(76% 0.28 340) 300deg, oklch(75% 0.3 350) 360deg)",
      "conic-gradient(oklch(78% 0.28 355) 0deg, oklch(72% 0.3 290) 120deg, oklch(80% 0.22 240) 240deg, oklch(78% 0.28 355) 360deg)",
      "conic-gradient(oklch(82% 0.24 5) 0deg, oklch(75% 0.28 270) 180deg, oklch(85% 0.2 230) 360deg)",
    ],
    filter: "contrast(1.28) saturate(1.4)",
  },
  midnight: {
    gradients: [
      "conic-gradient(oklch(38% 0.28 280) 0deg, oklch(32% 0.32 260) 60deg, oklch(42% 0.25 300) 120deg, oklch(35% 0.3 240) 180deg, oklch(40% 0.28 270) 240deg, oklch(36% 0.3 290) 300deg, oklch(38% 0.28 280) 360deg)",
      "conic-gradient(oklch(42% 0.25 285) 0deg, oklch(35% 0.3 250) 120deg, oklch(38% 0.22 295) 240deg, oklch(42% 0.25 285) 360deg)",
      "conic-gradient(oklch(45% 0.2 278) 0deg, oklch(38% 0.26 265) 180deg, oklch(42% 0.18 288) 360deg)",
    ],
    filter: "contrast(1.35) saturate(1.4)",
  },
  ember: {
    gradients: [
      "conic-gradient(oklch(62% 0.35 35) 0deg, oklch(55% 0.38 15) 60deg, oklch(50% 0.36 355) 120deg, oklch(58% 0.34 25) 180deg, oklch(52% 0.37 5) 240deg, oklch(60% 0.35 30) 300deg, oklch(62% 0.35 35) 360deg)",
      "conic-gradient(oklch(65% 0.32 40) 0deg, oklch(52% 0.36 10) 120deg, oklch(58% 0.3 350) 240deg, oklch(65% 0.32 40) 360deg)",
      "conic-gradient(oklch(70% 0.28 45) 0deg, oklch(55% 0.34 20) 180deg, oklch(62% 0.25 0) 360deg)",
    ],
    filter: "contrast(1.38) saturate(1.52)",
  },
  cosmic: {
    gradients: [
      "conic-gradient(oklch(55% 0.35 300) 0deg, oklch(48% 0.38 330) 60deg, oklch(52% 0.32 270) 120deg, oklch(58% 0.35 350) 180deg, oklch(50% 0.36 310) 240deg, oklch(54% 0.34 285) 300deg, oklch(55% 0.35 300) 360deg)",
      "conic-gradient(oklch(58% 0.32 310) 0deg, oklch(50% 0.36 340) 120deg, oklch(55% 0.3 275) 240deg, oklch(58% 0.32 310) 360deg)",
      "conic-gradient(oklch(62% 0.28 305) 0deg, oklch(52% 0.34 335) 180deg, oklch(58% 0.25 280) 360deg)",
    ],
    filter: "contrast(1.4) saturate(1.48)",
  },
  mint: {
    gradients: [
      "conic-gradient(oklch(72% 0.28 175) 0deg, oklch(68% 0.32 160) 60deg, oklch(75% 0.26 190) 120deg, oklch(70% 0.3 150) 180deg, oklch(73% 0.28 180) 240deg, oklch(69% 0.3 165) 300deg, oklch(72% 0.28 175) 360deg)",
      "conic-gradient(oklch(75% 0.26 180) 0deg, oklch(68% 0.3 155) 120deg, oklch(72% 0.22 195) 240deg, oklch(75% 0.26 180) 360deg)",
      "conic-gradient(oklch(78% 0.22 178) 0deg, oklch(70% 0.28 162) 180deg, oklch(75% 0.2 188) 360deg)",
    ],
    filter: "contrast(1.3) saturate(1.42)",
  },
  lavender: {
    gradients: [
      "conic-gradient(oklch(70% 0.28 295) 0deg, oklch(65% 0.32 275) 60deg, oklch(72% 0.25 315) 120deg, oklch(68% 0.3 260) 180deg, oklch(71% 0.28 285) 240deg, oklch(67% 0.3 305) 300deg, oklch(70% 0.28 295) 360deg)",
      "conic-gradient(oklch(73% 0.25 300) 0deg, oklch(66% 0.3 270) 120deg, oklch(70% 0.22 320) 240deg, oklch(73% 0.25 300) 360deg)",
      "conic-gradient(oklch(76% 0.2 298) 0deg, oklch(68% 0.26 278) 180deg, oklch(74% 0.18 312) 360deg)",
    ],
    filter: "contrast(1.28) saturate(1.38)",
  },
  gold: {
    gradients: [
      "conic-gradient(oklch(72% 0.3 85) 0deg, oklch(65% 0.34 70) 60deg, oklch(70% 0.28 95) 120deg, oklch(62% 0.32 55) 180deg, oklch(68% 0.3 80) 240deg, oklch(74% 0.28 90) 300deg, oklch(72% 0.3 85) 360deg)",
      "conic-gradient(oklch(75% 0.28 88) 0deg, oklch(64% 0.32 65) 120deg, oklch(70% 0.24 98) 240deg, oklch(75% 0.28 88) 360deg)",
      "conic-gradient(oklch(78% 0.24 86) 0deg, oklch(68% 0.3 72) 180deg, oklch(74% 0.2 95) 360deg)",
    ],
    filter: "contrast(1.35) saturate(1.48)",
  },
  "framer-wireframer": {
    gradients: [
      "conic-gradient(oklch(65% 0.32 330) 0deg, oklch(55% 0.35 300) 90deg, oklch(50% 0.3 270) 180deg, oklch(60% 0.25 240) 270deg, oklch(65% 0.32 330) 360deg)",
      "conic-gradient(oklch(70% 0.28 320) 0deg, oklch(58% 0.3 280) 120deg, oklch(65% 0.22 230) 240deg, oklch(70% 0.28 320) 360deg)",
      "conic-gradient(oklch(75% 0.2 340) 0deg, oklch(62% 0.25 260) 180deg, oklch(68% 0.18 210) 360deg)",
    ],
    filter: "contrast(1.3) saturate(1.4)",
  },
  "framer-translate": {
    gradients: [
      "conic-gradient(oklch(45% 0.2 250) 0deg, oklch(55% 0.25 220) 90deg, oklch(65% 0.22 195) 180deg, oklch(70% 0.18 180) 270deg, oklch(45% 0.2 250) 360deg)",
      "conic-gradient(oklch(50% 0.22 240) 0deg, oklch(60% 0.2 210) 120deg, oklch(72% 0.15 185) 240deg, oklch(50% 0.22 240) 360deg)",
      "conic-gradient(oklch(55% 0.18 230) 0deg, oklch(68% 0.16 200) 180deg, oklch(75% 0.12 175) 360deg)",
    ],
    filter: "contrast(1.2) saturate(1.3)",
  },
  "framer-plugins": {
    gradients: [
      "conic-gradient(oklch(60% 0.3 30) 0deg, oklch(55% 0.35 350) 90deg, oklch(50% 0.32 320) 180deg, oklch(58% 0.28 15) 270deg, oklch(60% 0.3 30) 360deg)",
      "conic-gradient(oklch(65% 0.28 40) 0deg, oklch(52% 0.32 340) 120deg, oklch(58% 0.25 10) 240deg, oklch(65% 0.28 40) 360deg)",
      "conic-gradient(oklch(70% 0.22 50) 0deg, oklch(55% 0.28 355) 180deg, oklch(62% 0.2 25) 360deg)",
    ],
    filter: "contrast(1.35) saturate(1.5)",
  },
};

const speedConfig = {
  slow: { rotate: 30, morph: 15 },
  normal: { rotate: 20, morph: 10 },
  fast: { rotate: 12, morph: 6 },
};

const intensityConfig = {
  subtle: { contrast: 1.1, saturate: 1.1 },
  normal: { contrast: 1.2, saturate: 1.2 },
  vivid: { contrast: 1.4, saturate: 1.5 },
};

const positionConfig: Record<string, { x: string; y: string }> = {
  center: { x: "-50%", y: "-50%" },
  top: { x: "-50%", y: "-80%" },
  bottom: { x: "-50%", y: "-20%" },
  left: { x: "-80%", y: "-50%" },
  right: { x: "-20%", y: "-50%" },
  "top-left": { x: "-75%", y: "-75%" },
  "top-right": { x: "-25%", y: "-75%" },
  "bottom-left": { x: "-75%", y: "-25%" },
  "bottom-right": { x: "-25%", y: "-25%" },
};

// Memoized values - only recalculate when props change
const preset = computed(() => presets[props.preset]);
const speed = computed(() => speedConfig[props.speed]);
const intensity = computed(() => intensityConfig[props.intensity]);
const position = computed(() => positionConfig[props.position]);

// Gradients - direct access, no extra computed
const gradient1 = computed(() => preset.value.gradients[0]);
const gradient2 = computed(() => preset.value.gradients[1]);
const gradient3 = computed(() => preset.value.gradients[2]);

// Container style with CSS custom properties for animations
const containerStyle = computed(() => ({
  "--rotate-duration": `${speed.value.rotate}s`,
  "--morph-duration": `${speed.value.morph}s`,
  "--scale": props.scale,
  "--translate-x": position.value.x,
  "--translate-y": position.value.y,
  filter: `contrast(${intensity.value.contrast}) saturate(${intensity.value.saturate})`,
}));

// Blob styles - minimal, using CSS for animations
const blob1Style = computed(() => ({
  "--rotate-direction": "1",
  "--rotate-offset": "0s",
}));

const blob2Style = computed(() => ({
  "--rotate-direction": "-1",
  "--rotate-offset": `${speed.value.rotate * 0.33}s`,
}));

const blob3Style = computed(() => ({
  "--rotate-direction": "1",
  "--rotate-offset": `${speed.value.rotate * 0.66}s`,
}));
</script>

<style scoped>
.blob-container {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 172px;
  height: 172px;
  transform: translate(var(--translate-x), var(--translate-y)) scale(var(--scale));
  transform-origin: center;
  contain: layout style paint;
}

.blob {
  position: absolute;
  border-radius: 50%;
  will-change: transform;
  animation: blob-rotate var(--rotate-duration) linear infinite;
  animation-direction: normal;
  animation-delay: var(--rotate-offset, 0s);
}

.blob-1 {
  top: 36px;
  left: 36px;
  width: 100px;
  height: 100px;
  filter: blur(9px);
  animation-direction: normal;
}

.blob-2 {
  top: 48.5px;
  left: 48.5px;
  width: 75px;
  height: 75px;
  filter: blur(4px);
}

.blob-3 {
  top: 66.5px;
  left: 66.5px;
  width: 39px;
  height: 39px;
  filter: blur(4px);
  mix-blend-mode: overlay;
}

.blob-inner {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  will-change: transform, border-radius;
}

.blob-morph-1 {
  opacity: 0.8;
  animation: blob-morph-1 var(--morph-duration) ease-in-out infinite;
}

.blob-morph-2 {
  animation: blob-morph-2 var(--morph-duration) ease-in-out infinite;
  animation-delay: calc(var(--morph-duration) * 0.33);
}

.blob-morph-3 {
  animation: blob-morph-3 var(--morph-duration) ease-in-out infinite;
  animation-delay: calc(var(--morph-duration) * 0.66);
}

/* Rotation animation */
@keyframes blob-rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(calc(360deg * var(--rotate-direction, 1)));
  }
}

/* Morph animations - organic blob shapes */
@keyframes blob-morph-1 {
  0%,
  100% {
    transform: scale(0.85);
    border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
  }
  20% {
    transform: scale(1.05);
    border-radius: 25% 75% 65% 35% / 45% 55% 25% 75%;
  }
  40% {
    transform: scale(0.9);
    border-radius: 70% 30% 45% 55% / 35% 65% 55% 45%;
  }
  60% {
    transform: scale(1.1);
    border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%;
  }
  80% {
    transform: scale(0.95);
    border-radius: 55% 45% 35% 65% / 65% 35% 60% 40%;
  }
}

@keyframes blob-morph-2 {
  0%,
  100% {
    transform: scale(0.9);
    border-radius: 40% 60% 60% 40% / 70% 30% 70% 30%;
  }
  20% {
    transform: scale(1.1);
    border-radius: 65% 35% 40% 60% / 30% 70% 55% 45%;
  }
  40% {
    transform: scale(0.85);
    border-radius: 35% 65% 55% 45% / 60% 40% 35% 65%;
  }
  60% {
    transform: scale(1.05);
    border-radius: 60% 40% 30% 70% / 40% 60% 50% 50%;
  }
  80% {
    transform: scale(0.92);
    border-radius: 45% 55% 65% 35% / 55% 45% 40% 60%;
  }
}

@keyframes blob-morph-3 {
  0%,
  100% {
    transform: scale(0.95);
    border-radius: 50% 50% 40% 60% / 40% 60% 50% 50%;
  }
  20% {
    transform: scale(1.15);
    border-radius: 35% 65% 60% 40% / 55% 45% 35% 65%;
  }
  40% {
    transform: scale(0.88);
    border-radius: 65% 35% 35% 65% / 45% 55% 65% 35%;
  }
  60% {
    transform: scale(1.02);
    border-radius: 70% 30% 50% 50% / 30% 70% 40% 60%;
  }
  80% {
    transform: scale(1.08);
    border-radius: 40% 60% 55% 45% / 60% 40% 55% 45%;
  }
}

/* Pause animations when not visible */
.paused .blob,
.paused .blob-inner {
  animation-play-state: paused;
}

/* Reduced motion preference */
@media (prefers-reduced-motion: reduce) {
  .blob,
  .blob-inner {
    animation: none;
  }
}
</style>
