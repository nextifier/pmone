<template>
  <PreviewPanel
    v-model:tab="activeTab"
    ratio="3:2"
    :offset="offset"
    :preview-title="previewTitle"
    reloadable
    client-only-preview
  >
    <template #edit>
      <!-- Centred while stacked, so the builder sits like every other page in
           this section; flush left once the panes split, because there the pane
           can be wider than the measure and centring would drift the controls
           out of line with the page header above them. -->
      <div class="mx-auto w-full max-w-2xl @5xl/preview:mx-0">
        <slot />
      </div>
    </template>

    <!-- Deliberately not LanguageSwitcher: that one drives the admin's own i18n
         locale, and switching the preview must not re-language the UI around
         it. -->
    <template v-if="locales.length > 1" #preview-toolbar>
      <Button
        v-for="code in locales"
        :key="code"
        size="xs"
        :variant="locale === code ? 'secondary' : 'ghost'"
        class="uppercase"
        @click="locale = code"
      >
        {{ code }}
      </Button>
    </template>

    <template #preview>
      <div class="mx-auto w-full max-w-xl px-4 py-6">
        <p
          v-if="!visibleFields.length"
          class="text-muted-foreground rounded-lg border border-dashed px-4 py-10 text-center text-sm tracking-tight"
        >
          {{ emptyMessage }}
        </p>

        <!--
          Remounted whenever the field set changes. CustomFieldGroup seeds its
          per-type default values in `onMounted` only, so a field added while
          the preview is open would otherwise render with `undefined` and its
          control would come up empty or throw.
        -->
        <CustomFieldGroup
          v-else
          :key="signature"
          v-model="values"
          :fields="visibleFields"
          :value-key="valueKey"
          :locale="locale"
          preview
        />
      </div>
    </template>
  </PreviewPanel>
</template>

<script setup>
/**
 * The shared right-hand pane for the three custom-field builders - Business
 * Matching, Brand Fields, Registration Fields. Each one used to be a list of
 * definitions with no way to see the form they add up to.
 *
 * The preview mounts the same `CustomFieldGroup` the public forms use, so what
 * shows here is the real renderer rather than a mock of it.
 */
import { Button } from "@/components/ui/button";
import { CustomFieldGroup } from "@/components/ui/custom-field";
import { PreviewPanel } from "@/components/ui/preview-panel";

const props = defineProps({
  /** Definitions straight from the manager, in display order. */
  fields: { type: Array, default: () => [] },
  /** Value-map key: "ulid" for event fields, "key" for brand profile fields. */
  valueKey: { type: String, default: "ulid" },
  previewTitle: { type: String, default: "Preview" },
  emptyMessage: {
    type: String,
    default: "Add a field to see how the form looks to the people filling it in.",
  },
  /**
   * These pages sit under a sticky TabNav, so the pinned preview has to clear
   * the navbar and that nav, not just the navbar.
   */
  offset: {
    type: String,
    default: "calc(var(--navbar-height-desktop) + var(--tabnav-height) + 1rem)",
  },
});

const activeTab = ref("edit");
const locale = ref("en");
const values = ref({});

/** Hidden fields are hidden from the people filling the form in, so from here too. */
const visibleFields = computed(() => props.fields.filter((field) => field.is_active !== false));

/**
 * Changes shape when a field is added, removed, reordered or retyped, but not
 * when only its label text changes - relabelling needs no remount and would
 * otherwise wipe whatever the previewer had typed.
 */
const signature = computed(() =>
  visibleFields.value.map((field) => `${field[props.valueKey] ?? field.id}:${field.type}`).join("|")
);

/**
 * Custom fields carry their translations inline as `{locale: string}` label
 * maps, unlike form-builder fields which keep a separate `label_translations`.
 * Same idea as `availableFormLocales`, different shape.
 */
const locales = computed(() => {
  const found = new Set();
  for (const field of props.fields) {
    if (!field?.label || typeof field.label !== "object") continue;
    for (const [code, value] of Object.entries(field.label)) {
      if (String(value ?? "").trim()) found.add(code);
    }
  }
  return [...found];
});

watch(locales, (codes) => {
  if (codes.length && !codes.includes(locale.value)) {
    locale.value = codes[0];
  }
});
</script>
