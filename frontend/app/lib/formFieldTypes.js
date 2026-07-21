// Admin-editor metadata catalog for the Form Builder (icons, groups, per-type
// editor capability flags). The runtime core (defaultValueFor, option/label
// normalization, response formatting, prefill coercion) lives in the portable
// components/ui/custom-field folder and is re-exported here so admin code has
// one import surface without duplicating logic.
// Consumed by FieldTypeSelector, FieldCard, FieldEditor, the responses table,
// the response detail dialog, and the analytics page.
export {
  defaultValueFor,
  supportsPrefill,
  isInputType,
  formatResponseValue,
  fileName,
  normalizeOptions,
  normalizeField,
  normalizeStoredValue,
  prefillValueFor,
  localizedLabel,
} from "@/components/ui/custom-field";

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
  month: {
    label: "Month",
    icon: "lucide:calendar-days",
    group: "datetime",
    hasPlaceholder: true,
  },
  month_range: {
    label: "Month Range",
    icon: "lucide:calendar-fold",
    group: "datetime",
    hasPlaceholder: true,
  },
  year: {
    label: "Year",
    icon: "lucide:calendar-1",
    group: "datetime",
    hasPlaceholder: true,
    minMaxMode: "value",
  },
  year_range: {
    label: "Year Range",
    icon: "lucide:unfold-horizontal",
    group: "datetime",
    hasPlaceholder: true,
    minMaxMode: "value",
  },
  time_range: {
    label: "Time Range",
    icon: "lucide:timer",
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
  slider_range: {
    label: "Range Slider",
    icon: "lucide:move-horizontal",
    group: "number",
    minMaxMode: "value",
    hasStep: true,
  },
  slider_ruler: {
    label: "Value Scrubber",
    icon: "lucide:pencil-ruler",
    group: "number",
    hasPlaceholder: true,
    placeholderLabel: "Track label",
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

// Editor-metadata view of hasOptions (whether the editor shows an options
// panel). The runtime hasOptions lives in the ui core; they agree.
export const hasOptions = (type) => Boolean(FIELD_TYPES[type]?.hasOptions);
