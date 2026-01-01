<template>
  <section class="relative overflow-hidden py-20 lg:py-32">
    <div class="container">
      <div
        class="relative flex flex-col items-center gap-10 lg:flex-row lg:items-center lg:gap-16"
        :class="reverse ? 'lg:flex-row-reverse' : ''"
      >
        <div class="relative w-full max-w-xl shrink-0 lg:w-1/2">
          <div
            class="relative aspect-19/20 w-full overflow-hidden rounded-3xl bg-black ring ring-white/10 backdrop-blur-[5px] ring-inset"
          >
            <div class="absolute inset-0">
              <div class="absolute size-43 origin-center" :style="grandparentStyle">
                <Motion
                  class="absolute top-9 left-9 size-25 rounded-[500px] blur-[9px] will-change-transform"
                  :animate="rotateAnimation"
                  :transition="rotateTransition"
                >
                  <Motion
                    class="gradient-circle-1 absolute inset-0 size-full rounded-full opacity-80 will-change-transform"
                    :animate="scaleAnimation"
                    :transition="scaleTransition"
                  />
                </Motion>

                <Motion
                  class="absolute top-[48.5px] left-[48.5px] size-[75px] rounded-[500px] blur-sm will-change-transform"
                  :animate="rotateAnimation"
                  :transition="rotateTransition"
                >
                  <Motion
                    class="gradient-circle-2 absolute inset-0 size-full rounded-full will-change-transform"
                    :animate="scaleAnimation"
                    :transition="scaleTransition"
                  />
                </Motion>

                <Motion
                  class="absolute top-[66.5px] left-[66.5px] size-[39px] rounded-[500px] mix-blend-overlay blur-sm will-change-transform"
                  :animate="rotateAnimation"
                  :transition="rotateTransition"
                >
                  <Motion
                    class="gradient-circle-3 absolute inset-0 size-full rounded-full will-change-transform"
                    :animate="scaleAnimation"
                    :transition="scaleTransition"
                  />
                </Motion>
              </div>
            </div>

            <div class="relative z-10 flex h-full flex-col p-8 sm:p-12">
              <div
                class="mb-2.5 flex items-center justify-between rounded-2xl bg-[oklch(15.59%_0_0)] px-3 py-2 shadow-[0_25px_50px_0_oklch(0%_0_0/0.25),0_5px_25px_0_oklch(0%_0_0/0.5)] ring ring-white/10 backdrop-blur-[9px] ring-inset"
              >
                <div class="flex items-center gap-1.5">
                  <div
                    class="flex size-5 items-center justify-center rounded bg-[oklch(100%_0_0/0.1)]"
                  >
                    <Icon name="lucide:play" class="size-2.5 text-[oklch(100%_0_0)]" />
                  </div>
                  <span class="text-xs font-medium text-[oklch(100%_0_0)]">Desktop</span>
                  <span class="text-xs text-[oklch(100%_0_0/0.4)]">·</span>
                  <span class="text-xs text-[oklch(100%_0_0/0.6)]">1200</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs text-[oklch(100%_0_0/0.6)]">Breakpoint</span>
                  <div
                    class="flex size-5 items-center justify-center rounded bg-[oklch(100%_0_0/0.1)]"
                  >
                    <Icon name="lucide:plus" class="size-2.5 text-[oklch(100%_0_0)]" />
                  </div>
                </div>
              </div>

              <div
                class="relative flex-1 rounded-2xl bg-black/80 p-4 ring ring-white/10 backdrop-blur-[5px] ring-inset"
              >
                <slot name="card">
                  <div
                    class="mb-2 flex size-8 items-center justify-center rounded-full bg-[oklch(100%_0_0/0.1)]"
                  >
                    <Icon name="lucide:user" class="size-4 text-[oklch(100%_0_0/0.7)]" />
                  </div>
                  <h4 class="mb-0.5 text-sm font-semibold text-[oklch(100%_0_0)]">
                    {{ cardTitle || "Dani" }}
                  </h4>
                  <p class="mb-3 text-xs leading-relaxed text-[oklch(100%_0_0/0.5)]">
                    {{
                      cardDescription ||
                      "Welcome to my personal portfolio! Here you can view my latest work and get to know my skills and experience."
                    }}
                  </p>

                  <div
                    v-if="showProjectList"
                    class="relative mb-3 rounded-[9px] bg-[oklch(100%_0_0/0.1)] py-2.5 pr-8 pl-3 shadow-[inset_0_0.5px_0_0_oklch(100%_0_0/0.2)]"
                  >
                    <Motion
                      tag="span"
                      class="text-xs text-[oklch(100%_0_0)]"
                      :initial="{ opacity: 0 }"
                      :animate="{ opacity: 1 }"
                      :transition="{ duration: 0.3 }"
                    >
                      {{ displayedText }}<span class="animate-pulse">|</span>
                    </Motion>
                    <div class="absolute top-1/2 right-2.5 -translate-y-1/2">
                      <Icon name="lucide:arrow-up" class="size-3.5 text-[oklch(100%_0_0/0.6)]" />
                    </div>
                  </div>

                  <div v-if="showProjectList" class="space-y-0">
                    <div
                      v-for="(project, index) in projects"
                      :key="index"
                      class="flex items-center justify-between border-t border-[oklch(100%_0_0/0.1)] py-2"
                    >
                      <span class="text-xs text-[oklch(100%_0_0)]">{{ project.name }}</span>
                      <span class="text-xs text-[oklch(100%_0_0/0.4)]">{{ project.year }}</span>
                    </div>
                  </div>

                  <div v-if="cardProgress" ref="progressContainer" class="mt-3">
                    <div class="mb-1.5 flex items-center justify-between">
                      <span class="text-xs text-[oklch(100%_0_0/0.6)]">{{ cardProgress }}</span>
                    </div>
                    <div class="h-1 w-full rounded-full bg-[oklch(100%_0_0/0.1)]">
                      <Motion
                        class="h-full rounded-full"
                        :class="progressBarClass"
                        :initial="{ width: '0%' }"
                        :animate="progressInView ? { width: '100%' } : { width: '0%' }"
                        :transition="{ duration: 1.5, ease: 'easeOut' }"
                      />
                    </div>
                    <p class="mt-2.5 text-xs text-[oklch(100%_0_0/0.5)]">{{ cardDescription }}</p>
                    <button
                      v-if="cardButton"
                      class="mt-3 w-full rounded-lg bg-[oklch(100%_0_0/0.1)] py-2 text-xs font-medium text-[oklch(100%_0_0/0.6)] transition hover:bg-[oklch(100%_0_0/0.2)]"
                    >
                      {{ cardButton }}
                    </button>
                  </div>
                </slot>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col justify-center lg:w-1/2">
          <h2 class="text-foreground text-3xl font-semibold tracking-tighter lg:text-4xl">
            {{ title }}
          </h2>
          <p class="text-muted-foreground mt-4 text-lg leading-relaxed tracking-tight lg:text-xl">
            {{ description }}
          </p>
          <NuxtLink
            v-if="link"
            :to="link"
            class="text-foreground group mt-6 inline-flex items-center gap-2 tracking-tight transition"
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

