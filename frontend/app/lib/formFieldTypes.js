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
    icon: "hugeicons:text-font",
    group: "text",
    hasPlaceholder: true,
    minMaxMode: "length",
  },
  textarea: {
    label: "Long Text",
    icon: "hugeicons:text-align-left",
    group: "text",
    hasPlaceholder: true,
    minMaxMode: "length",
  },
  rich_text: {
    label: "Rich Text",
    icon: "hugeicons:paragraph",
    group: "text",
    hasPlaceholder: true,
  },
  email: {
    label: "Email",
    icon: "hugeicons:mail-01",
    group: "text",
    hasPlaceholder: true,
  },
  phone: {
    label: "Phone",
    icon: "hugeicons:call-02",
    group: "text",
  },
  url: {
    label: "Link",
    icon: "hugeicons:link-01",
    group: "text",
    hasPlaceholder: true,
  },
  select: {
    // "Select", not "Dropdown": it renders a `<Select>`, and it sits next to
    // "Select Multiple" in the picker — the pair reads as one family.
    label: "Select",
    icon: "hugeicons:unfold-more",
    group: "choice",
    hasOptions: true,
    hasPlaceholder: true,
  },
  multi_select: {
    label: "Select Multiple",
    icon: "hugeicons:check-list",
    group: "choice",
    hasOptions: true,
    hasPlaceholder: true,
    minMaxMode: "selections",
    multiValue: true,
  },
  radio: {
    label: "Radio",
    icon: "hugeicons:radio-button",
    group: "choice",
    hasOptions: true,
  },
  checkbox: {
    label: "Checkbox",
    icon: "hugeicons:checkmark-square-02",
    group: "choice",
    hasPlaceholder: true,
    placeholderLabel: "Inline label",
  },
  checkbox_group: {
    label: "Checkboxes",
    icon: "hugeicons:copy-check",
    group: "choice",
    hasOptions: true,
    minMaxMode: "selections",
    multiValue: true,
  },
  switch: {
    label: "Switch",
    icon: "hugeicons:toggle-off",
    group: "choice",
    hasPlaceholder: true,
    placeholderLabel: "Inline label",
  },
  tags: {
    label: "Tags",
    icon: "hugeicons:tags",
    group: "choice",
    hasPlaceholder: true,
    minMaxMode: "selections",
    multiValue: true,
  },
  country: {
    label: "Country",
    icon: "hugeicons:globe-02",
    group: "choice",
    hasPlaceholder: true,
  },
  date: {
    label: "Date",
    icon: "hugeicons:calendar-03",
    group: "datetime",
  },
  time: {
    label: "Time",
    icon: "hugeicons:clock-01",
    group: "datetime",
  },
  datetime: {
    label: "Date & Time",
    icon: "hugeicons:date-time",
    group: "datetime",
  },
  date_range: {
    label: "Date Range",
    icon: "hugeicons:calendar-04",
    group: "datetime",
  },
  month: {
    label: "Month",
    icon: "hugeicons:calendar-02",
    group: "datetime",
    hasPlaceholder: true,
  },
  month_range: {
    label: "Month Range",
    icon: "hugeicons:calendar-fold",
    group: "datetime",
    hasPlaceholder: true,
  },
  year: {
    label: "Year",
    icon: "hugeicons:calendar-01",
    group: "datetime",
    hasPlaceholder: true,
    minMaxMode: "value",
  },
  year_range: {
    label: "Year Range",
    icon: "hugeicons:horizontal-resize",
    group: "datetime",
    hasPlaceholder: true,
    minMaxMode: "value",
  },
  time_range: {
    label: "Time Range",
    icon: "hugeicons:timer-01",
    group: "datetime",
  },
  number: {
    label: "Number",
    icon: "hugeicons:hashtag",
    group: "number",
    hasPlaceholder: true,
    minMaxMode: "value",
  },
  slider: {
    label: "Slider",
    icon: "hugeicons:sliders-horizontal",
    group: "number",
    minMaxMode: "value",
    hasStep: true,
  },
  slider_range: {
    label: "Range Slider",
    icon: "hugeicons:arrow-left-right",
    group: "number",
    minMaxMode: "value",
    hasStep: true,
  },
  slider_ruler: {
    label: "Value Scrubber",
    icon: "hugeicons:pencil-ruler",
    group: "number",
    hasPlaceholder: true,
    placeholderLabel: "Track label",
    minMaxMode: "value",
    hasStep: true,
  },
  price: {
    label: "Price",
    icon: "hugeicons:money-01",
    group: "number",
    hasPlaceholder: true,
    minMaxMode: "value",
    hasCurrency: true,
  },
  price_range: {
    label: "Price Range",
    icon: "hugeicons:money-send-01",
    group: "number",
    minMaxMode: "value",
    hasStep: true,
    hasCurrency: true,
    hasSliderToggle: true,
  },
  rating: {
    label: "Rating",
    icon: "hugeicons:star",
    group: "number",
    hasRatingMax: true,
  },
  linear_scale: {
    label: "Linear Scale",
    icon: "hugeicons:ruler",
    group: "number",
    minMaxMode: "scale",
    hasScaleLabels: true,
  },
  file: {
    label: "File Upload",
    icon: "hugeicons:attachment-01",
    group: "special",
    hasFileConfig: true,
    noPrefill: true,
  },
  color: {
    label: "Color",
    icon: "hugeicons:paint-board",
    group: "special",
  },
  section: {
    label: "Section",
    icon: "hugeicons:heading",
    group: "layout",
    isLayout: true,
    hasDescription: true,
    noPrefill: true,
  },
};

// Brand fields expose a "Year Select" convenience type that the backend stores
// as select + settings.options_preset=years. Resolve it before reading editor
// flags, otherwise it falls through to the text config and the min/max inputs
// would be labelled "Min length".
export const EDITOR_TYPE_ALIASES = { year_select: "select" };

export const resolveEditorType = (type) => EDITOR_TYPE_ALIASES[type] ?? type;

export const getTypeConfig = (type) => FIELD_TYPES[type] || FIELD_TYPES.text;

export const getTypeLabel = (type) => FIELD_TYPES[type]?.label || type;

export const getTypeIcon = (type) => FIELD_TYPES[type]?.icon || "hugeicons:text-font";

// Editor-metadata view of hasOptions (whether the editor shows an options
// panel). The runtime hasOptions lives in the ui core; they agree.
export const hasOptions = (type) => Boolean(FIELD_TYPES[type]?.hasOptions);
