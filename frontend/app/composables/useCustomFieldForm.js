import {
  cleanTranslatable,
  emptyFieldState,
  FIELD_LOCALE_TABS,
  hydrateFieldState,
  previewFieldFrom,
} from "@/lib/customFieldEditor";
import { getTypeConfig, getTypeLabel, hasOptions, resolveEditorType } from "@/lib/formFieldTypes";

/**
 * The editor half of a custom-field dialog: the reactive draft, the locale
 * proxies, the live preview payload, and the checks worth running before
 * spending a request.
 *
 * Four surfaces author custom fields - the form builder, brand fields, the two
 * event contexts, and ops documents. They differ in endpoint, id key and
 * payload shape, but every one of them had its own byte-identical copy of the
 * blocks below, down to the comments. This is that copy, once.
 *
 * @param {object} options
 * @param {"pairs"|"strings"} [options.optionsAs] How the surface stores options.
 *   `pairs` keeps `{label, value}` (form builder, event contexts); `strings`
 *   keeps plain strings (brand fields, documents).
 * @param {() => object} [options.extraState] Fields this surface adds to the
 *   draft beyond the shared shape - brand fields carry `is_public`.
 * @param {(field: object) => object} [options.extraHydrate] The same on the way
 *   back in, given the record being edited.
 * @param {(type: string) => string} [options.typeLabel] Overrides the label used
 *   for the preview placeholder, for surfaces with alias types (`year_select`).
 * @param {(form: object) => boolean} [options.optionsVisible] Extra condition on
 *   top of `hasOptions`. The event contexts hide the manual editor for
 *   preset-backed fields, whose options are generated rather than authored.
 */
export function useCustomFieldForm(options = {}) {
  const {
    optionsAs = "pairs",
    extraState = () => ({}),
    extraHydrate = () => ({}),
    typeLabel = getTypeLabel,
    optionsVisible = () => true,
  } = options;

  const buildEmpty = () => ({ ...emptyFieldState(), ...extraState() });

  const form = reactive(buildEmpty());
  const errors = ref({});
  const activeLocale = ref("en");
  const editing = ref(null);

  /**
   * `year_select` is an editor-only alias; resolving it here means the settings
   * panel, the preview and the payload builders all see `select`.
   */
  const editorType = computed(() => resolveEditorType(form.type));
  const typeConfig = computed(() => getTypeConfig(editorType.value));

  /** Raw type on purpose: a preset-backed alias hides the manual options editor. */
  const showOptions = computed(() => hasOptions(form.type) && optionsVisible(form));

  /** One language tab drives all three translatable inputs. */
  const translatableProxy = (key) =>
    computed({
      get: () => form[key][activeLocale.value] ?? "",
      set: (value) => {
        form[key] = { ...form[key], [activeLocale.value]: value };
      },
    });

  const labelField = translatableProxy("label");
  const placeholderField = translatableProxy("placeholder");
  const helpTextField = translatableProxy("help_text");

  const localizedLabelErrors = computed(
    () => errors.value[`label.${activeLocale.value}`] ?? errors.value.label ?? null
  );

  /**
   * Locale codes the server rejected something in. A translation error is
   * invisible from any other tab, so the tab itself has to carry the mark.
   */
  const localesWithErrors = computed(() =>
    FIELD_LOCALE_TABS.map((locale) => locale.value).filter((code) =>
      Object.keys(errors.value).some((key) => key.endsWith(`.${code}`))
    )
  );

  const hasEnglishLabel = computed(() => Boolean(String(form.label.en ?? "").trim()));

  /** Options with something in them, in whichever shape this surface stores. */
  const filledOptions = () =>
    optionsAs === "strings"
      ? form.options.filter((o) => String(o).trim().length > 0)
      : form.options.filter((o) => o.label || o.value);

  const previewField = computed(() =>
    previewFieldFrom(form, {
      label: Object.keys(cleanTranslatable(form.label)).length
        ? form.label
        : { en: typeLabel(form.type) },
      options: filledOptions(),
    })
  );

  /**
   * Everything catchable before the round trip. Returns the first problem or
   * null, so the submit path and any disabled state read the same rules.
   */
  const localProblem = computed(() => {
    if (!form.type) return { message: "Pick a field type" };
    if (!hasEnglishLabel.value) return { locale: "en", message: "English label is required" };

    if (showOptions.value) {
      const filled = filledOptions();
      if (!filled.length) {
        return { message: "This field type needs at least one option" };
      }
      // The value is the key answers are stored under, so a repeat makes two
      // different choices indistinguishable once they are submitted.
      const values = filled.map((o) =>
        String(optionsAs === "strings" ? o : o.value || o.label)
          .trim()
          .toLowerCase()
      );
      if (new Set(values).size !== values.length) {
        return { message: "Two options share the same value" };
      }
    }

    return null;
  });

  const resetForm = () => {
    // Assign into the existing reactive object rather than replacing it, so the
    // computed proxies and the FieldTypeSettings bindings stay wired up.
    Object.assign(form, buildEmpty());
    errors.value = {};
    activeLocale.value = "en";
  };

  const loadField = (field) => {
    errors.value = {};
    activeLocale.value = "en";
    Object.assign(form, hydrateFieldState(field, { optionsAs }), extraHydrate(field));
  };

  /**
   * Compared against the state the dialog opened with, so closing an untouched
   * dialog never asks a pointless question.
   */
  const snapshot = ref("");
  const takeSnapshot = () => {
    snapshot.value = JSON.stringify(toRaw(form));
  };
  const isDirty = computed(() => snapshot.value && JSON.stringify(form) !== snapshot.value);

  const startCreate = () => {
    editing.value = null;
    resetForm();
    takeSnapshot();
  };

  const startEdit = (field) => {
    editing.value = field;
    loadField(field);
    takeSnapshot();
  };

  /**
   * Applies a 422 body. Returns true when it was handled, so callers can skip
   * the toast: the inline messages already say it, and a toast on top covers
   * the fields it is talking about.
   */
  const applyServerErrors = (error) => {
    const body = error?.response?._data;
    if (error?.response?.status !== 422 || !body?.errors) return false;

    errors.value = body.errors;

    const failed = localesWithErrors.value;
    if (failed.length && !failed.includes(activeLocale.value)) {
      activeLocale.value = failed[0];
    }
    return true;
  };

  return {
    form,
    errors,
    editing,
    activeLocale,
    editorType,
    typeConfig,
    showOptions,
    labelField,
    placeholderField,
    helpTextField,
    localizedLabelErrors,
    localesWithErrors,
    hasEnglishLabel,
    previewField,
    localProblem,
    isDirty,
    resetForm,
    loadField,
    startCreate,
    startEdit,
    takeSnapshot,
    applyServerErrors,
  };
}
