import { getTypeLabel } from "@/lib/formFieldTypes";

/**
 * Mirrors EventDocument::isEventRule() / isSubmissionComplete() on the backend.
 * A document is described by its mini-form fields; `document_type` is only a
 * legacy marker for documents created before the mini-form existed.
 */

const EVENT_RULE_KIND = { key: "event_rule", label: "Event Rule", filterLabel: "Event Rule" };

const LEGACY_TYPE_LABELS = {
  checkbox_agreement: "Checkbox",
  file_upload: "File Upload",
  text_input: "Text Input",
};

export function documentActiveFields(doc) {
  return (doc?.fields || []).filter((field) => field.is_active !== false);
}

export function isEventRuleDocument(doc) {
  if (!doc?.blocks_next_step) return false;

  const fields = documentActiveFields(doc);
  if (!fields.length) return doc.document_type === "checkbox_agreement";

  return fields.some((field) => field.type === "checkbox" && field.validation?.required);
}

/**
 * The checkbox an exhibitor ticks to agree to a rule. Backfilled legacy
 * documents carry a synthesized field (`system_key: 'agreement'`) whose label
 * is generic, so callers fall back to their own copy for those.
 */
export function documentAgreementField(doc) {
  const fields = documentActiveFields(doc);

  return (
    fields.find((field) => field.type === "checkbox" && field.system_key === "agreement") ||
    fields.find((field) => field.type === "checkbox" && field.validation?.required) ||
    null
  );
}

export function documentKind(doc) {
  const fields = documentActiveFields(doc);

  if (!fields.length) {
    if (isEventRuleDocument(doc)) return EVENT_RULE_KIND;
    const label = LEGACY_TYPE_LABELS[doc?.document_type] || "No fields";
    return { key: `legacy:${doc?.document_type}`, label, filterLabel: label };
  }

  if (isEventRuleDocument(doc)) return EVENT_RULE_KIND;

  if (fields.length === 1) {
    const label = getTypeLabel(fields[0].type);
    return { key: `field:${fields[0].type}`, label, filterLabel: label };
  }

  return { key: "mixed", label: `Mixed (${fields.length} fields)`, filterLabel: "Mixed" };
}

export function isDocumentSubmissionComplete(doc, submission) {
  if (!submission) return false;

  if (isEventRuleDocument(doc)) return Boolean(submission.agreed_at);

  if (documentActiveFields(doc).length) {
    return Object.keys(submission.field_values || {}).length > 0;
  }

  if (doc?.document_type === "file_upload") return Boolean(submission.submission_file);
  if (doc?.document_type === "text_input") return Boolean(submission.text_value);

  return false;
}

export function documentSubmissionStatus(doc, submission) {
  if (!submission) return "pending";
  if (submission.needs_reagreement) return "needs_reagreement";
  return isDocumentSubmissionComplete(doc, submission) ? "completed" : "pending";
}

function formatAnswer(value) {
  if (value === null || value === undefined) return "";
  if (typeof value === "boolean") return value ? "Yes" : "";
  if (Array.isArray(value)) return value.map((item) => String(item).trim()).filter(Boolean).join(", ");
  return String(value).trim();
}

/**
 * Field-by-field rendering of a mini-form submission. Returns an empty array
 * for legacy documents, which the callers render from their own columns.
 */
export function documentAnswers(doc, submission) {
  if (!submission) return [];

  const files = submission.files || [];

  return documentActiveFields(doc)
    .map((field) => {
      if (field.type === "file") {
        return {
          label: field.label,
          files: files.filter(
            (media) =>
              media.field_ulid === field.ulid ||
              (field.system_key === "legacy_file" && !media.field_ulid)
          ),
        };
      }

      return { label: field.label, value: formatAnswer(submission.field_values?.[field.ulid]) };
    })
    .filter((answer) => answer.files?.length || answer.value);
}
