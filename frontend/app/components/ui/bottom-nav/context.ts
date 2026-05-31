import type { ComputedRef, InjectionKey } from "vue";

export type BottomNavVariant = "default" | "floating" | "glass";
export type BottomNavSize = "sm" | "md" | "lg";
export type BottomNavIndicator = "pill" | "bar" | "dot" | "none";
export type BottomNavLabel = "always" | "active" | "none";

export interface BottomNavContext {
  variant: ComputedRef<BottomNavVariant>;
  size: ComputedRef<BottomNavSize>;
  indicator: ComputedRef<BottomNavIndicator>;
  labelDisplay: ComputedRef<BottomNavLabel>;
  selectedValue: ComputedRef<string | number | undefined>;
  select: (value: string | number | undefined) => void;
}

export const BOTTOM_NAV_CONTEXT: InjectionKey<BottomNavContext> =
  Symbol("BottomNavContext");

export const BOTTOM_NAV_DEFAULTS = {
  variant: "default" as const,
  size: "md" as const,
  indicator: "pill" as const,
  labelDisplay: "always" as const,
};

/**
 * Shell of the <nav> root. The nav itself is the flex container; these classes
 * cover surface + border per variant. Positioning (fixed/static/floating
 * offsets) is composed in BottomNav.vue so it stays in one place.
 */
export const bottomNavContainerClasses: Record<BottomNavVariant, string> = {
  default: "border-t bg-background",
  floating:
    "rounded-2xl border bg-background shadow-lg",
  glass:
    "border-t bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/75",
};

/**
 * Per-item trigger base. Items sit above the sliding indicator (z-10) so the
 * pill/bar render behind. Each item is an equal-width flex column.
 */
export const bottomNavItemClasses: Record<BottomNavVariant, string> = {
  default:
    "relative z-10 flex flex-1 select-none flex-col items-center justify-center rounded-xl text-muted-foreground transition-colors hover:text-foreground data-[state=active]:text-foreground",
  floating:
    "relative z-10 flex flex-1 select-none flex-col items-center justify-center rounded-xl text-muted-foreground transition-colors hover:text-foreground data-[state=active]:text-foreground",
  glass:
    "relative z-10 flex flex-1 select-none flex-col items-center justify-center rounded-xl text-muted-foreground transition-colors hover:text-foreground data-[state=active]:text-foreground",
};

/**
 * Sliding indicator surface per variant. Pill sits behind the active item;
 * bar is a thin top-edge line. Geometry (left/top/width/height) is set inline
 * from measurement; these classes only carry the look + transition.
 */
export const bottomNavIndicatorClasses: Record<BottomNavVariant, string> = {
  default:
    "bg-muted rounded-xl shadow-xs dark:bg-border/60 dark:border dark:border-white/10",
  floating:
    "bg-muted rounded-xl shadow-xs dark:bg-border/60 dark:border dark:border-white/10",
  glass:
    "bg-muted/80 rounded-xl shadow-xs dark:bg-border/60 dark:border dark:border-white/10",
};

export const bottomNavBarIndicatorClasses: Record<BottomNavVariant, string> = {
  default: "bg-primary rounded-full",
  floating: "bg-primary rounded-full",
  glass: "bg-primary rounded-full",
};

export const bottomNavItemPaddingClasses: Record<BottomNavSize, string> = {
  sm: "gap-y-0.5 px-1 py-1.5",
  md: "gap-y-0.5 px-2 py-2",
  lg: "gap-y-1 px-2 py-2.5",
};

export const bottomNavIconSizeClasses: Record<BottomNavSize, string> = {
  sm: "size-5",
  md: "size-[1.375rem]",
  lg: "size-6",
};

export const bottomNavBarHeightClasses: Record<BottomNavSize, string> = {
  sm: "h-0.5",
  md: "h-0.5",
  lg: "h-[3px]",
};

export const bottomNavLabelSizeClasses: Record<BottomNavSize, string> = {
  sm: "text-[0.6875rem] leading-tight",
  md: "text-xs sm:text-sm leading-tight",
  lg: "text-xs sm:text-sm leading-tight",
};

export const bottomNavNavHeightClasses: Record<BottomNavSize, string> = {
  sm: "min-h-13",
  md: "min-h-14",
  lg: "min-h-16",
};
