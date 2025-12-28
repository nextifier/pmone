import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as GlassButton } from "./GlassButton.vue";

export const glassButtonVariants = cva(
  [
    // Base
    "group pointer-events-auto relative inline-flex items-center justify-center rounded-full bg-linear-[-75deg] from-white/5 via-white/20 to-white/5 font-medium tracking-tighter shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.25em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0_0_0_--alpha(var(--color-white)/10%)] backdrop-blur-[clamp(1px,0.125em,4px)] transition-all duration-(--transition-duration) ease-(--transition-ease) select-none [--angle-1:-75deg] [--angle-2:-45deg] [--border-width:clamp(1px,0.0625em,4px)] [--transition-duration:400ms] [--transition-ease:cubic-bezier(0.25,1,0.5,1)] text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)] hover:text-shadow-[0.025em_0.025em_0.025em_--alpha(var(--color-black)/12%)] active:transform-[rotate3d(1,0,0,25deg)] active:text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)]",

    // Hover State
    "hover:scale-98 hover:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.15em_0.05em_-0.1em_--alpha(var(--color-black)/25%),inset_0_0_0.05em_0.1em_--alpha(var(--color-white)/50%),0_0_0_0_--alpha(var(--color-white)/100%)]",

    // Active State
    "active:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.125em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0.225em_0.05em_0_--alpha(var(--color-black)/5%),0_0.25em_0_0_--alpha(var(--color-white)/75%),inset_0_0.25em_0.05em_0_--alpha(var(--color-black)/15%)]",

    // Shine Effect
    "before:pointer-events-none before:absolute before:top-[calc(0%+var(--border-width)/2)] before:left-[calc(0%+var(--border-width)/2)] before:size-[calc(100%-var(--border-width))] before:overflow-clip before:rounded-full before:bg-size-[200%_200%]! before:bg-position-[0%_50%]! before:bg-no-repeat before:mix-blend-screen before:transition-[background-position,--angle-2] before:duration-[calc(var(--transition-duration)*1.25)] before:ease-(--transition-ease) before:[background:linear-gradient(var(--angle-2),--alpha(var(--color-white)/0%)_0%,--alpha(var(--color-white)/50%)_40%_50%,--alpha(var(--color-white)/0%)_55%)] hover:before:bg-position-[25%_50%]! active:before:bg-position-[50%_15%]! active:before:[--angle-2:-15deg]! pointer-coarse:before:[--angle-2:-45deg] active:pointer-coarse:before:[--angle-2:-45deg]",

    // Outline Effect
    "after:absolute after:inset-0 after:top-[calc(0%-var(--border-width)/2)] after:left-[calc(0%-var(--border-width)/2)] after:size-[calc(100%+var(--border-width))] after:rounded-full after:mask-exclude! after:p-(--border-width) after:shadow-[inset_0_0_0_calc(var(--border-width)/2)_--alpha(var(--color-white)/50%)] after:[background:conic-gradient(from_var(--angle-1)_at_50%_50%,--alpha(var(--color-black)/50%),--alpha(var(--color-black)/0%)_5%_40%,--alpha(var(--color-black)/50%)_50%,--alpha(var(--color-black)/0%)_60%_95%,--alpha(var(--color-black)/50%)),linear-gradient(180deg,--alpha(var(--color-white)/50%),--alpha(var(--color-white)/50%))] after:[mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)] after:[transition:all_var(--transition-duration)_var(--transition-ease),--angle-1_500ms_ease] hover:after:[--angle-1:-125deg] active:after:[--angle-1:-75deg] pointer-coarse:after:[--angle-1:-75deg] pointer-coarse:hover:after:[--angle-1:-75deg] pointer-coarse:active:after:[--angle-1:-75deg]",
  ],
  {
    variants: {
      variant: {
        default: "glass-button-variant-default",
        outline: "glass-button-variant-outline",
        destructive: "glass-button-variant-destructive",
        "destructive-outline": "glass-button-variant-destructive-outline",
        secondary: "glass-button-variant-secondary",
        ghost: "glass-button-variant-ghost",
        link: "glass-button-variant-link",
        invert: "glass-button-variant-invert",
        "outline-invert": "glass-button-variant-outline-invert",
      },
      size: {
        xs: "h-7 gap-1 px-2.5 text-xs sm:h-6",
        sm: "h-8 gap-1.5 px-3 text-sm sm:h-7",
        default: "h-9 gap-2 px-4 text-sm sm:h-8",
        lg: "h-10 gap-2 px-5 text-base sm:h-9",
        xl: "h-11 gap-2.5 px-6 text-lg sm:h-10 sm:text-base",
        icon: "size-9 sm:size-8",
        "icon-xs": "size-7 sm:size-6",
        "icon-sm": "size-8 sm:size-7",
        "icon-lg": "size-10 sm:size-9",
        "icon-xl": "size-11 sm:size-10",
        inherit: "text-[1em] px-[1em] py-[0.5em]",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
);

export type GlassButtonVariants = VariantProps<typeof glassButtonVariants>;
