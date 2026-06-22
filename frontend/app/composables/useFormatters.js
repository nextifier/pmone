export function useFormatters() {
  function formatDate(dateString, options = {}) {
    if (!dateString) return "-";

    const date = new Date(dateString);

    const defaultOptions = {
      year: "numeric",
      month: "short",
      day: "numeric",
      ...options,
    };

    return date.toLocaleString("en-US", defaultOptions);
  }

  function formatDateTime(dateString) {
    return formatDate(dateString, {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function formatRelativeTime(dateString) {
    if (!dateString) return "-";

    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) {
      return "just now";
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
      return `${diffInMinutes} minute${diffInMinutes > 1 ? "s" : ""} ago`;
    }

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
      return `${diffInHours} hour${diffInHours > 1 ? "s" : ""} ago`;
    }

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
      return `${diffInDays} day${diffInDays > 1 ? "s" : ""} ago`;
    }

    return formatDate(dateString);
  }

  function formatNumber(number) {
    if (number === null || number === undefined) return "0";
    return new Intl.NumberFormat("en-US").format(number);
  }

  function formatCurrency(amount, currency = "USD") {
    if (amount === null || amount === undefined) return "-";
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency,
    }).format(amount);
  }

  function formatPrice(amount) {
    if (amount == null) return "-";
    return `Rp${Number(amount).toLocaleString("id-ID")}`;
  }

  // Full, exact Rupiah, e.g. "Rp20.900.000". No abbreviation, never ambiguous.
  function formatRupiahFull(amount) {
    return `Rp${Number(amount ?? 0).toLocaleString("id-ID")}`;
  }

  // Compact Rupiah using Indonesian short scale so it is NEVER read as the
  // ambiguous English "M" (which Indonesians read as "Milyar"/billion):
  // rb = ribu, jt = juta, miliar, triliun. e.g. "Rp20,9 jt".
  function formatRupiahCompact(amount) {
    const n = Number(amount ?? 0);
    const abs = Math.abs(n);
    const short = (v, suffix) =>
      `Rp${v.toLocaleString("id-ID", { maximumFractionDigits: 1 })}${suffix}`;
    if (abs >= 1e12) return short(n / 1e12, " triliun");
    if (abs >= 1e9) return short(n / 1e9, " miliar");
    if (abs >= 1e6) return short(n / 1e6, " jt");
    if (abs >= 1e3) return short(n / 1e3, " rb");
    return `Rp${n.toLocaleString("id-ID")}`;
  }

  // Compact Rupiah split into the numeric value + Indonesian-scale suffix, so a
  // NumberFlow can animate the digits while showing "jt"/"miliar" (never "M").
  function rupiahCompactParts(amount) {
    const n = Number(amount ?? 0);
    const abs = Math.abs(n);
    if (abs >= 1e12) return { value: n / 1e12, suffix: " triliun" };
    if (abs >= 1e9) return { value: n / 1e9, suffix: " miliar" };
    if (abs >= 1e6) return { value: n / 1e6, suffix: " jt" };
    if (abs >= 1e3) return { value: n / 1e3, suffix: " rb" };
    return { value: n, suffix: "" };
  }

  // Compact plain count using the same unambiguous Indonesian short scale.
  function formatCountCompact(value) {
    const n = Number(value ?? 0);
    const abs = Math.abs(n);
    const short = (v, suffix) =>
      `${v.toLocaleString("id-ID", { maximumFractionDigits: 1 })}${suffix}`;
    if (abs >= 1e12) return short(n / 1e12, " triliun");
    if (abs >= 1e9) return short(n / 1e9, " miliar");
    if (abs >= 1e6) return short(n / 1e6, " jt");
    if (abs >= 1e3) return short(n / 1e3, " rb");
    return n.toLocaleString("id-ID");
  }

  function formatDateId(dateStr) {
    if (!dateStr) return "-";
    return new Date(dateStr).toLocaleDateString("id-ID", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  }

  function orderStatusClass(status) {
    const map = {
      submitted: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
      confirmed: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
      processing: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
      completed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400",
      cancelled: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
    };
    return map[status] || "bg-muted text-muted-foreground";
  }

  return {
    formatDate,
    formatDateTime,
    formatRelativeTime,
    formatNumber,
    formatCurrency,
    formatPrice,
    formatRupiahFull,
    formatRupiahCompact,
    rupiahCompactParts,
    formatCountCompact,
    formatDateId,
    orderStatusClass,
  };
}
