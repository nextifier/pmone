<template>
  <div
    ref="containerRef"
    class="pointer-events-none absolute inset-0 overflow-hidden"
    :class="containerClass"
  >
    <div
      :style="{
        position: 'absolute',
        top: '50%',
        left: '50%',
        width: '100%',
        height: '100%',
        transform: `translate(${positionOffset.x}, ${positionOffset.y})`,
        transformOrigin: 'center',
      }"
    >
      <!-- Animated state with Motion -->
      <template v-if="isAnimating">
        <Motion
          tag="div"
          :animate="blob1Animate"
          :transition="rotateTransition"
          :style="blob1BaseStyle"
        >
          <Motion
            tag="div"
            :animate="morph1Animate"
            :transition="morphTransition"
            :style="{ background: gradient1, ...morphBaseStyle }"
          />
        </Motion>

        <Motion
          tag="div"
          :animate="blob2Animate"
          :transition="rotateTransition"
          :style="blob2BaseStyle"
        >
          <Motion
            tag="div"
            :animate="morph2Animate"
            :transition="morphTransition"
            :style="{ background: gradient2, ...morphBaseStyle }"
          />
        </Motion>

        <Motion
          tag="div"
          :animate="blob3Animate"
          :transition="rotateTransition"
          :style="blob3BaseStyle"
        >
          <Motion
            tag="div"
            :animate="morph3Animate"
            :transition="morphTransition"
            :style="{ background: gradient3, ...morphBaseStyle, mixBlendMode: 'overlay' }"
          />
        </Motion>
      </template>

      <!-- Static state without Motion -->
      <template v-else>
        <div :style="blob1BaseStyle">
          <div :style="{ background: gradient1, ...morphBaseStyle, ...morph1StaticStyle }" />
        </div>

        <div :style="blob2BaseStyle">
          <div :style="{ background: gradient2, ...morphBaseStyle, ...morph2StaticStyle }" />
        </div>

        <div :style="blob3BaseStyle">
          <div
            :style="{
              background: gradient3,
              ...morphBaseStyle,
              ...morph3StaticStyle,
              mixBlendMode: 'overlay',
            }"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Motion } from "motion-v";

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
  | "framer-plugins"
  | "orange-sunset"
  | "orange-tangerine"
  | "orange-peach"
  | "orange-amber"
  | "orange-coral"
  | "orange-fire"
  | "orange-honey"
  | "orange-copper"
  | "orange-apricot"
  | "orange-flame"
  // New 20 diverse gradients
  | "electric-blue"
  | "deep-sea"
  | "northern-lights"
  | "bubblegum"
  | "rose-garden"
  | "toxic"
  | "grape"
  | "ice"
  | "desert"
  | "tropical"
  | "cherry"
  | "ocean-sunset"
  | "spring"
  | "cyberpunk"
  | "autumn"
  | "royal"
  | "aquamarine"
  | "volcano"
  | "pastel-dream"
  | "twilight";

interface Props {
  preset?: PresetName;
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
  containerClass?: string;
  animate?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  preset: "aurora",
  position: "center",
  speed: "normal",
  animate: false,
});

// Hover detection
const containerRef = ref<HTMLElement | null>(null);
const isHovered = ref(false);

// Animation state: animate if prop is true OR if hovered
const isAnimating = computed(() => props.animate || isHovered.value);

onMounted(() => {
  if (!containerRef.value) return;

  const parent = containerRef.value.parentElement;
  if (!parent) return;

  const handleMouseEnter = () => {
    isHovered.value = true;
  };

  const handleMouseLeave = () => {
    isHovered.value = false;
  };

  parent.addEventListener("mouseenter", handleMouseEnter);
  parent.addEventListener("mouseleave", handleMouseLeave);

  onUnmounted(() => {
    parent.removeEventListener("mouseenter", handleMouseEnter);
    parent.removeEventListener("mouseleave", handleMouseLeave);
  });
});

// Blob configuration interface
interface BlobConfig {
  size: string;
  top: string;
  left: string;
  blur: string;
}

interface PresetConfig {
  gradients: string[];
  blobs?: [BlobConfig, BlobConfig, BlobConfig];
}

// Default blob configurations
const defaultBlobs: [BlobConfig, BlobConfig, BlobConfig] = [
  { size: "130%", top: "-15%", left: "-15%", blur: "40px" },
  { size: "100%", top: "0%", left: "0%", blur: "25px" },
  { size: "70%", top: "15%", left: "15%", blur: "15px" },
];

