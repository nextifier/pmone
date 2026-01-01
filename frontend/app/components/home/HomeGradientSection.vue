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
              <div
                class="gradient-grandparent absolute"
                :style="{ filter: gradientFilter, ...gradientPositionStyle }"
              >
                <!-- Large gradient circle with blur parent -->
                <div class="gradient-parent gradient-parent-1">
                  <div class="gradient-circle gradient-circle-1" />
                </div>
                <!-- Medium gradient circle -->
                <div class="gradient-parent gradient-parent-2">
                  <div class="gradient-circle gradient-circle-2" />
                </div>
                <!-- Small gradient circle -->
                <div class="gradient-parent gradient-parent-3">
                  <div class="gradient-circle gradient-circle-3" />
                </div>
              </div>
            </div>

            <!-- Card Content Overlay -->
            <div class="relative z-10 flex h-full flex-col p-4">
              <!-- Browser Chrome Header -->
              <div
                class="mb-3 flex items-center justify-between rounded-t-lg bg-black/40 px-4 py-2 backdrop-blur-sm"
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
                  <div class="mb-4 flex size-10 items-center justify-center rounded-full bg-white/10">
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

// Compute hue-rotate filter based on variant (exact Framer values)
const gradientFilter = computed(() => {
  switch (props.variant) {
    case "cyan":
      return "contrast(1.2) hue-rotate(310deg) saturate(1.2)";
    case "orange":
      return "contrast(1.2) hue-rotate(110deg) saturate(1.2)";
    default:
      return "contrast(1.2) saturate(1.2)";
  }
});

// Gradient position style based on variant (Framer uses different positions)
// Purple: top 34%, scale 7.2x | Cyan/Orange: top 68%, scale 6x
const gradientPositionStyle = computed(() => {
  if (props.variant === "cyan" || props.variant === "orange") {
    return {
      top: "68%",
      left: "50%",
      transform: "translate(-50%, -50%) perspective(1200px) scale(6)",
    };
  }
  return {
    top: "34%",
    left: "50%",
    transform: "translate(-50%, -50%) perspective(1200px) scale(7.2)",
  };
});

// Progress bar gradient class based on variant
const progressBarClass = computed(() => {
  switch (props.variant) {
    case "cyan":
      return "bg-gradient-to-r from-cyan-400 to-teal-400";
    case "orange":
      return "bg-gradient-to-r from-orange-400 to-red-400";
    default:
      return "bg-gradient-to-r from-purple-400 to-blue-400";
  }
});
</script>

<style scoped>
.gradient-box {
  background: rgb(10, 10, 10);
}

/* Grandparent: 172x172px container - position and scale set via inline style based on variant */
.gradient-grandparent {
  width: 172px;
  height: 172px;
  transform-origin: center center;
}

.gradient-parent {
  position: absolute;
  border-radius: 500px;
  will-change: transform;
}

/* Large circle: 100px centered in 172px container → top/left = (172-100)/2 = 36px */
.gradient-parent-1 {
  top: 36px;
  left: 36px;
  width: 100px;
  height: 100px;
  filter: blur(9px);
}

/* Medium circle: 75px centered → top/left = (172-75)/2 = 48.5px */
.gradient-parent-2 {
  top: 48.5px;
  left: 48.5px;
  width: 75px;
  height: 75px;
  filter: blur(8px);
}

/* Small circle: 39px centered → top/left = (172-39)/2 = 66.5px */
.gradient-parent-3 {
  top: 66.5px;
  left: 66.5px;
  width: 39px;
  height: 39px;
  filter: blur(8px);
}

.gradient-circle {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  will-change: transform;
  animation: scale-pulse 12s ease-in-out infinite;
}

.gradient-circle-1 {
  background: conic-gradient(
    rgb(255, 0, 0) 0deg,
    rgb(142, 36, 255) 54.89deg,
    rgb(0, 81, 255) 106.7deg,
    rgb(71, 151, 255) 162deg,
    rgb(0, 68, 255) 252deg,
    rgb(85, 0, 255) 306deg,
    rgb(221, 0, 255) 360deg
  );
  opacity: 0.8;
}

.gradient-circle-2 {
  background: conic-gradient(
    rgb(255, 173, 236) 0deg,
    rgb(19, 156, 229) 180deg,
    rgb(161, 76, 252) 360deg
  );
  opacity: 1;
  animation-delay: -4s;
}

.gradient-circle-3 {
  background: conic-gradient(
    rgb(255, 173, 173) 0deg,
    rgb(19, 156, 229) 180deg,
    rgb(207, 179, 255) 360deg
  );
  opacity: 1;
  animation-delay: -8s;
}

@keyframes scale-pulse {
  0%,
  100% {
    transform: scale(0.80);
  }
  50% {
    transform: scale(1);
  }
}
</style>
