import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Button } from "./Button.vue";

// Helpers
const r = (s: string) => `rounded-${s} before:rounded-${s} after:rounded-${s}`;
const outline = (text: string) =>
  `bg-linear-[-75deg] from-white/5 via-white/20 to-white/5 dark:via-transparent dark:to-transparent dark:from-transparent shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0_0_0_--alpha(var(--color-white)/10%)] dark:shadow-none backdrop-blur-[clamp(1px,0.125em,4px)] hover:scale-98 ${text}`;
const ghost = (text: string, hover: string, active: string) =>
  `bg-transparent ${text} shadow-none hover:${hover} hover:shadow-none active:${active} active:shadow-none before:hidden after:hidden backdrop-blur-none`;

export const buttonVariants = cva(
  [
    // Base layout & interaction
    "group pointer-events-auto relative inline-flex items-center justify-center font-medium tracking-tight select-none shrink-0 whitespace-nowrap outline-none disabled:cursor-not-allowed disabled:opacity-50",
    // Transitions
    "transition-[transform,box-shadow,opacity] duration-(--transition-duration) ease-(--transition-ease) [--transition-duration:300ms] [--transition-ease:cubic-bezier(0.25,1,0.5,1)]",
    // CSS variables
    "[--angle-1:-75deg] [--angle-2:-45deg] [--border-width:clamp(1px,0.0625em,4px)]",
    // States
    "active:transform-[rotate3d(1,0,0,25deg)] focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-2",
    // Validation
    "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
    // SVG
    "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    // Shadows
    "hover:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.15em_0.05em_-0.1em_--alpha(var(--color-black)/25%),inset_0_0_0.05em_0.1em_--alpha(var(--color-white)/50%),0_0_0_0_--alpha(var(--color-white)/100%)]",
    "active:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),0_0.125em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0.225em_0.05em_0_--alpha(var(--color-black)/5%),0_0.25em_0_0_--alpha(var(--color-white)/75%),inset_0_0.25em_0.05em_0_--alpha(var(--color-black)/15%)] active:text-shadow-[0em_0.25em_0.05em_--alpha(var(--color-black)/10%)]",
    "dark:shadow-none",
    // ::before (shine)
    "before:pointer-events-none before:absolute before:top-[calc(0%+var(--border-width)/2)] before:left-[calc(0%+var(--border-width)/2)] before:size-[calc(100%-var(--border-width))] before:overflow-clip before:bg-size-[200%_200%]! before:bg-position-[0%_50%]! before:bg-no-repeat before:mix-blend-screen before:transition-[background-position,--angle-2] before:duration-[calc(var(--transition-duration)*1.25)] before:ease-(--transition-ease)",
    "before:[background:linear-gradient(var(--angle-2),--alpha(var(--color-white)/0%)_0%,--alpha(var(--color-white)/16%)_40%_50%,--alpha(var(--color-white)/0%)_55%)] dark:before:[background:linear-gradient(var(--angle-2),--alpha(var(--color-white)/0%)_0%,--alpha(var(--color-white)/0%)_40%_50%,--alpha(var(--color-white)/0%)_55%)]",
    "hover:before:bg-position-[25%_50%]! active:before:bg-position-[50%_15%]! active:before:[--angle-2:-15deg]! pointer-coarse:before:[--angle-2:-45deg] active:pointer-coarse:before:[--angle-2:-45deg]",
    // ::after (outline)
    "after:absolute after:inset-0 after:top-[calc(0%-var(--border-width)/2)] after:left-[calc(0%-var(--border-width)/2)] after:size-[calc(100%+var(--border-width))] after:mask-exclude! after:p-(--border-width) after:[mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)]",
    "after:[transition:opacity_var(--transition-duration)_var(--transition-ease),--angle-1_500ms_ease] hover:after:[--angle-1:-125deg] active:after:[--angle-1:-75deg] pointer-coarse:after:[--angle-1:-75deg] pointer-coarse:hover:after:[--angle-1:-75deg] pointer-coarse:active:after:[--angle-1:-75deg]",
    "after:shadow-[inset_0_0_0_calc(var(--border-width)/2)_--alpha(var(--color-white)/50%)] after:[background:conic-gradient(from_var(--angle-1)_at_50%_50%,--alpha(var(--color-black)/50%),--alpha(var(--color-black)/0%)_5%_40%,--alpha(var(--color-black)/50%)_50%,--alpha(var(--color-black)/0%)_60%_95%,--alpha(var(--color-black)/50%)),linear-gradient(180deg,--alpha(var(--color-white)/50%),--alpha(var(--color-white)/50%))] dark:after:opacity-25 dark:hover:after:opacity-50",
  ],
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground",
        invert: "bg-primary-foreground text-primary",
        outline: outline("text-foreground"),
        "outline-invert":
          "bg-linear-[-75deg] dark:from-white/5 dark:via-white/20 dark:to-white/5 via-transparent to-transparent from-transparent text-primary-foreground dark:shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/50%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/20%),0_0_0_0_--alpha(var(--color-white)/10%)] shadow-[inset_0_0.125em_0.125em_--alpha(var(--color-black)/5%),inset_0_-0.125em_0.125em_--alpha(var(--color-white)/10%),0_0.25em_0.125em_-0.125em_--alpha(var(--color-black)/20%),inset_0_0_0.1em_0.25em_--alpha(var(--color-white)/5%),0_0_0_0_--alpha(var(--color-white)/5%)] border border-background/10",
        info: "bg-info text-white",
        success: "bg-success text-white",
        warning: "bg-warning text-white",
        destructive: "bg-destructive text-white",
        "info-outline": outline("text-info-foreground"),
        "success-outline": outline("text-success-foreground"),
        "warning-outline": outline("text-warning-foreground"),
        "destructive-outline": outline("text-destructive-foreground"),
        ghost: ghost("text-foreground", "bg-muted", "bg-border"),
        link: `${ghost("text-foreground", "bg-transparent", "bg-transparent")} underline-offset-4 hover:underline h-auto p-0!`,
        white: "bg-white text-black",
        "white-outline": outline("text-white bg-transparent"),
        "white-ghost": ghost("text-white", "bg-gray-900", "bg-gray-800"),
        black: "bg-black text-white",
        "black-outline": outline("text-black bg-transparent"),
        "black-ghost": ghost("text-black", "bg-gray-100", "bg-gray-200"),
      },
      size: {
        xs: "h-7 gap-1 px-2.5 text-xs sm:h-6",
        sm: "h-8 gap-1.5 px-3 text-sm sm:h-7",
        default: "h-9 gap-1.5 px-4 text-sm sm:text-base sm:h-10",
        lg: "h-10 gap-2 px-5 text-base sm:h-9",
        xl: "h-11 gap-2.5 px-6 text-lg sm:h-10 sm:text-base",
        icon: "size-9 sm:size-8",
        "icon-xs": "size-7 sm:size-6",
        "icon-sm": "size-8 sm:size-7",
        "icon-lg": "size-10 sm:size-9",
        "icon-xl": "size-11 sm:size-10",
        inherit: "text-[1em] px-[1em] py-[0.5em]",
      },
      rounded: {
        none: r("none"),
        sm: r("sm"),
        md: r("md"),
        lg: r("lg"),
        xl: r("xl"),
        "2xl": r("2xl"),
        "3xl": "rounded-[24px] before:rounded-[24px] after:rounded-[24px]",
        full: r("full"),
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
      rounded: "full",
    },
  }
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
