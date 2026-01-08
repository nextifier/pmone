<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { RouteLocationRaw } from "vue-router";
import { buttonVariants, type ButtonVariants } from ".";

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"];
  size?: ButtonVariants["size"];
  rounded?: ButtonVariants["rounded"];
  class?: HTMLAttributes["class"];
  to?: RouteLocationRaw;
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
});

const resolvedComponent = computed(() => {
  if (props.to) return resolveComponent("NuxtLink");
  return props.as;
});

const resolvedTarget = computed(() => {
  if (typeof props.to === "string" && props.to.startsWith("http")) {
    return "_blank";
  }
  return undefined;
});

const innerShadowRoundedClass = computed(() => {
  const roundedMap: Record<string, string> = {
    none: "after:rounded-none",
    sm: "after:rounded-sm",
    md: "after:rounded-md",
    lg: "after:rounded-lg",
    xl: "after:rounded-xl",
    "2xl": "after:rounded-2xl",
    "3xl": "after:rounded-[24px]", // Use arbitrary value since --breakpoint-3xl conflicts
    full: "after:rounded-full",
  };
  return roundedMap[props.rounded ?? "full"];
});

const hasInnerShadow = computed(() => {
  return !["ghost", "link", "white-ghost", "black-ghost"].includes(props.variant ?? "default");
});

const isOutlineVariant = computed(() => {
  return (props.variant ?? "").includes("outline");
});
</script>

<template>
  <Primitive
    data-slot="button"
    :as="resolvedComponent"
    :as-child="asChild"
    :to="to"
    :target="resolvedTarget"
    :class="cn(buttonVariants({ variant, size, rounded }), props.class)"
  >
    <!-- Inner Shadow Effect -->
    <div
      v-if="hasInnerShadow"
      :class="
        cn(
          'pointer-events-none absolute top-[calc(0%-var(--shadow-offset)/2)] left-[calc(0%-var(--shadow-offset)/2)] size-[calc(100%+var(--shadow-offset))] overflow-visible blur-[clamp(2px,0.125em,12px)] [--shadow-offset:2em]',

          isOutlineVariant &&
            'after:absolute after:inset-0 after:top-[calc(var(--shadow-offset)-0.5em)] after:left-[calc(var(--shadow-offset)-0.875em)] after:size-[calc(100%-var(--shadow-offset)-0.25em)] after:overflow-visible after:bg-linear-to-b after:from-black/20 after:to-black/10 after:mask-exclude! after:p-[0.125em] after:opacity-100 after:transition-[top,opacity] after:duration-(--transition-duration) after:ease-(--transition-ease) after:[mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)]',

          innerShadowRoundedClass,

          'group-hover:blur-[clamp(2px,0.0625em,6px)] group-hover:[transition:filter_var(--transition-duration)_var(--transition-ease)] group-hover:after:top-[calc(var(--shadow-offset)-0.875em)]',

          'group-active:blur-[clamp(2px,0.125em,12px)] group-active:after:top-[calc(var(--shadow-offset)-0.5em)] group-active:after:opacity-75'
        )
      "
    />

    <slot />
  </Primitive>
</template>

<style scoped>
/* CSS Custom Properties for animations */
@property --angle-1 {
  syntax: "<angle>";
  inherits: false;
  initial-value: -75deg;
}

@property --angle-2 {
  syntax: "<angle>";
  inherits: false;
  initial-value: -45deg;
}
</style>
