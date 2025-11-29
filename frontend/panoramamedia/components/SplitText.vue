<template>
  <span
    ref="textRef"
    :class="`inline-block overflow-hidden whitespace-normal ${className}`"
    :style="{
      visibility: isInitialized ? 'visible' : 'hidden',
    }"
    v-html="text"
  ></span>
</template>

<script setup lang="ts">
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { SplitText as GSAPSplitText } from "gsap/SplitText";

gsap.registerPlugin(ScrollTrigger, GSAPSplitText);

export interface SplitTextProps {
  text: string;
  className?: string;
  delay?: number;
  duration?: number;
  ease?: string | ((t: number) => number);
  splitType?: "chars" | "words" | "lines" | "words, chars";
  from?: gsap.TweenVars;
  to?: gsap.TweenVars;
  threshold?: number;
  rootMargin?: string;
  onLetterAnimationComplete?: () => void;
}

const props = withDefaults(defineProps<SplitTextProps>(), {
  className: "",
  delay: 10,
  duration: 2,
  ease: "elastic.out(1,0.3)",
  splitType: "words, chars",
  from: () => ({ opacity: 0, y: 40 }),
  to: () => ({ opacity: 1, y: 0 }),
  threshold: 0.1,
  rootMargin: "-100px",
});

const emit = defineEmits<{
  "animation-complete": [];
}>();

const textRef = ref<HTMLParagraphElement | null>(null);
const isInitialized = ref(false);
const animationCompletedRef = ref(false);
const scrollTriggerRef = ref<ScrollTrigger | null>(null);
const timelineRef = ref<gsap.core.Timeline | null>(null);
const splitterRef = ref<GSAPSplitText | null>(null);

const initializeAnimation = async () => {
  if (typeof window === "undefined" || !textRef.value) return;

  await nextTick();

  const el = textRef.value;

  if (!el.textContent?.trim()) {
    isInitialized.value = true;
    return;
  }

  animationCompletedRef.value = false;

  const absoluteLines = props.splitType === "lines";
  if (absoluteLines) el.style.position = "relative";

  let splitter: GSAPSplitText;
  try {
    splitter = new GSAPSplitText(el, {
      type: props.splitType,
      absolute: absoluteLines,
      linesClass: "split-line",
    });
    splitterRef.value = splitter;
  } catch (error) {
    console.error("Failed to create SplitText:", error);
    isInitialized.value = true;
    return;
  }

  let targets: Element[];
  switch (props.splitType) {
    case "lines":
      targets = splitter.lines;
      break;
    case "words":
      targets = splitter.words;
      break;
    case "chars":
      targets = splitter.chars;
      break;
    default:
      targets = splitter.chars;
  }

  if (!targets || targets.length === 0) {
    console.warn("No targets found for SplitText animation");
    splitter.revert();
    isInitialized.value = true;
    return;
  }

  gsap.set(targets, { ...props.from, force3D: true });
  isInitialized.value = true;

  targets.forEach((t) => {
    (t as HTMLElement).style.willChange = "transform, opacity";
  });

  const startPct = (1 - props.threshold) * 100;
  const marginMatch = /^(-?\d+(?:\.\d+)?)(px|em|rem|%)?$/.exec(
    props.rootMargin,
  );
  const marginValue = marginMatch ? parseFloat(marginMatch[1]) : 0;
  const marginUnit = marginMatch ? marginMatch[2] || "px" : "px";
  const sign =
    marginValue < 0
      ? `-=${Math.abs(marginValue)}${marginUnit}`
      : `+=${marginValue}${marginUnit}`;
  const start = `top ${startPct}%${sign}`;

  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: el,
      start,
      toggleActions: "play none none none",
      once: true,
      // scrub: true,
      // markers: true,
      onToggle: (self) => {
        scrollTriggerRef.value = self;
      },
    },
    smoothChildTiming: true,
    onComplete: () => {
      animationCompletedRef.value = true;
      gsap.set(targets, {
        ...props.to,
        clearProps: "willChange",
        immediateRender: true,
      });
      props.onLetterAnimationComplete?.();
      emit("animation-complete");
    },
  });

  timelineRef.value = tl;

  tl.to(targets, {
    ...props.to,
    duration: props.duration,
    ease: props.ease,
    stagger: props.delay / 1000,
    force3D: true,
  });
};

const cleanup = () => {
  if (timelineRef.value) {
    timelineRef.value.kill();
    timelineRef.value = null;
  }
  if (scrollTriggerRef.value) {
    scrollTriggerRef.value.kill();
    scrollTriggerRef.value = null;
  }
  if (splitterRef.value) {
    gsap.killTweensOf(textRef.value);
    splitterRef.value.revert();
    splitterRef.value = null;
  }
  isInitialized.value = false;
};

const waitForFontAndInitialize = async () => {
  if (typeof window === "undefined") return;

  await nextTick();
  const el = textRef.value;
  if (!el) return;

  try {
    await document.fonts.ready;

    const computedStyle = window.getComputedStyle(el);
    const fontFamily = computedStyle.fontFamily
      .split(",")[0]
      .trim()
      .replace(/"/g, "");

    const genericFonts = [
      "ui-sans-serif",
      "system-ui",
      "sans-serif",
      "apple color emoji",
      "segoe ui emoji",
      "segoe ui symbol",
      "noto color emoji",
    ];
    if (fontFamily && !genericFonts.includes(fontFamily.toLowerCase())) {
      await document.fonts.load(`1rem "${fontFamily}"`);
    }
  } catch (error) {
    console.warn(
      `Could not preload font for SplitText. Initializing animation anyway.`,
      error,
    );
  } finally {
    initializeAnimation();
  }
};

onMounted(() => {
  waitForFontAndInitialize();
});

onUnmounted(() => {
  cleanup();
});

watch(
  () => props.text,
  () => {
    cleanup();
    nextTick(() => {
      waitForFontAndInitialize();
    });
  },
);
</script>
