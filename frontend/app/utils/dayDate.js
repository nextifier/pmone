// Format an event-day ISO date ("2026-05-28") as "28 May" (day-first, short
// month). Parsed at local midnight to avoid timezone off-by-one. Auto-imported.
export function formatDayDate(iso) {
  if (!iso) return "";
  try {
    return new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short" }).format(
      new Date(`${iso}T00:00:00`)
    );
  } catch {
    return "";
  }
}

// Combine a resolved day label with its date: "Day 2 - 28 May".
export function appendDayDate(label, iso) {
  const date = formatDayDate(iso);
  if (!date) return label || "";
  return label ? `${label} - ${date}` : date;
}
