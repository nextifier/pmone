import type { ComputedRef, InjectionKey } from "vue";

export type TabsVariant = "pill" | "segmented" | "underline";
export type TabsSize = "sm" | "md" | "lg";

export interface TabsContext {
  variant: ComputedRef<TabsVariant>;
  size: ComputedRef<TabsSize>;
  swipeable: ComputedRef<boolean>;
}

export const TABS_CONTEXT: InjectionKey<TabsContext> = Symbol("TabsContext");

export const TABS_DEFAULTS = {
  variant: "pill" as const,
  size: "md" as const,
  swipeable: false as const,
};

export const tabsListClasses: Record<TabsVariant, string> = {
  pill: "relative inline-flex w-fit max-w-full overflow-x-auto items-center justify-center-safe no-scrollbar scroll-fade-x rounded-full border border-border bg-background p-0.5 bg-muted text-muted-foreground",
  segmented:
    "relative isolate inline-flex w-fit max-w-full overflow-x-auto items-center justify-center-safe no-scrollbar scroll-fade-x rounded-xl bg-muted dark:bg-background dark:border p-0.5 text-muted-foreground/80",
  underline:
    "relative isolate inline-flex w-fit max-w-full overflow-x-auto items-center-safe justify-center-safe no-scrollbar scroll-fade-x border-b border-border/30 text-muted-foreground/80",
};

export const tabsIndicatorClasses: Record<TabsVariant, string> = {
  pill: "absolute left-0 inset-y-0.5 rounded-full bg-background shadow-sm transition-[transform,width] duration-300 ease-out dark:border dark:bg-border/70 dark:border-white/10",
  segmented:
    "absolute inset-y-0.5 left-0 z-0 rounded-lg bg-background shadow-sm ring-1 ring-border/40 transition-[transform,width] duration-300 ease-out dark:border dark:bg-muted dark:border-white/15",
  underline:
    "absolute bottom-0 left-0 z-0 h-px bg-primary transition-[transform,width] duration-200 ease-out",
};

export const tabsTriggerClasses: Record<TabsVariant, string> = {
  pill: "relative z-10 rounded-full data-[state=active]:text-foreground hover:text-foreground",
  segmented:
    "relative z-10 rounded-lg data-[state=active]:text-foreground hover:text-foreground",
  underline:
    "relative z-10 data-[state=active]:text-foreground hover:text-muted-foreground",
};

export const tabsTriggerSizeClasses: Record<TabsSize, string> = {
  sm: "h-7 px-2.5 text-xs tracking-tight",
  md: "h-8 px-3 py-1.5 text-sm tracking-tight",
  lg: "h-10 px-4 py-2 text-base tracking-tight",
};
