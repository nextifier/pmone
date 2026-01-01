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
  | "orange-flame";

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
