/**
 * Shared status metadata for the Form Builder feature.
 * Used by forms list, trash, detail header, responses, and analytics
 * so every page renders identical badges and status options.
 */

export const FORM_STATUS_BADGE = {
  published: { variant: "success", icon: "hugeicons:checkmark-circle-02" },
  draft: { variant: "info", icon: "hugeicons:information-circle" },
  closed: { variant: "destructive", icon: "hugeicons:cancel-circle" },
};

export function formStatusBadge(status) {
  return (
    FORM_STATUS_BADGE[status] ?? {
      variant: "outline",
      icon: "hugeicons:information-circle",
    }
  );
}

export const RESPONSE_STATUS_OPTIONS = [
  { value: "new", label: "New", icon: "lucide:circle", color: "text-info" },
  { value: "read", label: "Read", icon: "lucide:check", color: "text-muted-foreground" },
  { value: "starred", label: "Starred", icon: "lucide:star", color: "text-warning" },
  { value: "spam", label: "Spam", icon: "lucide:shield-alert", color: "text-destructive" },
];

export function responseStatusDisplay(status) {
  return (
    RESPONSE_STATUS_OPTIONS.find((s) => s.value === status) ?? {
      value: status,
      label: status,
      icon: "lucide:circle",
      color: "text-muted-foreground",
    }
  );
}
