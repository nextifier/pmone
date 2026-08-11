// Client-side mirror of App\Support\FormFieldTypes::rulesForType(). Used by the
// multi-step public form to gate each step before advancing; the single-page
// form still relies on the server's 422 alone. No imports outside this folder,
// same as core.ts, so the whole folder stays portable across the synced
// component libraries.
//
// THE SERVER IS AUTHORITATIVE. This file exists to answer "can the visitor move
// on?" without a round trip, not to replace validation. Where the two could
// disagree, this mirror is deliberately the LOOSER of the two: a value it lets
// through is caught by the server on submit, but a value it wrongly rejects is
// a wall the visitor cannot get past. FieldTypeParityTest locks the type list
// on both sides so a new type cannot land on only one.

import type { CustomFieldShape } from "./core";
import { isEmptyValue, normalizeField, OBJECT_RANGE_TYPES } from "./core";

/** Laravel's `max` default for the free-text types. */
const TEXT_MAX = 65535;

const YEAR_MIN = 1900;
const YEAR_MAX = 2100;

interface RangeValue {
  start?: unknown;
  end?: unknown;
}

const asNumber = (value: unknown): number | null => {
  if (typeof value === "number") return Number.isFinite(value) ? value : null;
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
};

const asArray = (value: unknown): unknown[] => (Array.isArray(value) ? value : [value]);

const textLength = (value: unknown): number => String(value ?? "").length;

/**
 * `date_format` also rejects impossible dates (2026-02-31), so the shape check
 * is followed by a round-trip through Date.
 */
const isDate = (value: unknown): boolean => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value));
  if (!match) return false;

  const [, year, month, day] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day));

  return (
    date.getFullYear() === Number(year) &&
    date.getMonth() === Number(month) - 1 &&
    date.getDate() === Number(day)
  );
};

const isTime = (value: unknown): boolean => {
  const match = /^(\d{2}):(\d{2})$/.exec(String(value));
  return Boolean(match) && Number(match![1]) < 24 && Number(match![2]) < 60;
};

const isDateTime = (value: unknown): boolean => {
  const [date, time] = String(value).split(" ");
  return Boolean(date) && Boolean(time) && isDate(date) && isTime(time);
};

const isMonth = (value: unknown): boolean => {
  const match = /^(\d{4})-(\d{2})$/.exec(String(value));
  return Boolean(match) && Number(match![2]) >= 1 && Number(match![2]) <= 12;
};

/** Loose on purpose - Laravel's default `email` accepts more than a strict regex. */
const isEmail = (value: unknown): boolean => /^[^\s@]+@[^\s@]+$/.test(String(value));

const isUrl = (value: unknown): boolean => {
  try {
    return Boolean(new URL(String(value)).protocol);
  } catch {
    return false;
  }
};

const isHexColor = (value: unknown): boolean =>
  /^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/.test(String(value));

/**
 * Both ends of a range carry `required_with`, so a half-filled range is an
 * error while an entirely empty one falls through to the required check.
 */
const rangeParts = (value: unknown): RangeValue =>
  value && typeof value === "object" && !Array.isArray(value) ? (value as RangeValue) : {};

const bothEndsPresent = (range: RangeValue): boolean =>
  !isEmptyValue(range.start) && !isEmptyValue(range.end);

/**
 * Validate one answer against the rules its field type declares.
 *
 * @returns null when the answer may be submitted, otherwise the first error.
 */
