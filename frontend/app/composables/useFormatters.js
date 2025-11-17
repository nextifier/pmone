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

  return {
    formatDate,
    formatDateTime,
    formatRelativeTime,
    formatNumber,
    formatCurrency,
  };
}