// Preset configurations - vibrant colors without filter
const presets: Record<PresetName, PresetConfig> = {
  aurora: {
    gradients: [
      "conic-gradient(oklch(70% 0.42 330) 0deg, oklch(55% 0.45 290) 60deg, oklch(45% 0.4 260) 120deg, oklch(60% 0.38 220) 180deg, oklch(52% 0.42 280) 240deg, oklch(65% 0.4 310) 300deg, oklch(70% 0.42 330) 360deg)",
      "conic-gradient(oklch(75% 0.38 340) 0deg, oklch(55% 0.42 280) 120deg, oklch(65% 0.35 230) 240deg, oklch(75% 0.38 340) 360deg)",
      "conic-gradient(oklch(80% 0.32 350) 0deg, oklch(60% 0.38 270) 180deg, oklch(70% 0.3 200) 360deg)",
    ],
  },
  sunset: {
    gradients: [
      "conic-gradient(oklch(70% 0.45 35) 0deg, oklch(60% 0.48 20) 60deg, oklch(50% 0.44 5) 120deg, oklch(65% 0.42 50) 180deg, oklch(55% 0.46 25) 240deg, oklch(68% 0.43 40) 300deg, oklch(70% 0.45 35) 360deg)",
      "conic-gradient(oklch(75% 0.4 45) 0deg, oklch(55% 0.45 15) 120deg, oklch(65% 0.38 55) 240deg, oklch(75% 0.4 45) 360deg)",
      "conic-gradient(oklch(80% 0.35 50) 0deg, oklch(58% 0.42 25) 180deg, oklch(72% 0.32 60) 360deg)",
    ],
  },
  ocean: {
    gradients: [
      "conic-gradient(oklch(50% 0.38 240) 0deg, oklch(60% 0.42 220) 60deg, oklch(70% 0.38 200) 120deg, oklch(78% 0.32 185) 180deg, oklch(65% 0.36 210) 240deg, oklch(55% 0.4 230) 300deg, oklch(50% 0.38 240) 360deg)",
      "conic-gradient(oklch(55% 0.4 235) 0deg, oklch(72% 0.35 195) 120deg, oklch(62% 0.38 215) 240deg, oklch(55% 0.4 235) 360deg)",
      "conic-gradient(oklch(60% 0.35 230) 0deg, oklch(78% 0.3 190) 180deg, oklch(68% 0.32 205) 360deg)",
    ],
  },
  forest: {
    gradients: [
      "conic-gradient(oklch(55% 0.4 150) 0deg, oklch(62% 0.42 135) 60deg, oklch(48% 0.38 165) 120deg, oklch(58% 0.4 145) 180deg, oklch(52% 0.42 155) 240deg, oklch(60% 0.38 140) 300deg, oklch(55% 0.4 150) 360deg)",
      "conic-gradient(oklch(62% 0.38 155) 0deg, oklch(52% 0.42 140) 120deg, oklch(58% 0.35 165) 240deg, oklch(62% 0.38 155) 360deg)",
      "conic-gradient(oklch(68% 0.34 148) 0deg, oklch(55% 0.38 160) 180deg, oklch(62% 0.32 138) 360deg)",
    ],
  },
  neon: {
    gradients: [
      "conic-gradient(oklch(78% 0.48 330) 0deg, oklch(72% 0.5 300) 60deg, oklch(82% 0.45 200) 120deg, oklch(88% 0.42 100) 180deg, oklch(75% 0.48 280) 240deg, oklch(80% 0.46 320) 300deg, oklch(78% 0.48 330) 360deg)",
      "conic-gradient(oklch(85% 0.45 340) 0deg, oklch(78% 0.48 260) 120deg, oklch(90% 0.4 120) 240deg, oklch(85% 0.45 340) 360deg)",
      "conic-gradient(oklch(90% 0.4 350) 0deg, oklch(82% 0.45 220) 180deg, oklch(92% 0.35 150) 360deg)",
    ],
  },
  candy: {
    gradients: [
      "conic-gradient(oklch(80% 0.4 350) 0deg, oklch(75% 0.42 320) 60deg, oklch(78% 0.38 280) 120deg, oklch(85% 0.35 250) 180deg, oklch(80% 0.4 300) 240deg, oklch(82% 0.38 340) 300deg, oklch(80% 0.4 350) 360deg)",
      "conic-gradient(oklch(85% 0.38 355) 0deg, oklch(78% 0.4 290) 120deg, oklch(88% 0.32 240) 240deg, oklch(85% 0.38 355) 360deg)",
      "conic-gradient(oklch(90% 0.34 5) 0deg, oklch(82% 0.38 270) 180deg, oklch(92% 0.3 230) 360deg)",
    ],
  },
  midnight: {
    gradients: [
      "conic-gradient(oklch(35% 0.38 280) 0deg, oklch(28% 0.42 260) 60deg, oklch(40% 0.35 300) 120deg, oklch(32% 0.4 240) 180deg, oklch(38% 0.38 270) 240deg, oklch(34% 0.4 290) 300deg, oklch(35% 0.38 280) 360deg)",
      "conic-gradient(oklch(40% 0.35 285) 0deg, oklch(32% 0.4 250) 120deg, oklch(36% 0.32 295) 240deg, oklch(40% 0.35 285) 360deg)",
      "conic-gradient(oklch(45% 0.3 278) 0deg, oklch(35% 0.36 265) 180deg, oklch(42% 0.28 288) 360deg)",
    ],
  },
  ember: {
    gradients: [
      "conic-gradient(oklch(68% 0.45 35) 0deg, oklch(58% 0.48 15) 60deg, oklch(50% 0.46 355) 120deg, oklch(62% 0.44 25) 180deg, oklch(54% 0.47 5) 240deg, oklch(65% 0.45 30) 300deg, oklch(68% 0.45 35) 360deg)",
      "conic-gradient(oklch(72% 0.42 40) 0deg, oklch(55% 0.46 10) 120deg, oklch(62% 0.4 350) 240deg, oklch(72% 0.42 40) 360deg)",
      "conic-gradient(oklch(78% 0.38 45) 0deg, oklch(58% 0.44 20) 180deg, oklch(68% 0.35 0) 360deg)",
    ],
  },
  cosmic: {
    gradients: [
      "conic-gradient(oklch(58% 0.45 300) 0deg, oklch(48% 0.48 330) 60deg, oklch(55% 0.42 270) 120deg, oklch(62% 0.45 350) 180deg, oklch(52% 0.46 310) 240deg, oklch(56% 0.44 285) 300deg, oklch(58% 0.45 300) 360deg)",
      "conic-gradient(oklch(62% 0.42 310) 0deg, oklch(52% 0.46 340) 120deg, oklch(58% 0.4 275) 240deg, oklch(62% 0.42 310) 360deg)",
      "conic-gradient(oklch(68% 0.38 305) 0deg, oklch(55% 0.44 335) 180deg, oklch(62% 0.35 280) 360deg)",
    ],
  },
  mint: {
    gradients: [
      "conic-gradient(oklch(78% 0.38 175) 0deg, oklch(72% 0.42 160) 60deg, oklch(82% 0.36 190) 120deg, oklch(75% 0.4 150) 180deg, oklch(80% 0.38 180) 240deg, oklch(74% 0.4 165) 300deg, oklch(78% 0.38 175) 360deg)",
      "conic-gradient(oklch(82% 0.36 180) 0deg, oklch(74% 0.4 155) 120deg, oklch(78% 0.32 195) 240deg, oklch(82% 0.36 180) 360deg)",
      "conic-gradient(oklch(86% 0.32 178) 0deg, oklch(76% 0.38 162) 180deg, oklch(82% 0.3 188) 360deg)",
    ],
  },
  lavender: {
    gradients: [
      "conic-gradient(oklch(75% 0.38 295) 0deg, oklch(68% 0.42 275) 60deg, oklch(78% 0.35 315) 120deg, oklch(72% 0.4 260) 180deg, oklch(76% 0.38 285) 240deg, oklch(70% 0.4 305) 300deg, oklch(75% 0.38 295) 360deg)",
      "conic-gradient(oklch(80% 0.35 300) 0deg, oklch(70% 0.4 270) 120deg, oklch(76% 0.32 320) 240deg, oklch(80% 0.35 300) 360deg)",
      "conic-gradient(oklch(84% 0.3 298) 0deg, oklch(74% 0.36 278) 180deg, oklch(80% 0.28 312) 360deg)",
    ],
  },
  gold: {
    gradients: [
      "conic-gradient(oklch(78% 0.4 85) 0deg, oklch(68% 0.44 70) 60deg, oklch(75% 0.38 95) 120deg, oklch(65% 0.42 55) 180deg, oklch(72% 0.4 80) 240deg, oklch(80% 0.38 90) 300deg, oklch(78% 0.4 85) 360deg)",
      "conic-gradient(oklch(82% 0.38 88) 0deg, oklch(68% 0.42 65) 120deg, oklch(76% 0.34 98) 240deg, oklch(82% 0.38 88) 360deg)",
      "conic-gradient(oklch(86% 0.34 86) 0deg, oklch(72% 0.4 72) 180deg, oklch(80% 0.3 95) 360deg)",
    ],
  },
  "framer-wireframer": {
    gradients: [
      "conic-gradient(oklch(70% 0.42 330) 0deg, oklch(55% 0.45 300) 90deg, oklch(48% 0.4 270) 180deg, oklch(62% 0.35 240) 270deg, oklch(70% 0.42 330) 360deg)",
      "conic-gradient(oklch(76% 0.38 320) 0deg, oklch(58% 0.4 280) 120deg, oklch(68% 0.32 230) 240deg, oklch(76% 0.38 320) 360deg)",
      "conic-gradient(oklch(82% 0.3 340) 0deg, oklch(65% 0.35 260) 180deg, oklch(74% 0.28 210) 360deg)",
    ],
  },
  "framer-translate": {
    gradients: [
      "conic-gradient(oklch(48% 0.3 250) 0deg, oklch(58% 0.35 220) 90deg, oklch(70% 0.32 195) 180deg, oklch(78% 0.28 180) 270deg, oklch(48% 0.3 250) 360deg)",
      "conic-gradient(oklch(55% 0.32 240) 0deg, oklch(65% 0.3 210) 120deg, oklch(78% 0.25 185) 240deg, oklch(55% 0.32 240) 360deg)",
      "conic-gradient(oklch(60% 0.28 230) 0deg, oklch(72% 0.26 200) 180deg, oklch(82% 0.22 175) 360deg)",
    ],
  },
  "framer-plugins": {
    gradients: [
      "conic-gradient(oklch(65% 0.4 30) 0deg, oklch(58% 0.45 350) 90deg, oklch(52% 0.42 320) 180deg, oklch(62% 0.38 15) 270deg, oklch(65% 0.4 30) 360deg)",
      "conic-gradient(oklch(72% 0.38 40) 0deg, oklch(55% 0.42 340) 120deg, oklch(62% 0.35 10) 240deg, oklch(72% 0.38 40) 360deg)",
      "conic-gradient(oklch(78% 0.32 50) 0deg, oklch(58% 0.38 355) 180deg, oklch(68% 0.3 25) 360deg)",
    ],
  },
  // Orange gradient presets with varied blob configurations and contrasting colors
  "orange-sunset": {
    // Blob 1: Deep red-orange, Blob 2: Warm pink-magenta, Blob 3: Golden yellow
    gradients: [
      "conic-gradient(oklch(65% 0.48 25) 0deg, oklch(55% 0.5 15) 60deg, oklch(48% 0.46 5) 120deg, oklch(58% 0.48 20) 180deg, oklch(52% 0.5 10) 240deg, oklch(62% 0.47 28) 300deg, oklch(65% 0.48 25) 360deg)",
      "conic-gradient(oklch(72% 0.42 350) 0deg, oklch(65% 0.45 340) 90deg, oklch(58% 0.4 330) 180deg, oklch(68% 0.43 345) 270deg, oklch(72% 0.42 350) 360deg)",
      "conic-gradient(oklch(85% 0.44 75) 0deg, oklch(78% 0.46 65) 120deg, oklch(82% 0.42 80) 240deg, oklch(85% 0.44 75) 360deg)",
    ],
    blobs: [
      { size: "150%", top: "-25%", left: "-25%", blur: "50px" },
      { size: "110%", top: "-5%", left: "-5%", blur: "35px" },
      { size: "80%", top: "10%", left: "10%", blur: "20px" },
    ],
  },
  "orange-tangerine": {
    // Blob 1: Vivid orange, Blob 2: Coral pink, Blob 3: Lime yellow
    gradients: [
      "conic-gradient(oklch(75% 0.5 50) 0deg, oklch(68% 0.52 42) 60deg, oklch(72% 0.48 55) 120deg, oklch(65% 0.5 45) 180deg, oklch(78% 0.47 52) 240deg, oklch(70% 0.49 48) 300deg, oklch(75% 0.5 50) 360deg)",
      "conic-gradient(oklch(78% 0.42 20) 0deg, oklch(70% 0.45 10) 90deg, oklch(75% 0.4 25) 180deg, oklch(72% 0.43 15) 270deg, oklch(78% 0.42 20) 360deg)",
      "conic-gradient(oklch(88% 0.45 95) 0deg, oklch(82% 0.48 85) 120deg, oklch(85% 0.43 100) 240deg, oklch(88% 0.45 95) 360deg)",
    ],
    blobs: [
      { size: "120%", top: "-10%", left: "-10%", blur: "45px" },
      { size: "140%", top: "-20%", left: "-20%", blur: "30px" },
      { size: "60%", top: "20%", left: "20%", blur: "18px" },
    ],
  },
  "orange-peach": {
    // Blob 1: Warm peach-orange, Blob 2: Soft rose pink, Blob 3: Creamy coral
    gradients: [
      "conic-gradient(oklch(82% 0.38 45) 0deg, oklch(78% 0.4 38) 60deg, oklch(85% 0.35 50) 120deg, oklch(80% 0.38 42) 180deg, oklch(88% 0.32 52) 240deg, oklch(84% 0.36 48) 300deg, oklch(82% 0.38 45) 360deg)",
      "conic-gradient(oklch(85% 0.35 15) 0deg, oklch(80% 0.38 8) 90deg, oklch(88% 0.32 20) 180deg, oklch(82% 0.36 12) 270deg, oklch(85% 0.35 15) 360deg)",
      "conic-gradient(oklch(80% 0.4 30) 0deg, oklch(75% 0.42 22) 120deg, oklch(82% 0.38 35) 240deg, oklch(80% 0.4 30) 360deg)",
    ],
    blobs: [
      { size: "130%", top: "-15%", left: "-15%", blur: "55px" },
      { size: "100%", top: "0%", left: "0%", blur: "40px" },
      { size: "80%", top: "10%", left: "10%", blur: "25px" },
    ],
  },
  "orange-amber": {
    // Blob 1: Deep amber, Blob 2: Burnt sienna/brown, Blob 3: Warm gold
    gradients: [
      "conic-gradient(oklch(62% 0.48 60) 0deg, oklch(55% 0.5 50) 60deg, oklch(58% 0.46 65) 120deg, oklch(52% 0.48 55) 180deg, oklch(65% 0.45 62) 240deg, oklch(58% 0.47 58) 300deg, oklch(62% 0.48 60) 360deg)",
      "conic-gradient(oklch(48% 0.4 35) 0deg, oklch(42% 0.42 25) 90deg, oklch(45% 0.38 40) 180deg, oklch(40% 0.4 30) 270deg, oklch(48% 0.4 35) 360deg)",
      "conic-gradient(oklch(82% 0.45 85) 0deg, oklch(75% 0.48 75) 120deg, oklch(78% 0.43 90) 240deg, oklch(82% 0.45 85) 360deg)",
    ],
    blobs: [
      { size: "160%", top: "-30%", left: "-30%", blur: "55px" },
      { size: "85%", top: "8%", left: "8%", blur: "22px" },
      { size: "75%", top: "12%", left: "12%", blur: "12px" },
    ],
  },
  "orange-coral": {
    // Blob 1: Vibrant coral, Blob 2: Deep magenta-pink, Blob 3: Soft salmon
    gradients: [
      "conic-gradient(oklch(72% 0.46 28) 0deg, oklch(65% 0.48 18) 60deg, oklch(78% 0.42 32) 120deg, oklch(68% 0.46 22) 180deg, oklch(75% 0.44 30) 240deg, oklch(70% 0.47 25) 300deg, oklch(72% 0.46 28) 360deg)",
      "conic-gradient(oklch(62% 0.48 345) 0deg, oklch(55% 0.5 335) 90deg, oklch(58% 0.46 350) 180deg, oklch(52% 0.48 340) 270deg, oklch(62% 0.48 345) 360deg)",
      "conic-gradient(oklch(82% 0.38 35) 0deg, oklch(78% 0.4 25) 120deg, oklch(85% 0.35 40) 240deg, oklch(82% 0.38 35) 360deg)",
    ],
    blobs: [
      { size: "140%", top: "-20%", left: "-20%", blur: "45px" },
      { size: "110%", top: "-5%", left: "-5%", blur: "35px" },
      { size: "70%", top: "15%", left: "15%", blur: "20px" },
    ],
  },
  "orange-fire": {
    // Blob 1: Intense red, Blob 2: Deep orange, Blob 3: Bright yellow
    gradients: [
      "conic-gradient(oklch(58% 0.52 15) 0deg, oklch(50% 0.54 5) 60deg, oklch(45% 0.5 0) 120deg, oklch(52% 0.52 10) 180deg, oklch(48% 0.53 3) 240deg, oklch(55% 0.51 12) 300deg, oklch(58% 0.52 15) 360deg)",
      "conic-gradient(oklch(68% 0.5 45) 0deg, oklch(60% 0.52 35) 90deg, oklch(65% 0.48 50) 180deg, oklch(62% 0.5 40) 270deg, oklch(68% 0.5 45) 360deg)",
      "conic-gradient(oklch(92% 0.48 95) 0deg, oklch(88% 0.5 85) 120deg, oklch(90% 0.46 100) 240deg, oklch(92% 0.48 95) 360deg)",
    ],
    blobs: [
      { size: "140%", top: "-20%", left: "-20%", blur: "30px" },
      { size: "100%", top: "0%", left: "0%", blur: "45px" },
      { size: "120%", top: "-10%", left: "-10%", blur: "20px" },
    ],
  },
  "orange-honey": {
    // Blob 1: Golden honey, Blob 2: Warm brown, Blob 3: Cream white
    gradients: [
      "conic-gradient(oklch(78% 0.45 70) 0deg, oklch(72% 0.48 60) 60deg, oklch(82% 0.42 75) 120deg, oklch(75% 0.46 65) 180deg, oklch(85% 0.4 78) 240deg, oklch(80% 0.44 68) 300deg, oklch(78% 0.45 70) 360deg)",
      "conic-gradient(oklch(52% 0.38 50) 0deg, oklch(45% 0.4 40) 90deg, oklch(48% 0.36 55) 180deg, oklch(42% 0.38 45) 270deg, oklch(52% 0.38 50) 360deg)",
      "conic-gradient(oklch(95% 0.18 85) 0deg, oklch(92% 0.22 75) 120deg, oklch(94% 0.16 90) 240deg, oklch(95% 0.18 85) 360deg)",
    ],
    blobs: [
      { size: "95%", top: "2%", left: "2%", blur: "55px" },
      { size: "120%", top: "-10%", left: "-10%", blur: "35px" },
      { size: "85%", top: "8%", left: "8%", blur: "18px" },
    ],
  },
  "orange-copper": {
    // Blob 1: Deep copper/bronze, Blob 2: Dark burgundy, Blob 3: Bright rose gold
    gradients: [
      "conic-gradient(oklch(52% 0.42 45) 0deg, oklch(45% 0.44 38) 60deg, oklch(48% 0.4 50) 120deg, oklch(42% 0.42 42) 180deg, oklch(55% 0.38 48) 240deg, oklch(48% 0.43 44) 300deg, oklch(52% 0.42 45) 360deg)",
      "conic-gradient(oklch(38% 0.38 15) 0deg, oklch(32% 0.4 8) 90deg, oklch(35% 0.36 20) 180deg, oklch(30% 0.38 12) 270deg, oklch(38% 0.38 15) 360deg)",
      "conic-gradient(oklch(75% 0.4 30) 0deg, oklch(68% 0.43 22) 120deg, oklch(72% 0.38 35) 240deg, oklch(75% 0.4 30) 360deg)",
    ],
    blobs: [
      { size: "150%", top: "-25%", left: "-25%", blur: "55px" },
      { size: "120%", top: "-10%", left: "-10%", blur: "40px" },
      { size: "70%", top: "15%", left: "15%", blur: "18px" },
    ],
  },
  "orange-apricot": {
    // Blob 1: Warm apricot orange, Blob 2: Soft peachy pink, Blob 3: Creamy yellow
    gradients: [
      "conic-gradient(oklch(80% 0.42 50) 0deg, oklch(75% 0.44 42) 60deg, oklch(82% 0.38 55) 120deg, oklch(78% 0.42 48) 180deg, oklch(85% 0.36 58) 240deg, oklch(82% 0.4 52) 300deg, oklch(80% 0.42 50) 360deg)",
      "conic-gradient(oklch(85% 0.36 25) 0deg, oklch(80% 0.38 18) 90deg, oklch(88% 0.33 30) 180deg, oklch(82% 0.36 22) 270deg, oklch(85% 0.36 25) 360deg)",
      "conic-gradient(oklch(90% 0.35 75) 0deg, oklch(86% 0.38 65) 120deg, oklch(92% 0.32 80) 240deg, oklch(90% 0.35 75) 360deg)",
    ],
    blobs: [
      { size: "135%", top: "-18%", left: "-18%", blur: "50px" },
      { size: "105%", top: "-2%", left: "-2%", blur: "35px" },
      { size: "75%", top: "12%", left: "12%", blur: "22px" },
    ],
  },
  "orange-flame": {
    // Blob 1: Hot orange-red, Blob 2: Deep crimson red, Blob 3: Bright golden yellow
    gradients: [
      "conic-gradient(oklch(68% 0.5 38) 0deg, oklch(62% 0.52 28) 60deg, oklch(55% 0.48 18) 120deg, oklch(65% 0.5 32) 180deg, oklch(58% 0.51 22) 240deg, oklch(70% 0.48 42) 300deg, oklch(68% 0.5 38) 360deg)",
      "conic-gradient(oklch(48% 0.48 15) 0deg, oklch(42% 0.5 8) 90deg, oklch(45% 0.46 20) 180deg, oklch(40% 0.48 12) 270deg, oklch(48% 0.48 15) 360deg)",
      "conic-gradient(oklch(92% 0.48 90) 0deg, oklch(88% 0.5 80) 120deg, oklch(95% 0.45 95) 240deg, oklch(92% 0.48 90) 360deg)",
    ],
    blobs: [
      { size: "130%", top: "-15%", left: "-15%", blur: "35px" },
      { size: "145%", top: "-22%", left: "-22%", blur: "50px" },
      { size: "75%", top: "12%", left: "12%", blur: "18px" },
    ],
  },

  // ========== NEW 20 DIVERSE GRADIENTS ==========

  "electric-blue": {
    // Blob 1: Vibrant electric blue, Blob 2: Deep navy, Blob 3: Bright cyan
    gradients: [
      "conic-gradient(oklch(65% 0.45 250) 0deg, oklch(58% 0.48 240) 60deg, oklch(70% 0.42 260) 120deg, oklch(62% 0.46 245) 180deg, oklch(68% 0.44 255) 240deg, oklch(60% 0.47 248) 300deg, oklch(65% 0.45 250) 360deg)",
      "conic-gradient(oklch(35% 0.4 255) 0deg, oklch(28% 0.42 245) 90deg, oklch(32% 0.38 260) 180deg, oklch(25% 0.4 250) 270deg, oklch(35% 0.4 255) 360deg)",
      "conic-gradient(oklch(85% 0.4 200) 0deg, oklch(80% 0.42 190) 120deg, oklch(88% 0.38 210) 240deg, oklch(85% 0.4 200) 360deg)",
    ],
    blobs: [
      { size: "140%", top: "-20%", left: "-20%", blur: "45px" },
      { size: "160%", top: "-30%", left: "-30%", blur: "60px" },
      { size: "70%", top: "15%", left: "15%", blur: "20px" },
    ],
  },
  "deep-sea": {
    // Blob 1: Deep ocean blue, Blob 2: Bioluminescent teal, Blob 3: Dark abyss
    gradients: [
      "conic-gradient(oklch(40% 0.35 230) 0deg, oklch(35% 0.38 220) 60deg, oklch(45% 0.32 240) 120deg, oklch(38% 0.36 225) 180deg, oklch(42% 0.34 235) 240deg, oklch(36% 0.37 228) 300deg, oklch(40% 0.35 230) 360deg)",
      "conic-gradient(oklch(70% 0.4 185) 0deg, oklch(65% 0.42 175) 90deg, oklch(75% 0.38 195) 180deg, oklch(68% 0.4 180) 270deg, oklch(70% 0.4 185) 360deg)",
      "conic-gradient(oklch(22% 0.25 250) 0deg, oklch(18% 0.28 240) 120deg, oklch(25% 0.22 260) 240deg, oklch(22% 0.25 250) 360deg)",
    ],
    blobs: [
      { size: "150%", top: "-25%", left: "-25%", blur: "55px" },
      { size: "80%", top: "10%", left: "10%", blur: "25px" },
      { size: "170%", top: "-35%", left: "-35%", blur: "70px" },
    ],
  },
  "northern-lights": {
    // Blob 1: Aurora green, Blob 2: Purple shimmer, Blob 3: Cyan glow
    gradients: [
      "conic-gradient(oklch(75% 0.45 145) 0deg, oklch(68% 0.48 135) 60deg, oklch(80% 0.42 155) 120deg, oklch(72% 0.46 140) 180deg, oklch(78% 0.44 150) 240deg, oklch(70% 0.47 142) 300deg, oklch(75% 0.45 145) 360deg)",
      "conic-gradient(oklch(55% 0.42 300) 0deg, oklch(48% 0.45 290) 90deg, oklch(60% 0.4 310) 180deg, oklch(52% 0.43 295) 270deg, oklch(55% 0.42 300) 360deg)",
      "conic-gradient(oklch(82% 0.38 195) 0deg, oklch(78% 0.4 185) 120deg, oklch(85% 0.35 205) 240deg, oklch(82% 0.38 195) 360deg)",
    ],
    blobs: [
      { size: "135%", top: "-18%", left: "-18%", blur: "50px" },
      { size: "120%", top: "-10%", left: "-10%", blur: "40px" },
      { size: "90%", top: "5%", left: "5%", blur: "30px" },
    ],
  },
  "bubblegum": {
    // Blob 1: Hot pink, Blob 2: Light pink, Blob 3: Magenta pop
    gradients: [
      "conic-gradient(oklch(72% 0.45 350) 0deg, oklch(65% 0.48 340) 60deg, oklch(78% 0.42 355) 120deg, oklch(68% 0.46 345) 180deg, oklch(75% 0.44 352) 240deg, oklch(70% 0.47 348) 300deg, oklch(72% 0.45 350) 360deg)",
      "conic-gradient(oklch(88% 0.32 5) 0deg, oklch(85% 0.35 355) 90deg, oklch(90% 0.3 10) 180deg, oklch(86% 0.33 0) 270deg, oklch(88% 0.32 5) 360deg)",
      "conic-gradient(oklch(62% 0.48 320) 0deg, oklch(55% 0.5 310) 120deg, oklch(68% 0.45 330) 240deg, oklch(62% 0.48 320) 360deg)",
    ],
    blobs: [
      { size: "125%", top: "-12%", left: "-12%", blur: "42px" },
      { size: "140%", top: "-20%", left: "-20%", blur: "55px" },
      { size: "65%", top: "18%", left: "18%", blur: "18px" },
    ],
  },
  "rose-garden": {
    // Blob 1: Deep rose, Blob 2: Soft blush, Blob 3: Crimson accent
    gradients: [
      "conic-gradient(oklch(58% 0.42 10) 0deg, oklch(52% 0.45 0) 60deg, oklch(62% 0.4 18) 120deg, oklch(55% 0.43 5) 180deg, oklch(60% 0.41 15) 240deg, oklch(54% 0.44 8) 300deg, oklch(58% 0.42 10) 360deg)",
      "conic-gradient(oklch(85% 0.3 15) 0deg, oklch(82% 0.33 8) 90deg, oklch(88% 0.28 20) 180deg, oklch(84% 0.31 12) 270deg, oklch(85% 0.3 15) 360deg)",
      "conic-gradient(oklch(48% 0.48 358) 0deg, oklch(42% 0.5 350) 120deg, oklch(52% 0.45 5) 240deg, oklch(48% 0.48 358) 360deg)",
    ],
    blobs: [
      { size: "130%", top: "-15%", left: "-15%", blur: "48px" },
      { size: "155%", top: "-28%", left: "-28%", blur: "65px" },
      { size: "60%", top: "20%", left: "20%", blur: "15px" },
    ],
  },
  "toxic": {
    // Blob 1: Neon green, Blob 2: Acid yellow, Blob 3: Dark green glow
    gradients: [
      "conic-gradient(oklch(80% 0.5 130) 0deg, oklch(75% 0.52 120) 60deg, oklch(85% 0.48 140) 120deg, oklch(78% 0.5 125) 180deg, oklch(82% 0.49 135) 240deg, oklch(76% 0.51 128) 300deg, oklch(80% 0.5 130) 360deg)",
      "conic-gradient(oklch(92% 0.48 105) 0deg, oklch(88% 0.5 95) 90deg, oklch(95% 0.45 110) 180deg, oklch(90% 0.48 100) 270deg, oklch(92% 0.48 105) 360deg)",
      "conic-gradient(oklch(45% 0.4 145) 0deg, oklch(40% 0.42 135) 120deg, oklch(50% 0.38 155) 240deg, oklch(45% 0.4 145) 360deg)",
    ],
    blobs: [
      { size: "120%", top: "-10%", left: "-10%", blur: "35px" },
      { size: "95%", top: "2%", left: "2%", blur: "28px" },
      { size: "150%", top: "-25%", left: "-25%", blur: "55px" },
    ],
  },
  "grape": {
    // Blob 1: Rich purple, Blob 2: Deep violet, Blob 3: Light lavender
    gradients: [
      "conic-gradient(oklch(50% 0.42 305) 0deg, oklch(45% 0.45 295) 60deg, oklch(55% 0.4 315) 120deg, oklch(48% 0.43 300) 180deg, oklch(52% 0.41 310) 240deg, oklch(46% 0.44 302) 300deg, oklch(50% 0.42 305) 360deg)",
      "conic-gradient(oklch(35% 0.45 290) 0deg, oklch(30% 0.48 280) 90deg, oklch(40% 0.42 300) 180deg, oklch(32% 0.46 285) 270deg, oklch(35% 0.45 290) 360deg)",
      "conic-gradient(oklch(82% 0.3 295) 0deg, oklch(78% 0.33 285) 120deg, oklch(85% 0.28 305) 240deg, oklch(82% 0.3 295) 360deg)",
    ],
    blobs: [
      { size: "135%", top: "-18%", left: "-18%", blur: "50px" },
      { size: "110%", top: "-5%", left: "-5%", blur: "38px" },
      { size: "85%", top: "8%", left: "8%", blur: "22px" },
    ],
  },
  "ice": {
    // Blob 1: Icy blue, Blob 2: Frost white, Blob 3: Deep glacier
    gradients: [
      "conic-gradient(oklch(82% 0.28 220) 0deg, oklch(78% 0.3 210) 60deg, oklch(85% 0.25 230) 120deg, oklch(80% 0.28 215) 180deg, oklch(84% 0.26 225) 240deg, oklch(79% 0.29 218) 300deg, oklch(82% 0.28 220) 360deg)",
      "conic-gradient(oklch(95% 0.12 220) 0deg, oklch(92% 0.15 210) 90deg, oklch(97% 0.1 230) 180deg, oklch(94% 0.13 215) 270deg, oklch(95% 0.12 220) 360deg)",
      "conic-gradient(oklch(55% 0.3 235) 0deg, oklch(50% 0.33 225) 120deg, oklch(60% 0.28 245) 240deg, oklch(55% 0.3 235) 360deg)",
    ],
    blobs: [
      { size: "145%", top: "-22%", left: "-22%", blur: "60px" },
      { size: "125%", top: "-12%", left: "-12%", blur: "45px" },
      { size: "75%", top: "12%", left: "12%", blur: "25px" },
    ],
  },
  "desert": {
    // Blob 1: Sandy beige, Blob 2: Terracotta, Blob 3: Golden sand
    gradients: [
      "conic-gradient(oklch(78% 0.25 70) 0deg, oklch(74% 0.28 60) 60deg, oklch(82% 0.22 80) 120deg, oklch(76% 0.26 65) 180deg, oklch(80% 0.24 75) 240deg, oklch(75% 0.27 68) 300deg, oklch(78% 0.25 70) 360deg)",
      "conic-gradient(oklch(58% 0.38 40) 0deg, oklch(52% 0.4 32) 90deg, oklch(62% 0.36 48) 180deg, oklch(55% 0.39 36) 270deg, oklch(58% 0.38 40) 360deg)",
      "conic-gradient(oklch(88% 0.32 85) 0deg, oklch(84% 0.35 75) 120deg, oklch(90% 0.3 92) 240deg, oklch(88% 0.32 85) 360deg)",
    ],
    blobs: [
      { size: "140%", top: "-20%", left: "-20%", blur: "52px" },
      { size: "100%", top: "0%", left: "0%", blur: "35px" },
      { size: "80%", top: "10%", left: "10%", blur: "22px" },
    ],
  },
  "tropical": {
    // Blob 1: Tropical teal, Blob 2: Mango orange, Blob 3: Palm green
    gradients: [
      "conic-gradient(oklch(72% 0.38 190) 0deg, oklch(68% 0.4 180) 60deg, oklch(76% 0.36 200) 120deg, oklch(70% 0.38 185) 180deg, oklch(74% 0.37 195) 240deg, oklch(69% 0.39 188) 300deg, oklch(72% 0.38 190) 360deg)",
      "conic-gradient(oklch(78% 0.48 55) 0deg, oklch(72% 0.5 45) 90deg, oklch(82% 0.45 62) 180deg, oklch(75% 0.48 50) 270deg, oklch(78% 0.48 55) 360deg)",
      "conic-gradient(oklch(62% 0.4 145) 0deg, oklch(58% 0.42 135) 120deg, oklch(66% 0.38 155) 240deg, oklch(62% 0.4 145) 360deg)",
    ],
    blobs: [
      { size: "130%", top: "-15%", left: "-15%", blur: "45px" },
      { size: "115%", top: "-8%", left: "-8%", blur: "35px" },
      { size: "95%", top: "2%", left: "2%", blur: "28px" },
    ],
  },
  "cherry": {
    // Blob 1: Cherry red, Blob 2: Dark burgundy, Blob 3: Bright pink
    gradients: [
      "conic-gradient(oklch(55% 0.48 15) 0deg, oklch(50% 0.5 8) 60deg, oklch(60% 0.46 22) 120deg, oklch(52% 0.48 12) 180deg, oklch(58% 0.47 18) 240deg, oklch(51% 0.49 10) 300deg, oklch(55% 0.48 15) 360deg)",
      "conic-gradient(oklch(32% 0.42 5) 0deg, oklch(28% 0.44 358) 90deg, oklch(36% 0.4 12) 180deg, oklch(30% 0.43 2) 270deg, oklch(32% 0.42 5) 360deg)",
      "conic-gradient(oklch(78% 0.45 350) 0deg, oklch(72% 0.48 342) 120deg, oklch(82% 0.42 358) 240deg, oklch(78% 0.45 350) 360deg)",
    ],
    blobs: [
      { size: "125%", top: "-12%", left: "-12%", blur: "42px" },
      { size: "155%", top: "-28%", left: "-28%", blur: "60px" },
      { size: "70%", top: "15%", left: "15%", blur: "20px" },
    ],
  },
  "ocean-sunset": {
    // Blob 1: Sunset orange, Blob 2: Ocean blue, Blob 3: Horizon purple
    gradients: [
      "conic-gradient(oklch(72% 0.48 45) 0deg, oklch(65% 0.5 35) 60deg, oklch(78% 0.45 55) 120deg, oklch(68% 0.48 40) 180deg, oklch(75% 0.46 50) 240deg, oklch(67% 0.49 42) 300deg, oklch(72% 0.48 45) 360deg)",
      "conic-gradient(oklch(55% 0.4 235) 0deg, oklch(50% 0.42 225) 90deg, oklch(60% 0.38 245) 180deg, oklch(52% 0.4 230) 270deg, oklch(55% 0.4 235) 360deg)",
      "conic-gradient(oklch(50% 0.4 310) 0deg, oklch(45% 0.42 300) 120deg, oklch(55% 0.38 320) 240deg, oklch(50% 0.4 310) 360deg)",
    ],
    blobs: [
      { size: "135%", top: "-18%", left: "-18%", blur: "48px" },
      { size: "145%", top: "-22%", left: "-22%", blur: "55px" },
      { size: "80%", top: "10%", left: "10%", blur: "25px" },
    ],
  },
  "spring": {
    // Blob 1: Fresh green, Blob 2: Blossom pink, Blob 3: Sunny yellow
    gradients: [
      "conic-gradient(oklch(75% 0.42 140) 0deg, oklch(70% 0.45 130) 60deg, oklch(80% 0.4 150) 120deg, oklch(72% 0.43 135) 180deg, oklch(78% 0.41 145) 240deg, oklch(71% 0.44 138) 300deg, oklch(75% 0.42 140) 360deg)",
      "conic-gradient(oklch(85% 0.35 350) 0deg, oklch(80% 0.38 342) 90deg, oklch(88% 0.32 358) 180deg, oklch(82% 0.36 345) 270deg, oklch(85% 0.35 350) 360deg)",
      "conic-gradient(oklch(92% 0.42 95) 0deg, oklch(88% 0.45 85) 120deg, oklch(95% 0.4 102) 240deg, oklch(92% 0.42 95) 360deg)",
    ],
    blobs: [
      { size: "130%", top: "-15%", left: "-15%", blur: "45px" },
      { size: "110%", top: "-5%", left: "-5%", blur: "35px" },
      { size: "85%", top: "8%", left: "8%", blur: "22px" },
    ],
  },
  "cyberpunk": {
    // Blob 1: Neon pink, Blob 2: Electric cyan, Blob 3: Deep purple
    gradients: [
      "conic-gradient(oklch(68% 0.5 340) 0deg, oklch(62% 0.52 330) 60deg, oklch(72% 0.48 350) 120deg, oklch(65% 0.5 335) 180deg, oklch(70% 0.49 345) 240deg, oklch(63% 0.51 338) 300deg, oklch(68% 0.5 340) 360deg)",
      "conic-gradient(oklch(82% 0.45 200) 0deg, oklch(78% 0.48 190) 90deg, oklch(85% 0.42 210) 180deg, oklch(80% 0.46 195) 270deg, oklch(82% 0.45 200) 360deg)",
      "conic-gradient(oklch(38% 0.45 295) 0deg, oklch(32% 0.48 285) 120deg, oklch(42% 0.42 305) 240deg, oklch(38% 0.45 295) 360deg)",
    ],
    blobs: [
      { size: "120%", top: "-10%", left: "-10%", blur: "38px" },
      { size: "135%", top: "-18%", left: "-18%", blur: "48px" },
      { size: "95%", top: "2%", left: "2%", blur: "28px" },
    ],
  },
  "autumn": {
    // Blob 1: Fall orange, Blob 2: Deep red leaf, Blob 3: Golden brown
    gradients: [
      "conic-gradient(oklch(68% 0.45 55) 0deg, oklch(62% 0.48 45) 60deg, oklch(72% 0.42 65) 120deg, oklch(65% 0.46 50) 180deg, oklch(70% 0.44 60) 240deg, oklch(64% 0.47 52) 300deg, oklch(68% 0.45 55) 360deg)",
      "conic-gradient(oklch(45% 0.45 20) 0deg, oklch(40% 0.48 12) 90deg, oklch(50% 0.42 28) 180deg, oklch(42% 0.46 16) 270deg, oklch(45% 0.45 20) 360deg)",
      "conic-gradient(oklch(62% 0.38 75) 0deg, oklch(58% 0.4 65) 120deg, oklch(66% 0.36 82) 240deg, oklch(62% 0.38 75) 360deg)",
    ],
    blobs: [
      { size: "140%", top: "-20%", left: "-20%", blur: "50px" },
      { size: "115%", top: "-8%", left: "-8%", blur: "38px" },
      { size: "75%", top: "12%", left: "12%", blur: "20px" },
    ],
  },
  "royal": {
    // Blob 1: Royal purple, Blob 2: Rich gold, Blob 3: Deep navy
    gradients: [
      "conic-gradient(oklch(45% 0.4 295) 0deg, oklch(40% 0.42 285) 60deg, oklch(50% 0.38 305) 120deg, oklch(42% 0.4 290) 180deg, oklch(48% 0.39 300) 240deg, oklch(41% 0.41 292) 300deg, oklch(45% 0.4 295) 360deg)",
      "conic-gradient(oklch(78% 0.45 85) 0deg, oklch(72% 0.48 75) 90deg, oklch(82% 0.42 92) 180deg, oklch(75% 0.46 80) 270deg, oklch(78% 0.45 85) 360deg)",
      "conic-gradient(oklch(28% 0.32 260) 0deg, oklch(24% 0.35 250) 120deg, oklch(32% 0.3 270) 240deg, oklch(28% 0.32 260) 360deg)",
    ],
    blobs: [
      { size: "135%", top: "-18%", left: "-18%", blur: "48px" },
      { size: "100%", top: "0%", left: "0%", blur: "32px" },
      { size: "160%", top: "-30%", left: "-30%", blur: "65px" },
    ],
  },
  "aquamarine": {
    // Blob 1: Aquamarine teal, Blob 2: Sea foam, Blob 3: Deep turquoise
    gradients: [
      "conic-gradient(oklch(75% 0.38 180) 0deg, oklch(70% 0.4 170) 60deg, oklch(80% 0.36 190) 120deg, oklch(72% 0.38 175) 180deg, oklch(78% 0.37 185) 240deg, oklch(71% 0.39 178) 300deg, oklch(75% 0.38 180) 360deg)",
      "conic-gradient(oklch(88% 0.3 175) 0deg, oklch(85% 0.32 165) 90deg, oklch(90% 0.28 185) 180deg, oklch(86% 0.3 170) 270deg, oklch(88% 0.3 175) 360deg)",
      "conic-gradient(oklch(52% 0.4 195) 0deg, oklch(48% 0.42 185) 120deg, oklch(56% 0.38 205) 240deg, oklch(52% 0.4 195) 360deg)",
    ],
    blobs: [
      { size: "130%", top: "-15%", left: "-15%", blur: "45px" },
      { size: "145%", top: "-22%", left: "-22%", blur: "58px" },
      { size: "85%", top: "8%", left: "8%", blur: "25px" },
    ],
  },
  "volcano": {
    // Blob 1: Molten orange, Blob 2: Lava red, Blob 3: Volcanic black
    gradients: [
      "conic-gradient(oklch(65% 0.52 45) 0deg, oklch(58% 0.54 35) 60deg, oklch(70% 0.5 55) 120deg, oklch(62% 0.52 40) 180deg, oklch(68% 0.51 50) 240deg, oklch(60% 0.53 42) 300deg, oklch(65% 0.52 45) 360deg)",
      "conic-gradient(oklch(48% 0.5 20) 0deg, oklch(42% 0.52 12) 90deg, oklch(52% 0.48 28) 180deg, oklch(45% 0.5 16) 270deg, oklch(48% 0.5 20) 360deg)",
      "conic-gradient(oklch(20% 0.15 30) 0deg, oklch(15% 0.18 20) 120deg, oklch(25% 0.12 40) 240deg, oklch(20% 0.15 30) 360deg)",
    ],
    blobs: [
      { size: "125%", top: "-12%", left: "-12%", blur: "40px" },
      { size: "140%", top: "-20%", left: "-20%", blur: "52px" },
      { size: "165%", top: "-32%", left: "-32%", blur: "70px" },
    ],
  },
  "pastel-dream": {
    // Blob 1: Pastel pink, Blob 2: Pastel blue, Blob 3: Pastel yellow
    gradients: [
      "conic-gradient(oklch(88% 0.22 350) 0deg, oklch(85% 0.25 342) 60deg, oklch(90% 0.2 358) 120deg, oklch(86% 0.23 345) 180deg, oklch(89% 0.21 355) 240deg, oklch(87% 0.24 348) 300deg, oklch(88% 0.22 350) 360deg)",
      "conic-gradient(oklch(88% 0.2 230) 0deg, oklch(85% 0.23 220) 90deg, oklch(90% 0.18 240) 180deg, oklch(86% 0.21 225) 270deg, oklch(88% 0.2 230) 360deg)",
      "conic-gradient(oklch(94% 0.22 95) 0deg, oklch(91% 0.25 85) 120deg, oklch(96% 0.2 102) 240deg, oklch(94% 0.22 95) 360deg)",
    ],
    blobs: [
      { size: "135%", top: "-18%", left: "-18%", blur: "55px" },
      { size: "120%", top: "-10%", left: "-10%", blur: "45px" },
      { size: "100%", top: "0%", left: "0%", blur: "35px" },
    ],
  },
  "twilight": {
    // Blob 1: Twilight purple, Blob 2: Dusk orange, Blob 3: Night blue
    gradients: [
      "conic-gradient(oklch(48% 0.4 290) 0deg, oklch(42% 0.42 280) 60deg, oklch(52% 0.38 300) 120deg, oklch(45% 0.4 285) 180deg, oklch(50% 0.39 295) 240deg, oklch(44% 0.41 288) 300deg, oklch(48% 0.4 290) 360deg)",
      "conic-gradient(oklch(68% 0.45 40) 0deg, oklch(62% 0.48 30) 90deg, oklch(72% 0.42 50) 180deg, oklch(65% 0.46 35) 270deg, oklch(68% 0.45 40) 360deg)",
      "conic-gradient(oklch(30% 0.35 260) 0deg, oklch(25% 0.38 250) 120deg, oklch(35% 0.32 270) 240deg, oklch(30% 0.35 260) 360deg)",
    ],
    blobs: [
      { size: "140%", top: "-20%", left: "-20%", blur: "50px" },
      { size: "110%", top: "-5%", left: "-5%", blur: "38px" },
      { size: "155%", top: "-28%", left: "-28%", blur: "62px" },
    ],
  },
};