export function validateFieldValue(
  field: CustomFieldShape,
  value: unknown,
  locale = "en",
): string | null {
  const normalized = normalizeField(field, locale);
  const { type, validation, settings, options } = normalized;

  if (type === "section") {
    return null;
  }

  const required = Boolean(validation.required);

  // A required checkbox is `accepted` on the server, so an explicit "no" is not
  // an answer. isEmptyValue(false) is false, which would wrongly let it pass.
  if (type === "checkbox" || type === "switch") {
    if (required && value !== true) {
      return "This field must be accepted.";
    }
    return null;
  }

  if (isEmptyValue(value)) {
    return required ? "This field is required." : null;
  }

  const min = asNumber(validation.min);
  const max = asNumber(validation.max);
  const minSelections = asNumber(validation.min_selections);
  const maxSelections = asNumber(validation.max_selections);
  const optionValues = options.map((option) => option.value);

  switch (type) {
    case "text":
    case "textarea":
    case "rich_text": {
      const length = textLength(value);
      if (min !== null && length < min) return `Must be at least ${min} characters.`;
      const limit = max ?? TEXT_MAX;
      if (length > limit) return `Must not be longer than ${limit} characters.`;
      return null;
    }

    case "email":
      if (!isEmail(value)) return "Enter a valid email address.";
      if (textLength(value) > 255) return "Must not be longer than 255 characters.";
      return null;

    case "phone":
      return textLength(value) > 30 ? "Must not be longer than 30 characters." : null;

    case "url":
      if (!isUrl(value)) return "Enter a valid URL.";
      if (textLength(value) > 2048) return "Must not be longer than 2048 characters.";
      return null;

    case "number":
    case "slider":
    case "slider_ruler":
    case "price": {
      const number = asNumber(value);
      if (number === null) return "Enter a number.";
      if (min !== null && number < min) return `Must be at least ${min}.`;
      if (max !== null && number > max) return `Must not be greater than ${max}.`;
      return null;
    }

    case "slider_range":
    case "price_range": {
      const range = rangeParts(value);
      if (!bothEndsPresent(range)) return "Enter both a start and an end.";

      const start = asNumber(range.start);
      const end = asNumber(range.end);
      if (start === null || end === null) return "Enter a number.";
      if (min !== null && (start < min || end < min)) return `Must be at least ${min}.`;
      if (max !== null && (start > max || end > max)) return `Must not be greater than ${max}.`;
      if (end < start) return "The end must be after the start.";
      return null;
    }

    case "date":
      return isDate(value) ? null : "Enter a valid date.";

    case "time":
      return isTime(value) ? null : "Enter a valid time.";

    case "datetime":
      return isDateTime(value) ? null : "Enter a valid date and time.";

    case "month":
      return isMonth(value) ? null : "Enter a valid month.";

    case "date_range":
    case "month_range":
    case "time_range": {
      const range = rangeParts(value);
      if (!bothEndsPresent(range)) return "Enter both a start and an end.";

      const check = type === "date_range" ? isDate : type === "month_range" ? isMonth : isTime;
      const label =
        type === "date_range" ? "date" : type === "month_range" ? "month" : "time";

      if (!check(range.start) || !check(range.end)) return `Enter a valid ${label}.`;
      if (String(range.end) < String(range.start)) return "The end must be after the start.";
      return null;
    }

    case "year": {
      const year = asNumber(value);
      if (year === null || !Number.isInteger(year)) return "Enter a whole number.";
      const lower = min ?? YEAR_MIN;
      const upper = max ?? YEAR_MAX;
      if (year < lower) return `Must be at least ${lower}.`;
      if (year > upper) return `Must not be greater than ${upper}.`;
      return null;
    }

    case "year_range": {
      const range = rangeParts(value);
      if (!bothEndsPresent(range)) return "Enter both a start and an end.";

      const start = asNumber(range.start);
      const end = asNumber(range.end);
      if (start === null || end === null || !Number.isInteger(start) || !Number.isInteger(end)) {
        return "Enter a whole number.";
      }
      const lower = min ?? YEAR_MIN;
      const upper = max ?? YEAR_MAX;
      if (start < lower || end < lower) return `Must be at least ${lower}.`;
      if (start > upper || end > upper) return `Must not be greater than ${upper}.`;
      if (end < start) return "The end must be after the start.";
      return null;
    }

    case "select":
    case "radio":
      if (optionValues.length && !optionValues.includes(String(value))) {
        return "Select a valid option.";
      }
      return null;

    case "multi_select":
    case "checkbox_group": {
      const selected = asArray(value);
      if (minSelections !== null && selected.length < minSelections) {
        return `Select at least ${minSelections} options.`;
      }
      if (maxSelections !== null && selected.length > maxSelections) {
        return `Select no more than ${maxSelections} options.`;
      }
      if (optionValues.length && selected.some((item) => !optionValues.includes(String(item)))) {
        return "Select a valid option.";
      }
      return null;
    }

    case "tags": {
      const tags = asArray(value);
      if (maxSelections !== null && tags.length > maxSelections) {
        return `Add no more than ${maxSelections} tags.`;
      }
      if (tags.some((tag) => textLength(tag) > 100)) {
        return "Each tag must be 100 characters or fewer.";
      }
      return null;
    }

    case "file": {
      // Size and type are already enforced at upload time by
      // CustomFieldFileUpload; what is left is how many made it through.
      const paths = settings.multiple ? asArray(value) : [value];
      const maxFiles = asNumber(validation.max_files) ?? 5;

      if (settings.multiple && paths.length > maxFiles) {
        return `Upload no more than ${maxFiles} files.`;
      }
      if (paths.some((path) => !String(path).startsWith("form-"))) {
        return "Wait for the upload to finish.";
      }
      return null;
    }

    case "rating": {
      const rating = asNumber(value);
      const ceiling = asNumber(settings.max) ?? 5;
      if (rating === null || !Number.isInteger(rating)) return "Enter a whole number.";
      if (rating < 1) return "Must be at least 1.";
      if (rating > ceiling) return `Must not be greater than ${ceiling}.`;
      return null;
    }

    case "linear_scale": {
      const scale = asNumber(value);
      const lower = min ?? 1;
      const upper = max ?? 5;
      if (scale === null || !Number.isInteger(scale)) return "Enter a whole number.";
      if (scale < lower) return `Must be at least ${lower}.`;
      if (scale > upper) return `Must not be greater than ${upper}.`;
      return null;
    }

    case "color":
      return isHexColor(value) ? null : "Enter a valid hex color.";

    case "country":
      return textLength(value) > 100 ? "Must not be longer than 100 characters." : null;

    default:
      return null;
  }
}

/**
 * Whether an answer counts as given, for the purpose of step navigation. Kept
 * next to the rules because the checkbox carve-out is the same one: a required
 * consent box is only answered when it is ticked, but an optional one is
 * answered either way.
 */
export function isAnswered(field: CustomFieldShape, value: unknown): boolean {
  const normalized = normalizeField(field);

  if (normalized.type === "section") {
    return true;
  }

  if (normalized.type === "checkbox" || normalized.type === "switch") {
    return normalized.validation.required ? value === true : true;
  }

  if (OBJECT_RANGE_TYPES.includes(normalized.type)) {
    return bothEndsPresent(rangeParts(value));
  }

  return !isEmptyValue(value);
}
