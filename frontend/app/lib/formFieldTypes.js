// Single source of truth for form builder field types.
// Consumed by FieldTypeSelector, FieldCard, FieldEditor, PublicFieldRenderer,
// the responses table, the response detail dialog, and the analytics page.

export const FIELD_GROUPS = [
  { key: "text", label: "Text" },
  { key: "choice", label: "Choice" },
  { key: "datetime", label: "Date & Time" },
  { key: "number", label: "Number & Scale" },
  { key: "special", label: "Special" },
  { key: "layout", label: "Layout" },
];

export const FIELD_TYPES = {
  text: {
    label: "Short Text",
    icon: "lucide:type",
    group: "text",
    hasPlaceholder: true,
    minMaxMode: "length",
  },
  textarea: {
    label: "Long Text",
    icon: "lucide:align-left",
    group: "text",
    hasPlaceholder: true,
    minMaxMode: "length",
  },
  rich_text: {
    label: "Rich Text",
    icon: "lucide:pilcrow",
    group: "text",
    hasPlaceholder: true,
  },
  email: {
    label: "Email",
    icon: "lucide:mail",
    group: "text",
    hasPlaceholder: true,
  },
  phone: {
    label: "Phone",
    icon: "lucide:phone",
    group: "text",
  },
  url: {
    label: "Link",
    icon: "lucide:link",
    group: "text",
    hasPlaceholder: true,
  },
  select: {
    label: "Dropdown",
    icon: "lucide:chevrons-up-down",
    group: "choice",
    hasOptions: true,
    hasPlaceholder: true,
  },
  multi_select: {
    label: "Multi Select",
    icon: "lucide:list-checks",
    group: "choice",
    hasOptions: true,
    hasPlaceholder: true,
    minMaxMode: "selections",
    multiValue: true,
  },
  radio: {
    label: "Radio",
    icon: "lucide:circle-dot",
    group: "choice",
    hasOptions: true,
  },
  checkbox: {
    label: "Checkbox",
    icon: "lucide:square-check",
    group: "choice",
    hasPlaceholder: true,
    placeholderLabel: "Inline label",
  },
  checkbox_group: {
    label: "Checkboxes",
    icon: "lucide:copy-check",
    group: "choice",
    hasOptions: true,
    minMaxMode: "selections",
    multiValue: true,
  },
  switch: {
    label: "Switch",
    icon: "lucide:toggle-left",
    group: "choice",
    hasPlaceholder: true,
    placeholderLabel: "Inline label",
  },
  tags: {
    label: "Tags",
    icon: "lucide:tags",
    group: "choice",
    hasPlaceholder: true,
    minMaxMode: "selections",
    multiValue: true,
  },
  country: {
    label: "Country",
    icon: "lucide:globe",
    group: "choice",
    hasPlaceholder: true,
  },
  date: {
    label: "Date",
    icon: "lucide:calendar",
    group: "datetime",
  },
  time: {
    label: "Time",
    icon: "lucide:clock",
    group: "datetime",
  },
  datetime: {
    label: "Date & Time",
    icon: "lucide:calendar-clock",
    group: "datetime",
  },
  date_range: {
    label: "Date Range",
    icon: "lucide:calendar-range",
    group: "datetime",
  },
  number: {
    label: "Number",
    icon: "lucide:hash",
    group: "number",
    hasPlaceholder: true,
    minMaxMode: "value",
  },
  slider: {
    label: "Slider",
    icon: "lucide:sliders-horizontal",
    group: "number",
    minMaxMode: "value",
    hasStep: true,
  },
  rating: {
    label: "Rating",
    icon: "lucide:star",
    group: "number",
    hasRatingMax: true,
  },
  linear_scale: {
    label: "Linear Scale",
    icon: "lucide:ruler",
    group: "number",
    minMaxMode: "scale",
    hasScaleLabels: true,
  },
  file: {
    label: "File Upload",
    icon: "lucide:paperclip",
    group: "special",
    hasFileConfig: true,
    noPrefill: true,
  },
  color: {
    label: "Color",
    icon: "lucide:palette",
    group: "special",
  },
  section: {
    label: "Section",
    icon: "lucide:heading",
    group: "layout",
    isLayout: true,
    hasDescription: true,
    noPrefill: true,
  },
};

export const getTypeConfig = (type) => FIELD_TYPES[type] || FIELD_TYPES.text;

export const getTypeLabel = (type) => FIELD_TYPES[type]?.label || type;

export const getTypeIcon = (type) => FIELD_TYPES[type]?.icon || "lucide:type";

export const hasOptions = (type) => Boolean(FIELD_TYPES[type]?.hasOptions);

export const isInputType = (type) => !FIELD_TYPES[type]?.isLayout;

export const supportsPrefill = (type) => !FIELD_TYPES[type]?.noPrefill;

export const defaultValueFor = (field) => {
  switch (field.type) {
    case "checkbox":
    case "switch":
      return false;
    case "multi_select":
    case "checkbox_group":
    case "tags":
      return [];
    case "file":
      return field.settings?.multiple ? [] : null;
    case "text":
    case "textarea":
    case "email":
    case "phone":
    case "url":
    case "rich_text":
      return "";
    default:
      return null;
  }
};

const optionLabel = (field, value) => {
  const option = (field.options || []).find((o) => o.value === value);
  return option?.label ?? String(value);
};

const stripHtml = (html) =>
  String(html)
    .replace(/<[^>]*>/g, " ")
    .replace(/&nbsp;/g, " ")
    .replace(/\s+/g, " ")
    .trim();

export const fileName = (path) => String(path).split("/").pop();

export const formatResponseValue = (field, value) => {
  if (value === null || value === undefined || value === "") return "-";
  if (Array.isArray(value) && !value.length) return "-";

  switch (field?.type) {
    case "select":
    case "radio":
      return optionLabel(field, value);
    case "multi_select":
    case "checkbox_group":
      return (Array.isArray(value) ? value : [value])
        .map((v) => optionLabel(field, v))
        .join(", ");
    case "tags":
      return (Array.isArray(value) ? value : [value]).join(", ");
    case "checkbox":
    case "switch":
      return value ? "Yes" : "No";
    case "date_range": {
      if (typeof value === "object") {
        const range = [value.start, value.end].filter(Boolean).join(" - ");
        return range || "-";
      }
      return String(value);
    }
    case "rich_text":
      return stripHtml(value) || "-";
    case "file":
      return (Array.isArray(value) ? value : [value]).map(fileName).join(", ") || "-";
    default:
      if (Array.isArray(value)) return value.map((v) => String(v)).join(", ");
      if (typeof value === "object") return JSON.stringify(value);
      return String(value);
  }
};
