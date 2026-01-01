<template>
  <section class="relative overflow-hidden bg-black py-20 lg:py-32">
    <div class="container">
      <div
        class="relative flex flex-col items-center gap-10 lg:flex-row lg:items-center lg:gap-16"
        :class="reverse ? 'lg:flex-row-reverse' : ''"
      >
        <!-- Gradient Card Container -->
        <div class="relative w-full max-w-[590px] shrink-0 lg:w-1/2">
          <div
            class="gradient-box relative aspect-[590/617] w-full overflow-hidden rounded-[15px] border border-white/10"
          >
            <!-- Animated Gradient Background -->
            <div class="absolute inset-0">
              <!-- Grandparent: contrast + saturate + scale -->
              <div class="gradient-grandparent absolute" :style="grandparentStyle">
                <!-- Large gradient circle with blur parent (100px) -->
                <Motion
                  class="gradient-parent"
                  :style="blurParent1Style"
                  :animate="rotateAnimation1"
                  :transition="rotateTransition1"
                >
                  <Motion
                    class="gradient-circle gradient-circle-1"
                    :animate="scaleAnimation"
                    :transition="scaleTransition1"
                  />
                </Motion>

                <!-- Medium gradient circle (75px) -->
                <Motion
                  class="gradient-parent"
                  :style="blurParent2Style"
                  :animate="rotateAnimation2"
                  :transition="rotateTransition2"
                >
                  <Motion
                    class="gradient-circle gradient-circle-2"
                    :animate="scaleAnimation"
                    :transition="scaleTransition2"
                  />
                </Motion>

                <!-- Small gradient circle (39px) -->
                <Motion
                  class="gradient-parent"
                  :style="blurParent3Style"
                  :animate="rotateAnimation3"
                  :transition="rotateTransition3"
                >
                  <Motion
                    class="gradient-circle gradient-circle-3"
                    :animate="scaleAnimation"
                    :transition="scaleTransition3"
                  />
                </Motion>
              </div>
            </div>

            <!-- Card Content Overlay -->
            <div class="relative z-10 flex h-full flex-col p-4">
              <!-- Browser Chrome Header -->
              <div
                class="mb-3 flex items-center justify-between rounded-t-lg bg-black/30 px-4 py-2 backdrop-blur-sm"
              >
                <div class="flex items-center gap-2">
                  <div class="flex size-6 items-center justify-center rounded bg-white/10">
                    <Icon name="lucide:play" class="size-3 text-white/60" />
                  </div>
                  <span class="text-sm text-white/80">Desktop</span>
                  <span class="text-sm text-white/40">·</span>
                  <span class="text-sm text-white/60">1200</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-white/60">Breakpoint</span>
                  <div class="flex size-5 items-center justify-center rounded bg-white/10">
                    <Icon name="lucide:plus" class="size-3 text-white/60" />
                  </div>
                </div>
              </div>

              <!-- Main Card Content -->
              <div
                class="flex-1 rounded-lg border border-white/10 bg-black/30 p-6 backdrop-blur-sm"
              >
                <slot name="card">
                  <!-- Default portfolio card content -->
                  <div
                    class="mb-4 flex size-10 items-center justify-center rounded-full bg-white/10"
                  >
                    <Icon name="lucide:user" class="size-5 text-white/60" />
                  </div>
                  <h4 class="mb-1 text-lg font-semibold text-white">{{ cardTitle }}</h4>
                  <p class="mb-6 text-sm leading-relaxed text-white/50">{{ cardDescription }}</p>

                  <!-- Project List -->
                  <div v-if="showProjectList" class="space-y-0">
                    <div
                      v-for="(project, index) in projects"
                      :key="index"
                      class="flex items-center justify-between border-t border-white/10 py-3"
                    >
                      <span class="text-sm text-white">{{ project.name }}</span>
                      <span class="text-sm text-white/40">{{ project.year }}</span>
                    </div>
                  </div>

                  <!-- Progress bar for translate variant -->
                  <div v-if="cardProgress" class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                      <span class="text-sm text-white/60">{{ cardProgress }}</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-white/10">
                      <div
                        class="h-full rounded-full"
                        :class="progressBarClass"
                        style="width: 100%"
                      />
                    </div>
                    <p class="mt-3 text-sm text-white/50">{{ cardDescription }}</p>
                    <button
                      v-if="cardButton"
                      class="mt-4 w-full rounded-lg bg-white/10 py-2.5 text-sm font-medium text-white/60 transition hover:bg-white/20"
                    >
                      {{ cardButton }}
                    </button>
                  </div>
                </slot>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="flex flex-col justify-center lg:w-1/2">
          <h2 class="text-3xl font-semibold tracking-tight text-white lg:text-4xl">
            {{ title }}
          </h2>
          <p class="mt-4 text-lg leading-relaxed text-white/50 lg:text-xl">
            {{ description }}
          </p>
          <NuxtLink
            v-if="link"
            :to="link"
            class="group mt-6 inline-flex items-center gap-2 text-white transition hover:text-white/80"
          >
            {{ linkText }}
            <Icon
              name="lucide:chevron-right"
              class="size-4 transition-transform group-hover:translate-x-0.5"
            />
          </NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { Motion } from "motion-v";

