<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { GlassButtonVariants } from ".";
import { glassButtonVariants } from ".";

interface Props extends PrimitiveProps {
  variant?: GlassButtonVariants["variant"];
  size?: GlassButtonVariants["size"];
  class?: HTMLAttributes["class"];
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
});
</script>

<template>
  <Primitive
    data-slot="button"
    :as="as"
    :as-child="asChild"
    :class="
      cn(
        // Base
        'group pointer-events-auto relative inline-flex items-center justify-center rounded-full bg-linear-[-75deg] from-white/5 via-white/20 to-white/5 font-medium tracking-tighter shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.25em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0_0_0_--alpha(var(--color-white)/10%)] backdrop-blur-[clamp(1px,0.125em,4px)] transition-all duration-(--transition-duration) ease-(--transition-ease) select-none [--angle-1:-75deg] [--angle-2:-45deg] [--border-width:clamp(1px,0.0625em,4px)] [--shadow-offset:2em] [--transition-duration:400ms] [--transition-ease:cubic-bezier(0.25,1,0.5,1)] text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)] hover:text-shadow-[0.025em_0.025em_0.025em_--alpha(var(--color-black)/12%)] active:transform-[rotate3d(1,0,0,25deg)] active:text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)]',

        // Hover State
        'hover:scale-98 hover:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.15em_0.05em_-0.1em_--alpha(var(--color-black)/25%),inset_0_0_0.05em_0.1em_--alpha(var(--color-white)/50%),0_0_0_0_--alpha(var(--color-white)/100%)]',

        // Active State
        'active:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.125em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0.225em_0.05em_0_--alpha(var(--color-black)/5%),0_0.25em_0_0_--alpha(var(--color-white)/75%),inset_0_0.25em_0.05em_0_--alpha(var(--color-black)/15%)]',

        // Shine effect
        'before:pointer-events-none before:absolute before:top-[calc(0%+var(--border-width)/2)] before:left-[calc(0%+var(--border-width)/2)] before:size-[calc(100%-var(--border-width))] before:overflow-clip before:rounded-full before:bg-size-[200%_200%]! before:bg-position-[0%_50%]! before:bg-no-repeat before:mix-blend-screen before:transition-[background-position,--angle-2] before:duration-[calc(var(--transition-duration)*1.25)] before:ease-(--transition-ease) before:[background:linear-gradient(var(--angle-2),--alpha(var(--color-white)/0%)_0%,--alpha(var(--color-white)/50%)_40%_50%,--alpha(var(--color-white)/0%)_55%)] hover:before:bg-position-[25%_50%]! active:before:bg-position-[50%_15%]! active:before:[--angle-2:-15deg]! pointer-coarse:before:[--angle-2:-45deg] active:pointer-coarse:before:[--angle-2:-45deg]',

        // Outline
        'after:absolute after:inset-0 after:top-[calc(0%-var(--border-width)/2)] after:left-[calc(0%-var(--border-width)/2)] after:size-[calc(100%+var(--border-width))] after:rounded-full after:mask-exclude! after:p-(--border-width) after:shadow-[inset_0_0_0_calc(var(--border-width)/2)_--alpha(var(--color-white)/50%)] after:[background:conic-gradient(from_var(--angle-1)_at_50%_50%,--alpha(var(--color-black)/50%),--alpha(var(--color-black)/0%)_5%_40%,--alpha(var(--color-black)/50%)_50%,--alpha(var(--color-black)/0%)_60%_95%,--alpha(var(--color-black)/50%)),linear-gradient(180deg,--alpha(var(--color-white)/50%),--alpha(var(--color-white)/50%))] after:[mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)] after:[transition:all_var(--transition-duration)_var(--transition-ease),--angle-1_500ms_ease] hover:after:[--angle-1:-125deg] active:after:[--angle-1:-75deg] pointer-coarse:after:[--angle-1:-75deg] pointer-coarse:hover:after:[--angle-1:-75deg] pointer-coarse:active:after:[--angle-1:-75deg]',

        glassButtonVariants({ variant, size }),
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
