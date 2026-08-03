// Framework-free helpers behind PublicFormView. Kept separate so the parts that
// are pure data (cover srcset, duplicate-check mode, validation error mapping)
// can be reasoned about without a component around them, mirroring the split
// custom-field/core.ts already uses.

/**
 * Conversion ladder registered on the Form model (registerMediaConversions).
 * The rendered column is ~544px, so the 1500px `xl` render alone was always
 * more than any viewport asked for.
 */
export const COVER_WIDTHS = { sm: 450, md: 900, lg: 1200, xl: 1500 };

/**
 * Build a srcset from whichever conversions exist. Ungenerated conversions fall
 * back to the original URL server-side, so repeats are dropped: the same file
 * listed at four widths tells the browser nothing. Returns undefined when there
 * is nothing meaningful to choose between.
 */
export const buildCoverSrcset = (cover) => {
  if (!cover) return undefined;

  const seen = new Set();
  const candidates = [];

  for (const [key, width] of Object.entries(COVER_WIDTHS)) {
    if (!cover[key] || seen.has(cover[key])) continue;
    seen.add(cover[key]);
    candidates.push(`${cover[key]} ${width}w`);
  }

  return candidates.length > 1 ? candidates.join(", ") : undefined;
};

/**
 * A 403 means the form is shut (not yet open, past closes_at, or response limit
 * reached) and carries the staff-set closed_message. It is the one load failure
 * that stays inline instead of escalating to the error boundary: the visitor did
 * nothing wrong, and "Access denied" is the wrong thing to tell them.
 */
export const isClosedError = (fetchError) =>
  (fetchError?.statusCode || fetchError?.data?.statusCode) === 403;

/**
 * Honeypot companion token. The hidden `website` field catches bots that fill
 * everything; this timestamp proves the submission was paced by a human.
 * Browser-only (btoa), so call it from onMounted.
 */
export const generateHoneypotToken = () => {
  const rand = () => Math.random().toString(16).slice(2, 10);
  return btoa(`${rand()}_${Math.floor(Date.now() / 1000)}_${rand()}`);
};

/**
 * prevent_duplicate keys off the email, the browser fingerprint, or both.
 * Splitting the mode keeps each check honest: on load only the fingerprint can
 * match (nothing has been typed yet), and the email check is pointless in
 * fingerprint-only mode. Mirrors checkDuplicateSubmission() in PublicFormService.
 *
 * @returns {{checksEmail: boolean, checksFingerprint: boolean}}
 */
export const duplicateModeFor = (settings) => {
  const mode = settings?.prevent_duplicate ? settings.prevent_duplicate_by || "fingerprint" : null;

  return {
    checksEmail: ["email", "both"].includes(mode),
    checksFingerprint: ["fingerprint", "both"].includes(mode),
  };
};

/**
 * Split a Laravel 422 bag into per-field errors and everything that has no
 * field to attach to, which is surfaced as one general message.
 *
 * @returns {Record<string, string>} keyed by the original error key, plus `_general`
 */
export const mapValidationErrors = (errors) => {
  const mapped = {};
  const unmapped = [];

  for (const [key, messages] of Object.entries(errors)) {
    const message = Array.isArray(messages) ? messages[0] : messages;
    if (key.startsWith("responses.") || key === "respondent_email") {
      mapped[key] = message;
    } else {
      unmapped.push(message);
    }
  }

  if (unmapped.length) {
    mapped._general = unmapped.join(". ");
  }

  return mapped;
};

/**
 * First error for a field, including nested keys (date_range start/end, array
 * items) which Laravel reports as `responses.{ulid}.{child}`.
 */
export const firstFieldError = (errors, ulid) => {
  const prefix = `responses.${ulid}`;
  if (errors[prefix]) return errors[prefix];

  const nestedKey = Object.keys(errors).find((key) => key.startsWith(`${prefix}.`));
  return nestedKey ? errors[nestedKey] : null;
};

/**
 * Locales this form was actually authored in, read off the field labels.
 * A language nobody wrote a label in has nothing to show, so offering it in the
 * switcher would just render the English fallback under a Japanese flag.
 *
 * Labels alone decide it: `label.en` is the one required translation, so a
 * locale that reached a label is a locale someone deliberately filled in.
 *
 * @returns {string[]} locale codes, empty when the form is single-language
 */
export const availableFormLocales = (form) => {
  const found = new Set();

  for (const field of form?.fields || []) {
    const translations = field.label_translations;
    if (!translations || typeof translations !== "object") continue;

    for (const [locale, value] of Object.entries(translations)) {
      if (String(value ?? "").trim()) found.add(locale);
    }
  }

  return [...found];
};

const usableTranslations = (map) =>
  map && typeof map === "object" && Object.values(map).some((v) => String(v ?? "").trim())
    ? map
    : null;

/**
 * Swap the flat `label` string for its translation map so the renderer can pick
 * a language. The API sends both: `label` already resolved to one language (the
 * old shape, kept for existing consumers) and `label_translations` alongside it.
 * `normalizeField` only ever looks at `label`, so without this the renderer sees
 * a finished string and every locale shows the same text.
 */
export const withFieldTranslations = (field) => ({
  ...field,
  label: usableTranslations(field.label_translations) ?? field.label,
  placeholder: usableTranslations(field.placeholder_translations) ?? field.placeholder,
  help_text: usableTranslations(field.help_text_translations) ?? field.help_text,
});

export const sortFormFields = (fields) =>
  [...(fields || [])]
    .sort((a, b) => (a.order_column || 0) - (b.order_column || 0))
    .map(withFieldTranslations);

/**
 * Imported lazily so the fingerprint bundle never blocks the initial render.
 * Failing is survivable: the duplicate check simply falls back to the 409 the
 * server returns on submit.
 */
export const loadVisitorId = async () => {
  try {
    const FingerprintJS = await import("@fingerprintjs/fingerprintjs");
    const fp = await FingerprintJS.load();
    return (await fp.get()).visitorId;
  } catch {
    return null;
  }
};
