import type { DateValue } from "@internationalized/date";

export type PricingDay = {
  rate: number | null;
  available: number;
};

export type PricingMap = Record<string, PricingDay>;

export function formatIsoDate(d: DateValue): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.year}-${pad(d.month)}-${pad(d.day)}`;
}

export function formatRupiahShort(value: number): string {
  if (value >= 1_000_000_000) {
    const v = Math.round((value / 1_000_000_000) * 10) / 10;
    return `Rp${String(v).replace(".", ",").replace(/,0$/, "")}m`;
  }
  if (value >= 1_000_000) {
    const v = Math.round((value / 1_000_000) * 10) / 10;
    return `Rp${String(v).replace(".", ",").replace(/,0$/, "")}jt`;
  }
  if (value >= 1_000) {
    return `Rp${Math.round(value / 1_000)}rb`;
  }
  return `Rp${Math.round(value)}`;
}

/**
 * The `YYYY-MM-DD` window spanned by `months` calendar months starting at
 * `start`: the first day of the start month through the last day of the final
 * visible month. Used to lazy-load pricing for whatever the calendar shows.
 */
export function visibleMonthRange(start: DateValue, months: number): { start: string; end: string } {
  const pad = (n: number) => String(n).padStart(2, "0");
  const last = start.add({ months: Math.max(0, months - 1) });
  const lastMonthDays = new Date(last.year, last.month, 0).getDate();
  return {
    start: `${start.year}-${pad(start.month)}-01`,
    end: `${last.year}-${pad(last.month)}-${pad(lastMonthDays)}`,
  };
}
