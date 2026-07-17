import { computed, inject, provide, type ComputedRef, type InjectionKey } from "vue";

export type CalendarMode = "single" | "multiple" | "range";

export const CALENDAR_MODE_KEY: InjectionKey<ComputedRef<CalendarMode>> = Symbol("cn-calendar-mode");

export function provideCalendarMode(mode: ComputedRef<CalendarMode>) {
  provide(CALENDAR_MODE_KEY, mode);
}

/**
 * Resolves which reka-ui primitive family the sub-components render. Falls back
 * to "single" so the parts still work when used outside a `<Calendar>` root.
 */
export function useCalendarMode(): ComputedRef<CalendarMode> {
  return inject(
    CALENDAR_MODE_KEY,
    computed(() => "single" as CalendarMode)
  );
}