// Speed configurations
const speedConfig = {
  slow: { rotate: 30, morph: 15 },
  normal: { rotate: 20, morph: 10 },
  fast: { rotate: 12, morph: 6 },
};

// Position offsets
const positionOffsets: Record<string, { x: string; y: string }> = {
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

// Computed values
const currentPreset = computed(() => presets[props.preset]);
const currentSpeed = computed(() => speedConfig[props.speed]);
const positionOffset = computed(() => positionOffsets[props.position]);
const currentBlobs = computed(() => currentPreset.value.blobs || defaultBlobs);

const gradient1 = computed(() => currentPreset.value.gradients[0]);
const gradient2 = computed(() => currentPreset.value.gradients[1]);
const gradient3 = computed(() => currentPreset.value.gradients[2]);

// Transition configs
const rotateTransition = computed(() => ({
  duration: currentSpeed.value.rotate,
  ease: "linear",
  repeat: Infinity,
}));

const morphTransition = computed(() => ({
  duration: currentSpeed.value.morph,
  ease: "easeInOut",
  repeat: Infinity,
}));

// Blob base styles - computed from preset configuration
const blob1BaseStyle = computed(() => ({
  position: "absolute" as const,
  top: currentBlobs.value[0].top,
  left: currentBlobs.value[0].left,
  width: currentBlobs.value[0].size,
  height: currentBlobs.value[0].size,
  borderRadius: "50%",
  filter: `blur(${currentBlobs.value[0].blur})`,
}));

const blob2BaseStyle = computed(() => ({
  position: "absolute" as const,
  top: currentBlobs.value[1].top,
  left: currentBlobs.value[1].left,
  width: currentBlobs.value[1].size,
  height: currentBlobs.value[1].size,
  borderRadius: "50%",
  filter: `blur(${currentBlobs.value[1].blur})`,
}));

const blob3BaseStyle = computed(() => ({
  position: "absolute" as const,
  top: currentBlobs.value[2].top,
  left: currentBlobs.value[2].left,
  width: currentBlobs.value[2].size,
  height: currentBlobs.value[2].size,
  borderRadius: "50%",
  filter: `blur(${currentBlobs.value[2].blur})`,
}));

const morphBaseStyle = {
  position: "absolute" as const,
  inset: "0",
  width: "100%",
  height: "100%",
  borderRadius: "inherit",
};

// Static styles for non-animated state
const morph1StaticStyle = {
  transform: "scale(0.85)",
  borderRadius: "60% 40% 30% 70% / 60% 30% 70% 40%",
  opacity: 0.8,
};

const morph2StaticStyle = {
  transform: "scale(0.9)",
  borderRadius: "40% 60% 60% 40% / 70% 30% 70% 30%",
};

const morph3StaticStyle = {
  transform: "scale(0.95)",
  borderRadius: "50% 50% 40% 60% / 40% 60% 50% 50%",
};

// Animated state
const blob1Animate = { rotate: [0, 360] };
const blob2Animate = { rotate: [0, -360] };
const blob3Animate = { rotate: [0, 360] };

const morph1Animate = {
  scale: [0.85, 1.05, 0.9, 1.1, 0.95, 0.85],
  borderRadius: [
    "60% 40% 30% 70% / 60% 30% 70% 40%",
    "25% 75% 65% 35% / 45% 55% 25% 75%",
    "70% 30% 45% 55% / 35% 65% 55% 45%",
    "30% 60% 70% 40% / 50% 60% 30% 60%",
    "55% 45% 35% 65% / 65% 35% 60% 40%",
    "60% 40% 30% 70% / 60% 30% 70% 40%",
  ],
  opacity: 0.8,
};

const morph2Animate = {
  scale: [0.9, 1.1, 0.85, 1.05, 0.92, 0.9],
  borderRadius: [
    "40% 60% 60% 40% / 70% 30% 70% 30%",
    "65% 35% 40% 60% / 30% 70% 55% 45%",
    "35% 65% 55% 45% / 60% 40% 35% 65%",
    "60% 40% 30% 70% / 40% 60% 50% 50%",
    "45% 55% 65% 35% / 55% 45% 40% 60%",
    "40% 60% 60% 40% / 70% 30% 70% 30%",
  ],
};

const morph3Animate = {
  scale: [0.95, 1.15, 0.88, 1.02, 1.08, 0.95],
  borderRadius: [
    "50% 50% 40% 60% / 40% 60% 50% 50%",
    "35% 65% 60% 40% / 55% 45% 35% 65%",
    "65% 35% 35% 65% / 45% 55% 65% 35%",
    "70% 30% 50% 50% / 30% 70% 40% 60%",
    "40% 60% 55% 45% / 60% 40% 55% 45%",
    "50% 50% 40% 60% / 40% 60% 50% 50%",
  ],
};
</script>
