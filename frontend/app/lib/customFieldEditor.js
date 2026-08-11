// Shared editing state and payload builders for every custom-field editor:
// the Form Builder, Business Matching, ticket registration, brand fields and
// the ops-document mini-forms. The per-type capability flags live in
// formFieldTypes.js; this file turns them into the editor's reactive state and
// back into API payloads so the four hosts stay in sync.
import { getTypeConfig, resolveEditorType, supportsPrefill } from "@/lib/formFieldTypes";

export const FIELD_LOCALES = ["en", "id", "ja", "ko", "zh"];

export const FIELD_LOCALE_TABS = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

// Keys the editor owns. They are pruned and rebuilt from the current field type
// on every save, so switching a type can never leave a stale rule behind.
// Anything else in validation/settings (settings.public for brand fields,
// settings.options_preset, keys added later) rides along untouched.
export const MANAGED_VALIDATION_KEYS = [
  "required",
  "min",
  "max",
  "min_selections",
  "max_selections",
  "max_file_size",
  "max_files",
  "allowed_file_types",
];

export const MANAGED_SETTINGS_KEYS = [
  "multiple",
  "step",
  "max",
  "min_label",
  "max_label",
  "description",
  "param_key",
  "currency",
  "show_slider",
];

export const emptyTranslatable = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const emptyValidation = () => ({
  required: false,
  min: null,
  max: null,
  min_selections: null,
  max_selections: null,
  max_file_size: null,
  max_files: null,
  allowed_file_types: [],
});

const emptySettings = () => ({
  multiple: false,
  step: null,
  max: null,
  min_label: "",
  max_label: "",
  description: "",
  param_key: "",
  currency: "",
  show_slider: true,
});

/**
 * Blank editor state. `translatable: false` keeps label/placeholder/help_text as
 * plain strings. Every editor is multi-language now, so the flag exists only for
 * a caller that deliberately wants the flat shape.
 */
export const emptyFieldState = ({ translatable = true } = {}) => ({
  type: "text",
  label: translatable ? emptyTranslatable() : "",
  placeholder: translatable ? emptyTranslatable() : "",
  help_text: translatable ? emptyTranslatable() : "",
  options: [],
  validation: emptyValidation(),
  settings: emptySettings(),
  is_active: true,
});

const toTranslatable = (map, plain) => {
  const source =
    map && typeof map === "object"
      ? map
      : plain && typeof plain === "object"
        ? plain
        : plain
          ? { en: String(plain) }
          : {};

  return { ...emptyTranslatable(), ...source };
};

/** Options arrive as plain strings (brand, documents) or {value,label} pairs. */
const toOptionPairs = (options) =>
  (Array.isArray(options) ? options : []).map((option) =>
    option && typeof option === "object"
      ? { value: option.value != null ? String(option.value) : "", label: option.label ?? "" }
      : { value: String(option), label: String(option) }
  );

const toOptionStrings = (options) =>
  (Array.isArray(options) ? options : []).map((option) =>
    option && typeof option === "object" ? String(option.value ?? "") : String(option)
  );

/**
 * Build editor state from an API field. Unmanaged validation/settings keys are
 * preserved so the save payload can hand them back untouched.
 *
 * @param {"pairs"|"strings"} optionsAs shape the host's options editor expects
 */
export const hydrateFieldState = (field, { translatable = true, optionsAs = "pairs" } = {}) => {
  const state = emptyFieldState({ translatable });

  if (!field) {
    return state;
  }

  state.type = field.type ?? "text";
  state.is_active = field.is_active ?? true;

  if (translatable) {
    state.label = toTranslatable(field.label_translations, field.label);
    state.placeholder = toTranslatable(field.placeholder_translations, field.placeholder);
    state.help_text = toTranslatable(field.help_text_translations, field.help_text);
  } else {
    state.label = field.label ?? "";
    state.placeholder = field.placeholder ?? "";
    state.help_text = field.help_text ?? "";
  }

  state.options = optionsAs === "strings" ? toOptionStrings(field.options) : toOptionPairs(field.options);

  state.validation = {
    ...state.validation,
    ...(field.validation ?? {}),
    allowed_file_types: field.validation?.allowed_file_types ?? [],
  };

  // Contexts that expose the required flag at the top level (event custom
  // fields ship `required`, brand fields ship `is_required`).
  if (field.validation?.required == null) {
    const legacyRequired = field.required ?? field.is_required;
    if (legacyRequired != null) {
      state.validation.required = Boolean(legacyRequired);
    }
  }

  state.settings = { ...state.settings, ...(field.settings ?? {}) };

  return state;
};

