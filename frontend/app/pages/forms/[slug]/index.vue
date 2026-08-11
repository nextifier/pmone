<template>
  <PreviewPanel
    v-model:tab="activeTab"
    ratio="3:2"
    offset="calc(var(--navbar-height-desktop) + 1rem)"
    :preview-title="publicFormUrl"
    reloadable
    expandable
    client-only-preview
  >
    <template #edit>
      <!--
        Centred while stacked, so this tab sits like Settings, Responses and
        Analytics do; flush left once the panes split, because there the pane
        can be wider than the measure and centring would drift the fields out of
        line with the page header above them.

        `@5xl/preview` is the panel's own container at its default threshold —
        the one place a page has to name it.
      -->
      <div class="mx-auto w-full max-w-2xl space-y-8 pb-4 @5xl/preview:mx-0">
        <!--
          Save sits under the fields it saves. It only commits the form record -
          cover, title, description, layout - while the field list below writes
          each change straight through, so a shared bar at the bottom of the
          page would have claimed to save both.
        -->
        <FormForm
          ref="formRef"
          mode="edit"
          section="editor"
          :form="form"
          @saved="emit('refresh')"
          @update:preview="previewForm = $event"
        />

        <FormBuilderFieldsManager
          :form="form"
          @refresh="emit('refresh')"
          @update:fields="previewFields = $event"
        />
      </div>
    </template>

    <template #preview-toolbar>
      <!-- Deliberately not LanguageSwitcher: that one drives the app's own
           i18n locale, and switching the preview must not re-language the
           admin around it. -->
      <template v-if="previewLocales.length > 1">
        <Button
          v-for="code in previewLocales"
          :key="code"
          size="xs"
          :variant="previewLocale === code ? 'secondary' : 'ghost'"
          class="uppercase"
          @click="previewLocale = code"
        >
          {{ code }}
        </Button>
        <span class="bg-border mx-1 h-4 w-px" />
      </template>

      <Button
        v-for="state in PREVIEW_STATES"
        :key="state.value"
        size="xs"
        :variant="previewState === state.value ? 'secondary' : 'ghost'"
        @click="previewState = state.value"
      >
        {{ state.label }}
      </Button>
    </template>

    <template #preview>
      <PublicFormView
        :form="previewPayload"
        :locale="previewLocale"
        preview
        :preview-state="previewState"
      />
    </template>

  </PreviewPanel>
</template>

<script setup>
import FormForm from "@/components/FormForm.vue";
import { Button } from "@/components/ui/button";
import { PreviewPanel } from "@/components/ui/preview-panel";
import { availableFormLocales, PublicFormView } from "@/components/ui/public-form";

const props = defineProps({
  form: { type: Object, required: true },
});

const emit = defineEmits(["refresh"]);

const PREVIEW_STATES = [
  { value: "form", label: "Form" },
  { value: "success", label: "Success" },
  { value: "closed", label: "Closed" },
];

const formRef = ref(null);
const activeTab = ref("edit");
const previewState = ref("form");
const previewLocale = ref("en");

const { pmoneUrl: publicFormUrl } = useFormPublicUrls(computed(() => props.form));

/**
 * The two halves of the preview payload, kept separate because they change from
 * different places: the form record streams live out of FormForm as the author
 * types, the fields out of the fields manager as they are added and reordered.
 */
const previewForm = ref(null);
const previewFields = ref([]);

/**
 * Shaped like PublicFormResource so PublicFormView needs no special casing.
 * Built here rather than fetched because a draft form has no public endpoint at
 * all - `PublicFormService::show()` 404s anything not published and active - so
 * this is the only way the builder can show work in progress.
 */
const previewPayload = computed(() => ({
  title: previewForm.value?.title || props.form?.title || "",
  description: previewForm.value?.description ?? props.form?.description ?? "",
  slug: previewForm.value?.slug || props.form?.slug,
  status: previewForm.value?.status || props.form?.status,
  cover_image: previewForm.value?.cover_image ?? props.form?.cover_image ?? null,
  settings: previewForm.value?.settings || props.form?.settings || {},
  fields: previewFields.value,
}));

const previewLocales = computed(() => availableFormLocales(previewPayload.value));

watch(previewLocales, (codes) => {
  if (codes.length && !codes.includes(previewLocale.value)) {
    previewLocale.value = codes[0];
  }
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => formRef.value?.handleSubmit(),
  },
});
</script>
