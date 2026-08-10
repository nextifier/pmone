// Shared vocabulary for the Event Websites pages. Auto-imported.
//
// The list and the detail page both read Cloudflare Workers Builds, and both
// used to carry their own copy of the status map and the time helpers. Two
// copies of a mapping this subtle is one copy too many: Cloudflare splits build
// state across TWO fields, and a failure reads "fail", not "failed".

// `status` is the lifecycle; only a stopped build carries an outcome.
export const BUILD_STATE = {
  queued: { label: "Queued", variant: "muted" },
  initializing: { label: "Starting", variant: "info" },
  running: { label: "Building", variant: "info" },
  success: { label: "Success", variant: "success" },
  fail: { label: "Failed", variant: "destructive" },
  cancelled: { label: "Cancelled", variant: "muted" },
  skipped: { label: "Skipped", variant: "muted" },
  terminated: { label: "Timed out", variant: "warning" },
};

export function buildState(build) {
  if (!build) return { label: "No build yet", variant: "muted" };
  if (build.status !== "stopped") {
    return BUILD_STATE[build.status] || { label: build.status, variant: "info" };
  }
  return BUILD_STATE[build.outcome] || { label: build.outcome || "Unknown", variant: "muted" };
}

// Anything that is not `stopped` is still in flight.
export function isBuildRunning(build) {
  return Boolean(build) && build.status !== "stopped";
}

export function relativeTime(value, { capitalize = false } = {}) {
  if (!value) return "—";

  const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);

  let text;
  if (minutes < 1) text = "just now";
  else if (minutes < 60) text = `${minutes}m ago`;
  else {
    const hours = Math.round(minutes / 60);
    text = hours < 24 ? `${hours}h ago` : `${Math.round(hours / 24)}d ago`;
  }

  // Every numeric form already starts with a digit, so only the word form ever
  // needs this — and it has to stay lowercase inside "Edited just now".
  return capitalize ? text.charAt(0).toUpperCase() + text.slice(1) : text;
}

export function absoluteTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

export function shortDate(value) {
  if (!value) return "";
  return new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short" }).format(
    new Date(value),
  );
}

// "48s" / "6m 12s" / "1h 04m". Returns "" for null so a call site can just v-if
// on it — when Cloudflare is unreachable every duration is null at once, and no
// card should be rendering "NaN".
export function formatDuration(seconds) {
  if (seconds === null || seconds === undefined || Number.isNaN(seconds)) return "";
  if (seconds < 60) return `${Math.max(0, Math.round(seconds))}s`;

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ${String(Math.round(seconds % 60)).padStart(2, "0")}s`;

  return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, "0")}m`;
}

// A finished build uses the number the server computed; a running one counts up
// from `started_at` so it moves between polls.
export function buildElapsed(build, now = Date.now()) {
  if (!build) return null;
  if (build.duration_seconds !== null && build.duration_seconds !== undefined) {
    return build.duration_seconds;
  }
  if (!build.started_at) return null;
  return Math.max(0, Math.round((now - new Date(build.started_at).getTime()) / 1000));
}

// Keys match ProjectContentActivity::SOURCES on the server.
export const CONTENT_SOURCE_ICONS = {
  project: "hugeicons:folder-01",
  event: "hugeicons:calendar-03",
  faqs: "hugeicons:message-question",
  programs: "hugeicons:task-01",
  media_coverages: "hugeicons:news",
  tickets: "hugeicons:ticket-01",
  gallery: "hugeicons:image-02",
};

export function contentSourceIcon(source) {
  return CONTENT_SOURCE_ICONS[source] || "hugeicons:file-01";
}
