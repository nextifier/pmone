// Auto-imported. Shared date-range presets for <DatePicker :presets>.
//
// Native Date on purpose: $dayjs is a plugin injection (not importable here)
// and DatePicker converts values via CalendarDate(y, m, d), so time-of-day is
// irrelevant. Every preset value is a getter so relative presets ("Today")
// stay fresh on long-lived pages.
//
// lastNDaysRange(n) spans today-(n-1)..today — the same day-math as the
// backend's Period::days(n), which keeps the Google Analytics warm-cache keys
// (7/30/90 days) matching. Do not "fix" the off-by-one.
import type { DatePickerPreset, DateRangeValue } from "@/components/ui/date-picker";

export function toYmd(date: Date | null | undefined): string | null {
  if (!date) return null;
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function startOfToday(): Date {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
}

function daysAgo(n: number): Date {
  const d = startOfToday();
  d.setDate(d.getDate() - n);
  return d;
}

export function lastNDaysRange(n: number): () => DateRangeValue {
  return () => ({ start: daysAgo(n - 1), end: startOfToday() });
}

function yesterdayRange(): DateRangeValue {
  const d = daysAgo(1);
  return { start: d, end: new Date(d) };
}

function thisMonthRange(): DateRangeValue {
  const today = startOfToday();
  return { start: new Date(today.getFullYear(), today.getMonth(), 1), end: today };
}

function lastMonthRange(): DateRangeValue {
  const today = startOfToday();
  return {
    start: new Date(today.getFullYear(), today.getMonth() - 1, 1),
    end: new Date(today.getFullYear(), today.getMonth(), 0),
  };
}

function thisYearRange(): DateRangeValue {
  const today = startOfToday();
  return { start: new Date(today.getFullYear(), 0, 1), end: today };
}

/** Operational filters (emails, payment gateway dialogs). */
export function standardRangePresets(): DatePickerPreset[] {
  return [
    { label: "Today", value: lastNDaysRange(1) },
    { label: "Yesterday", value: yesterdayRange },
    { label: "Last 3 days", value: lastNDaysRange(3) },
    { label: "Last 7 days", value: lastNDaysRange(7) },
    { label: "Last 15 days", value: lastNDaysRange(15) },
    { label: "Last 30 days", value: lastNDaysRange(30) },
  ];
}

/** Analytics dashboards and /logs — calendar-month/year shortcuts included. */
export function analyticsRangePresets(): DatePickerPreset[] {
  return [
    { label: "Today", value: lastNDaysRange(1) },
    { label: "Yesterday", value: yesterdayRange },
    { label: "Last 7 days", value: lastNDaysRange(7) },
    { label: "Last 30 days", value: lastNDaysRange(30) },
    { label: "This month", value: thisMonthRange },
    { label: "Last month", value: lastMonthRange },
    { label: "Last 90 days", value: lastNDaysRange(90) },
    { label: "This year", value: thisYearRange },
  ];
}
