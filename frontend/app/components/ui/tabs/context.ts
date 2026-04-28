import type { ComputedRef, InjectionKey } from "vue";

export type TabsVariant = "pill" | "segmented" | "underline";
export type TabsSize = "sm" | "md" | "lg";

export interface TabsContext {
  variant: ComputedRef<TabsVariant>;
  size: ComputedRef<TabsSize>;
}

export const TABS_CONTEXT: InjectionKey<TabsContext> = Symbol("TabsContext");

export const TABS_DEFAULTS = {
  variant: "pill" as const,
  size: "md" as const,
};

export const tabsListClasses: Record<TabsVariant, string> = {
  pill: "relative inline-flex w-fit items-center justify-center rounded-full border border-border bg-background p-1 text-muted-foreground",
  segmented:
    "relative isolate inline-flex w-fit items-center justify-center rounded-xl bg-muted p-0.5 text-muted-foreground/80",
  underline:
    "relative isolate inline-flex items-end border-b border-border/30 text-muted-foreground/80",
};

export const tabsIndicatorClasses: Record<TabsVariant, string> = {
  pill: "absolute left-0 top-1/2 -translate-y-1/2 h-[calc(100%-0.5rem)] rounded-full bg-muted transition-all duration-300 ease-in-out",
  segmented:
    "absolute inset-y-0.5 left-0 z-0 rounded-lg bg-background shadow-sm ring-1 ring-border/40 transition-all duration-300 ease-in-out",
  underline:
    "absolute bottom-0 left-0 z-0 h-px bg-primary transition-all duration-200 ease-out",
};

export const tabsTriggerClasses: Record<TabsVariant, string> = {
  pill: "relative z-10 rounded-full data-[state=active]:text-foreground hover:text-foreground",
  segmented:
    "relative z-10 rounded-lg data-[state=active]:text-foreground hover:text-foreground",
  underline:
    "relative z-10 data-[state=active]:text-foreground hover:text-muted-foreground",
};

export const tabsTriggerSizeClasses: Record<TabsSize, string> = {
  sm: "h-7 px-2.5 text-xs",
  md: "h-8 px-3 py-1.5 text-sm",
  lg: "h-10 px-4 py-2 text-base",
};
