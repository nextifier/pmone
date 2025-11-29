<template>
  <component
    :is="tag"
    ref="textRef"
    class="w-full [&_.split-line]:perspective-[1000px] [&_.split-line]:transform-3d"
    :class="{
      '[&_.split-line]:origin-center': props.origin === 'center',
      '[&_.split-line]:origin-left': props.origin === 'left',
      '[&_.split-line]:origin-right': props.origin === 'right',
    }"
    :style="{
      visibility: isInitialized ? 'visible' : 'hidden',
    }"
  >
    <span v-if="props.text" ref="textChildRef" v-html="props.text" />
    <slot v-else />
  </component>
</template>

<script setup lang="ts">
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { SplitText as GSAPSplitText } from "gsap/SplitText";

export interface SplitText3DProps {
  text?: string | null;
  tag?: string;
  duration?: number;
  ease?: string;
  stagger?: number;
  start?: string;
  end?: string;
  origin: string;
}

const props = withDefaults(defineProps<SplitText3DProps>(), {
  text: null,
  tag: "span",
  duration: 2.3,
  ease: "expo.out",
  stagger: 0.2,
  start: "bottom 95%",
  end: "bottom 50%",
  origin: "center",
});

const textRef = ref<HTMLDivElement | null>(null);
const textChildRef = ref<HTMLDivElement | null>(null);
const isInitialized = ref(false);
let ctx: gsap.Context | null = null;

const initializeAnimation = () => {
  const el = props.text ? textChildRef.value : textRef.value;

  ctx = gsap.context(() => {
    gsap.registerPlugin(ScrollTrigger, GSAPSplitText);

    const splitter = new GSAPSplitText(el, {
      type: "lines",
      absolute: true,
      linesClass: "split-line",
    });

    const tl = gsap.timeline({
      scrollTrigger: {
        trigger: el,
        start: props.start,
        end: props.end,
        once: true,
      },
    });

    let delay = 0;

    tl.fromTo(
      splitter.lines,
      {
        opacity: 0,
      },
      {
        opacity: 1,
        duration: 0.15,
        stagger: 0.15,
      },
      delay,
    );

    tl.fromTo(
      splitter.lines,
      {
        yPercent: 100,
        skewY: 2,
        scale: 0.8,
        rotateX: -90,
      },
      {
        yPercent: 0,
        skewY: 0,
        rotateX: 0,
        scale: 1,
        ease: props.ease,
        stagger: props.stagger,
        duration: props.duration,
        force3D: true,
      },
      delay,
    );

    tl.set(
      el,
      {
        willChange: "auto",
      },
      "+=0.1",
    );

    delay += 0.3;

    isInitialized.value = true;
  }, el); // Batasi scope ke elemen utama
};

const cleanup = () => {
  if (ctx) {
    ctx.revert();
    ctx = null;
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
    console.warn(`Could not preload font for SplitText3D.`, error);
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
</script>
