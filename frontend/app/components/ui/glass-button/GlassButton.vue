<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { GlassButtonVariants } from ".";

interface Props extends PrimitiveProps {
  variant?: GlassButtonVariants["variant"];
  size?: GlassButtonVariants["size"];
  class?: HTMLAttributes["class"];
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
  variant: "default",
  size: "default",
});

const sizeClass = computed(() =>
  props.size === "inherit" ? "" : `glass-button-size-${props.size}`
);
const variantClass = computed(() => `glass-button-variant-${props.variant}`);
</script>

<template>
  <Primitive
    data-slot="button"
    :as="as"
    :as-child="asChild"
    :class="
      cn(
        'relative isolate rounded-full [font-size:var(--global-size,1rem)] transition-all duration-(--transition-duration) ease-(--transition-ease) [--angle-1:-75deg] [--angle-2:-45deg] [--border-width:clamp(1px,0.0625em,4px)] [--shadow-offset:2em] [--transition-duration:400ms] [--transition-ease:cubic-bezier(0.25,1,0.5,1)] has-[.glass-button:active]:transform-[rotate3d(1,0,0,25deg)]',

        'group peer pointer-events-auto relative rounded-full bg-linear-[-75deg] from-white/5 via-white/20 to-white/5 shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.25em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0_0_0_--alpha(var(--color-white)/10%)] backdrop-blur-[clamp(1px,0.125em,4px)] transition-all duration-(--transition-duration) ease-(--transition-ease)',

        'hover:scale-98 hover:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.15em_0.05em_-0.1em_--alpha(var(--color-black)/25%),inset_0_0_0.05em_0.1em_--alpha(var(--color-white)/50%),0_0_0_0_--alpha(var(--color-white)/100%)]',

        'active:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.125em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0.225em_0.05em_0_--alpha(var(--color-black)/5%),0_0.25em_0_0_--alpha(var(--color-white)/75%),inset_0_0.25em_0.05em_0_--alpha(var(--color-black)/15%)]',

        'after:absolute after:inset-0 after:top-[calc(0%-var(--border-width)/2)] after:left-[calc(0%-var(--border-width)/2)] after:size-[calc(100%+var(--border-width))] after:rounded-full after:mask-exclude! after:p-(--border-width) after:shadow-[inset_0_0_0_calc(var(--border-width)/2)_--alpha(var(--color-white)/50%)] after:[background:conic-gradient(from_var(--angle-1)_at_50%_50%,--alpha(var(--color-black)/50%),--alpha(var(--color-black)/0%)_5%_40%,--alpha(var(--color-black)/50%)_50%,--alpha(var(--color-black)/0%)_60%_95%,--alpha(var(--color-black)/50%)),linear-gradient(180deg,--alpha(var(--color-white)/50%),--alpha(var(--color-white)/50%))] after:[mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)] after:[transition:all_var(--transition-duration)_var(--transition-ease),--angle-1_500ms_ease] hover:after:[--angle-1:-125deg] active:after:[--angle-1:-75deg] pointer-coarse:after:[--angle-1:-75deg] pointer-coarse:hover:after:[--angle-1:-75deg] pointer-coarse:active:after:[--angle-1:-75deg]',

        sizeClass,
        variantClass,
        props.class
      )
    "
  >
    <div
      :class="
        cn(
          'pointer-events-none absolute top-[calc(0%-var(--shadow-offset)/2)] left-[calc(0%-var(--shadow-offset)/2)] size-[calc(100%+var(--shadow-offset))] overflow-visible blur-[clamp(2px,0.125em,12px)]',

          'after:absolute after:inset-0 after:top-[calc(var(--shadow-offset)-0.5em)] after:left-[calc(var(--shadow-offset)-0.875em)] after:size-[calc(100%-var(--shadow-offset)-0.25em)] after:overflow-visible after:rounded-full after:bg-linear-to-b after:from-black/20 after:to-black/10 after:mask-exclude! after:p-[0.125em] after:opacity-100 after:transition-all after:duration-(--transition-duration) after:ease-(--transition-ease) after:[mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)]',

          'group-hover:blur-[clamp(2px,0.0625em,6px)] group-hover:[transition:filter_var(--transition-duration)_var(--transition-ease)] group-hover:after:top-[calc(var(--shadow-offset)-0.875em)]',

          'group-active:blur-[clamp(2px,0.125em,12px)] group-active:after:top-[calc(var(--shadow-offset)-0.5em)] group-active:after:opacity-75'
        )
      "
    ></div>

    <span
      :class="
        cn(
          'relative block rounded-full px-[1.5em] py-[0.875em] font-medium tracking-tighter transition-all duration-(--transition-duration) ease-(--transition-ease) select-none text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)] group-active:text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)]',

          'after:pointer-events-none after:absolute after:top-[calc(0%+var(--border-width)/2)] after:left-[calc(0%+var(--border-width)/2)] after:size-[calc(100%-var(--border-width))] after:overflow-clip after:rounded-full after:bg-size-[200%_200%]! after:bg-position-[0%_50%]! after:bg-no-repeat after:mix-blend-screen after:transition-[background-position,--angle-2] after:duration-[calc(var(--transition-duration)*1.25)] after:ease-(--transition-ease) after:[background:linear-gradient(var(--angle-2),--alpha(var(--color-white)/0%)_0%,--alpha(var(--color-white)/50%)_40%_50%,--alpha(var(--color-white)/0%)_55%)]',

          'group-hover:text-shadow-[0.025em_0.025em_0.025em_--alpha(var(--color-black)/12%)] group-hover:after:bg-position-[25%_50%]!',

          'group-active:after:bg-position-[50%_15%]! group-active:after:[--angle-2:-15deg]!',

          'pointer-coarse:after:[--angle-2:-45deg] group-active:pointer-coarse:after:[--angle-2:-45deg]'
        )
      "
    >
      <slot />
    </span>
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

/* Size Variants */
.glass-button-size-xs {
  --global-size: 0.625rem;
}

.glass-button-size-sm {
  --global-size: 0.75rem;
}

.glass-button-size-default {
  --global-size: 0.875rem;
}

.glass-button-size-lg {
  --global-size: 1rem;
}

.glass-button-size-xl {
  --global-size: 1.125rem;
}

.glass-button-size-icon {
  --global-size: 0.875rem;
}

.glass-button-size-icon-xs {
  --global-size: 0.625rem;
}

.glass-button-size-icon-sm {
  --global-size: 0.75rem;
}

.glass-button-size-icon-lg {
  --global-size: 1rem;
}

.glass-button-size-icon-xl {
  --global-size: 1.125rem;
}
</style>