interface Project {
  name: string;
  year: string;
}

interface Props {
  title: string;
  description: string;
  link?: string;
  linkText?: string;
  reverse?: boolean;
  variant?: "purple" | "cyan" | "orange";
  cardTitle?: string;
  cardDescription?: string;
  cardProgress?: string;
  cardButton?: string;
  showProjectList?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  reverse: false,
  variant: "purple",
  linkText: "Learn more",
  showProjectList: false,
});

// Sample projects for the wireframer card
const projects: Project[] = [
  { name: "Project One", year: "2020" },
  { name: "Project Two", year: "2021" },
  { name: "Project Three", year: "2022" },
  { name: "Project Four", year: "2022" },
  { name: "Project Five", year: "2023" },
  { name: "Project Six", year: "2024" },
];

// Grandparent style with filter and transform (exact Framer values)
const grandparentStyle = computed(() => {
  const filter =
    props.variant === "cyan"
      ? "contrast(1.2) hue-rotate(310deg) saturate(1.2)"
      : props.variant === "orange"
        ? "contrast(1.2) hue-rotate(110deg) saturate(1.2)"
        : "contrast(1.2) saturate(1.2)";

  // Purple: top 34%, scale 7.2 | Cyan/Orange: top 68%, scale 6
  const isPurple = props.variant === "purple";
  const scale = isPurple ? 7.2 : 6;
  const top = isPurple ? "34%" : "68%";

  return {
    filter,
    top,
    left: "50%",
    transform: `translate(-50%, -50%) perspective(1200px) scale(${scale})`,
  };
});

// Blur parent styles (exact Framer positions and sizes)
const blurParent1Style = {
  position: "absolute" as const,
  top: "36px",
  left: "36px",
  width: "100px",
  height: "100px",
  filter: "blur(9px)",
  borderRadius: "500px",
};

const blurParent2Style = {
  position: "absolute" as const,
  top: "48.5px",
  left: "48.5px",
  width: "75px",
  height: "75px",
  filter: "blur(8px)",
  borderRadius: "500px",
};

const blurParent3Style = {
  position: "absolute" as const,
  top: "66.5px",
  left: "66.5px",
  width: "39px",
  height: "39px",
  filter: "blur(8px)",
  borderRadius: "500px",
  mixBlendMode: "overlay" as const,
};

// Rotation animations for blur parents (continuous rotation like Framer)
const rotateAnimation1 = { rotate: 360 };
const rotateAnimation2 = { rotate: 360 };
const rotateAnimation3 = { rotate: 360 };

// Rotation transitions (faster, matching Framer's speed)
const rotateTransition1 = {
  duration: 8,
  repeat: Infinity,
  ease: "linear",
};

const rotateTransition2 = {
  duration: 10,
  repeat: Infinity,
  ease: "linear",
};

const rotateTransition3 = {
  duration: 12,
  repeat: Infinity,
  ease: "linear",
};

// Scale animation for gradient circles (0.8 to 1.0 like Framer)
const scaleAnimation = {
  scale: [0.8, 1, 0.8],
};

// Scale transitions with faster durations matching Framer
const scaleTransition1 = {
  duration: 4,
  repeat: Infinity,
  ease: "easeInOut",
};

const scaleTransition2 = {
  duration: 5,
  repeat: Infinity,
  ease: "easeInOut",
};

const scaleTransition3 = {
  duration: 6,
  repeat: Infinity,
  ease: "easeInOut",
};

// Progress bar gradient class based on variant
const progressBarClass = computed(() => {
  switch (props.variant) {
    case "cyan":
      return "bg-linear-to-r from-cyan-400 to-teal-400";
    case "orange":
      return "bg-linear-to-r from-orange-400 to-red-400";
    default:
      return "bg-linear-to-r from-purple-400 to-blue-400";
  }
});
</script>

<style scoped>
/* .gradient-box {
  background: rgb(10, 10, 10);
} */

.gradient-grandparent {
  width: 172px;
  height: 172px;
  transform-origin: center center;
}

.gradient-parent {
  will-change: transform;
}

.gradient-circle {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  will-change: transform;
}

/* Using oklch for more vivid/saturated colors */
.gradient-circle-1 {
  background: conic-gradient(
    oklch(0.63 0.29 29) 0deg,
    oklch(0.49 0.32 303) 54.8916deg,
    oklch(0.49 0.28 264) 106.699deg,
    oklch(0.66 0.19 250) 162deg,
    oklch(0.46 0.29 265) 252deg,
    oklch(0.42 0.33 292) 306deg,
    oklch(0.57 0.33 318) 360deg
  );
  opacity: 0.8;
}

.gradient-circle-2 {
  background: conic-gradient(
    oklch(0.82 0.15 340) 0deg,
    oklch(0.64 0.18 230) 180deg,
    oklch(0.56 0.28 298) 360deg
  );
  opacity: 1;
}

.gradient-circle-3 {
  background: conic-gradient(
    oklch(0.81 0.13 20) 0deg,
    oklch(0.64 0.18 230) 180deg,
    oklch(0.8 0.16 295) 360deg
  );
  opacity: 1;
}
</style>
