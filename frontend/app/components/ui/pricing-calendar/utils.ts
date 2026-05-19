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