/**
 * Always send every locale, using "" for the empty ones. spatie/translatable
 * merges per locale on write and filters empty strings on read, so an empty
 * string is what actually clears a locale - sending null would only clear the
 * app's current locale and leave the others stale.
 */
export const buildTranslatablePayload = (map) =>
  Object.fromEntries(FIELD_LOCALES.map((locale) => [locale, String(map?.[locale] ?? "").trim()]));

/**
 * Drop blank locales, which is what the model effectively stores. Used for the
 * label payload (a blank label is never meaningful) and for the live preview,
 * where an empty locale has to fall back to English the way the renderer does.
 */
export const cleanTranslatable = (map) => {
  const cleaned = {};

  for (const [locale, value] of Object.entries(map ?? {})) {
    const trimmed = value == null ? "" : String(value).trim();
    if (trimmed.length > 0) {
      cleaned[locale] = trimmed;
    }
  }

  return cleaned;
};

/**
 * Rebuild the validation map for the field's current type: carry unmanaged keys
 * over, drop every managed key, then set the ones this type supports.
 */
export const buildValidationPayload = (state, type, { fileConfig = true } = {}) => {
  const config = getTypeConfig(resolveEditorType(type));
  const validation = state.validation ?? {};
  const payload = { ...validation };

  for (const key of MANAGED_VALIDATION_KEYS) {
    delete payload[key];
  }

  payload.required = Boolean(validation.required);

  const mode = config.minMaxMode;

  if (mode === "selections") {
    if (validation.min_selections != null) payload.min_selections = validation.min_selections;
    if (validation.max_selections != null) payload.max_selections = validation.max_selections;
  } else if (mode) {
    if (validation.min != null) payload.min = validation.min;
    if (validation.max != null) payload.max = validation.max;
  }

  if (config.hasFileConfig && fileConfig) {
    if (validation.max_file_size) payload.max_file_size = validation.max_file_size;
    if (state.settings?.multiple && validation.max_files) payload.max_files = validation.max_files;
    if (validation.allowed_file_types?.length) {
      payload.allowed_file_types = validation.allowed_file_types.map((ext) =>
        String(ext).toLowerCase().replace(/^\./, "")
      );
    }
  }

  return payload;
};

/**
 * Same prune-and-rebuild pass for settings. `presets: false` drops
 * settings.options_preset, which is how the brand editor lets a field leave the
 * year_select alias.
 */
export const buildSettingsPayload = (state, type, { prefill = false, presets = true } = {}) => {
  const config = getTypeConfig(resolveEditorType(type));
  const settings = state.settings ?? {};
  const payload = { ...settings };

  for (const key of MANAGED_SETTINGS_KEYS) {
    delete payload[key];
  }

  if (!presets || !config.hasOptions) {
    delete payload.options_preset;
  }

  if (config.hasDescription && settings.description) payload.description = settings.description;
  if (config.hasStep && settings.step) payload.step = settings.step;
  if (config.hasCurrency && settings.currency) payload.currency = settings.currency;
  if (config.hasSliderToggle) payload.show_slider = settings.show_slider !== false;
  if (config.hasRatingMax && settings.max) payload.max = settings.max;

  if (config.hasScaleLabels) {
    if (settings.min_label) payload.min_label = settings.min_label;
    if (settings.max_label) payload.max_label = settings.max_label;
  }

  if (config.hasFileConfig) payload.multiple = Boolean(settings.multiple);
  if (prefill && supportsPrefill(type) && settings.param_key) payload.param_key = settings.param_key;

  return payload;
};

/**
 * Assemble the object the live preview renders. Hosts that keep options as
 * plain strings pass their own filtered list.
 */
// Blank locales are stripped on the way out, so strip them here too: the
// renderer's English fallback then kicks in exactly like it will in production.
const forPreview = (value) => (value && typeof value === "object" ? cleanTranslatable(value) : value);

export const previewFieldFrom = (state, { type, label, options } = {}) => ({
  ulid: "preview",
  type: type ?? state.type,
  label: forPreview(label ?? state.label),
  placeholder: forPreview(state.placeholder),
  help_text: forPreview(state.help_text),
  options: options ?? state.options,
  validation: { ...state.validation },
  settings: { ...state.settings },
});