const projects: Project[] = [
  { name: "Project One", year: "2020" },
  { name: "Project Two", year: "2021" },
  { name: "Project Three", year: "2022" },
  { name: "Project Four", year: "2022" },
  { name: "Project Five", year: "2023" },
  { name: "Project Six", year: "2024" },
];

const fullText = "Create a simple portfolio website. My name is Dani.";
const displayedText = ref("");
const typingSpeed = 50;
let typingInterval: ReturnType<typeof setInterval> | null = null;

const progressContainer = ref<HTMLElement | null>(null);
const progressInView = ref(false);
let progressObserver: IntersectionObserver | null = null;

const startTyping = () => {
  let index = 0;
  displayedText.value = "";

  typingInterval = setInterval(() => {
    if (index < fullText.length) {
      displayedText.value += fullText[index];
      index++;
    } else {
      setTimeout(() => {
        displayedText.value = "";
        index = 0;
      }, 3000);
    }
  }, typingSpeed);
};

onMounted(() => {
  if (props.showProjectList) {
    startTyping();
  }

  nextTick(() => {
    if (props.cardProgress && progressContainer.value) {
      progressObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              progressInView.value = true;
            }
          });
        },
        { threshold: 0.5 }
      );
      progressObserver.observe(progressContainer.value);
    }
  });
});

onUnmounted(() => {
  if (typingInterval) {
    clearInterval(typingInterval);
  }
  if (progressObserver) {
    progressObserver.disconnect();
  }
});

const grandparentStyle = computed(() => {
  const filter =
    props.variant === "cyan"
      ? "contrast(1.2) saturate(1.2) hue-rotate(310deg)"
      : props.variant === "orange"
        ? "contrast(1.2) saturate(1.2) hue-rotate(110deg)"
        : "contrast(1.2) saturate(1.2)";

  const isPurple = props.variant === "purple";
  const scale = isPurple ? 7.2 : 6;
  const topPercent = isPurple ? "48.2759%" : "75%";

  return {
    filter,
    top: `calc(${topPercent} - 86px)`,
    left: "calc(50% - 86px)",
    transform: `perspective(1200px) scale(${scale})`,
  };
});

const rotateAnimation = { rotate: 360 };
const rotateTransition = { duration: 20, repeat: Infinity, ease: "linear" };
const scaleAnimation = { scale: [0.8, 1, 0.8] };
const scaleTransition = { duration: 8, repeat: Infinity, ease: "easeInOut" };

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
.gradient-circle-1 {
  background: conic-gradient(
    oklch(62.8% 0.2577 29.23) 0deg,
    oklch(50.16% 0.2893 303.11) 54.8916deg,
    oklch(50.06% 0.2556 264.05) 106.699deg,
    oklch(67.41% 0.1653 248.77) 162deg,
    oklch(47.62% 0.2642 264.05) 252deg,
    oklch(44.03% 0.3101 303.37) 306deg,
    oklch(60.82% 0.3099 328.36) 360deg
  );
}

.gradient-circle-2 {
  background: conic-gradient(
    oklch(83.32% 0.1123 340.8) 0deg,
    oklch(66.29% 0.1459 230.9) 180deg,
    oklch(58.21% 0.2459 303.66) 360deg
  );
}

.gradient-circle-3 {
  background: conic-gradient(
    oklch(82.12% 0.0935 17.91) 0deg,
    oklch(66.29% 0.1459 230.9) 180deg,
    oklch(80.66% 0.1189 303.66) 360deg
  );
}
</style>
