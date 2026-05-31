<script setup lang="ts">
import { cn } from "@/lib/utils";
import { useScroll } from "@vueuse/core";
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  provide,
  ref,
  watch,
  type HTMLAttributes,
} from "vue";
import {
  BOTTOM_NAV_CONTEXT,
  BOTTOM_NAV_DEFAULTS,
  bottomNavBarHeightClasses,
  bottomNavBarIndicatorClasses,
  bottomNavContainerClasses,
  bottomNavIndicatorClasses,
  bottomNavNavHeightClasses,
  type BottomNavIndicator,
  type BottomNavLabel,
  type BottomNavSize,
  type BottomNavVariant,
} from "./context";

const props = withDefaults(
  defineProps<{
    modelValue?: string | number;
    variant?: BottomNavVariant;
    indicator?: BottomNavIndicator;
    size?: BottomNavSize;
    labelDisplay?: BottomNavLabel;
    position?: "fixed" | "static";
    hideOnScroll?: boolean;
    scrollTarget?: HTMLElement | string | null;
    ariaLabel?: string;
    class?: HTMLAttributes["class"];
  }>(),
  {
    variant: BOTTOM_NAV_DEFAULTS.variant,
    indicator: BOTTOM_NAV_DEFAULTS.indicator,
    size: BOTTOM_NAV_DEFAULTS.size,
    labelDisplay: BOTTOM_NAV_DEFAULTS.labelDisplay,
    position: "fixed",
    hideOnScroll: false,
    scrollTarget: null,
    ariaLabel: "Bottom navigation",
  },
);

const emit = defineEmits<{ "update:modelValue": [value: string | number] }>();

provide(BOTTOM_NAV_CONTEXT, {
  variant: computed(() => props.variant),
  size: computed(() => props.size),
  indicator: computed(() => props.indicator),
  labelDisplay: computed(() => props.labelDisplay),
  selectedValue: computed(() => props.modelValue),
  select: (value) => {
    if (value !== undefined) {
      emit("update:modelValue", value);
    }
  },
});

const route = useRoute();
const navRef = ref<HTMLElement | null>(null);

type IndicatorStyle = {
  left: string;
  top?: string;
  width: string;
  height?: string;
};

const indicatorStyle = ref<IndicatorStyle | null>(null);
const showSlidingIndicator = computed(
  () => props.indicator === "pill" || props.indicator === "bar",
);

let resizeObserver: ResizeObserver | null = null;
let stateObserver: MutationObserver | null = null;

function measureIndicator(): void {
  const nav = navRef.value;
  if (!nav || !showSlidingIndicator.value) {
    indicatorStyle.value = null;
    return;
  }

  const items = Array.from(
    nav.querySelectorAll<HTMLElement>("[data-bottom-nav-item]"),
  );
  const activeEl = items.find((el) => el.dataset.state === "active");
  if (!activeEl) {
    indicatorStyle.value = null;
    return;
  }

  if (props.indicator === "bar") {
    indicatorStyle.value = {
      left: `${activeEl.offsetLeft}px`,
      width: `${activeEl.offsetWidth}px`,
    };
    return;
  }

  indicatorStyle.value = {
    left: `${activeEl.offsetLeft}px`,
    top: `${activeEl.offsetTop}px`,
    width: `${activeEl.offsetWidth}px`,
    height: `${activeEl.offsetHeight}px`,
  };
}

function scheduleMeasure(): void {
  nextTick(measureIndicator);
}

onMounted(() => {
  scheduleMeasure();

  if (navRef.value && typeof ResizeObserver !== "undefined") {
    resizeObserver = new ResizeObserver(() => measureIndicator());
    resizeObserver.observe(navRef.value);
  }

  if (navRef.value && typeof MutationObserver !== "undefined") {
    stateObserver = new MutationObserver(() => scheduleMeasure());
    stateObserver.observe(navRef.value, {
      subtree: true,
      attributes: true,
      attributeFilter: ["data-state"],
    });
  }
});

watch(
  () => route.path,
  () => scheduleMeasure(),
);

watch(
  () => [props.indicator, props.size, props.labelDisplay],
  () => scheduleMeasure(),
);

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  resizeObserver = null;
  stateObserver?.disconnect();
  stateObserver = null;
});

/**
 * hideOnScroll. Resolve the scroll source: explicit scrollTarget (element or
 * selector) wins, otherwise the window. Works in both fixed and static modes
 * so docs examples can demonstrate the behavior inside a scroll container.
 */
const resolvedScrollSource = ref<HTMLElement | Window | null>(null);

function resolveScrollSource(): HTMLElement | Window | null {
  if (typeof window === "undefined") {
    return null;
  }
  if (!props.hideOnScroll) {
    return null;
  }
  const target = props.scrollTarget;
  if (typeof target === "string") {
    return document.querySelector<HTMLElement>(target) ?? window;
  }
  if (target instanceof HTMLElement) {
    return target;
  }
  return window;
}

const isHidden = ref(false);

const { y: scrollY, directions } = useScroll(resolvedScrollSource, {
  throttle: 50,
});

watch([() => directions.top, () => directions.bottom, scrollY], () => {
  if (!props.hideOnScroll) {
    isHidden.value = false;
    return;
  }
  if (scrollY.value <= 8) {
    isHidden.value = false;
    return;
  }
  if (directions.bottom) {
    isHidden.value = true;
  } else if (directions.top) {
    isHidden.value = false;
  }
});

onMounted(() => {
  resolvedScrollSource.value = resolveScrollSource();
});

watch(
  () => [props.scrollTarget, props.hideOnScroll, props.position],
  () => {
    resolvedScrollSource.value = resolveScrollSource();
  },
);

const containerClass = computed(() => {
  if (props.position === "static") {
    return cn(
      "relative isolate flex w-full items-stretch",
      "transition-transform duration-300 ease-out motion-reduce:transition-none",
      bottomNavContainerClasses[props.variant],
      bottomNavNavHeightClasses[props.size],
      isHidden.value ? "translate-y-full" : "translate-y-0",
    );
  }

  return cn(
    "fixed inset-x-0 bottom-0 z-40 isolate flex items-stretch pb-[env(safe-area-inset-bottom)] lg:hidden",
    "transition-transform duration-300 ease-out motion-reduce:transition-none",
    props.variant === "floating"
      ? "mx-4 mb-[max(1rem,env(safe-area-inset-bottom))]"
      : null,
    bottomNavContainerClasses[props.variant],
    bottomNavNavHeightClasses[props.size],
    isHidden.value
      ? props.variant === "floating"
        ? "translate-y-[calc(100%+1rem)]"
        : "translate-y-full"
      : "translate-y-0",
  );
});

const indicatorVisualClass = computed(() =>
  props.indicator === "bar"
    ? cn(
        "absolute top-0 z-0",
        bottomNavBarHeightClasses[props.size],
        bottomNavBarIndicatorClasses[props.variant],
      )
    : cn("absolute z-0", bottomNavIndicatorClasses[props.variant]),
);
</script>

<template>
  <nav
    ref="navRef"
    data-slot="bottom-nav"
    :data-variant="variant"
    :aria-label="ariaLabel"
    :class="cn(containerClass, props.class)"
  >
    <span
      v-if="showSlidingIndicator && indicatorStyle"
      aria-hidden="true"
      data-slot="bottom-nav-indicator"
      :class="
        cn(
          indicatorVisualClass,
          'transition-[left,top,width,height] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] will-change-[left,top,width,height] motion-reduce:transition-none',
        )
      "
      :style="indicatorStyle"
    />
    <slot />
  </nav>
</template>
