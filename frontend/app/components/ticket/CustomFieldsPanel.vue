<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-muted-foreground text-sm tracking-tight">{{ panelIntro }}</p>
      <div class="flex shrink-0 items-center gap-2">
        <Button v-if="library && canCreate" size="sm" variant="outline" @click="libraryOpen = true">
          <Icon name="hugeicons:library" class="-ml-1 size-4 shrink-0" />
          Add from library
        </Button>
        <Button v-if="canCreate" size="sm" @click="openCreateDialog">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          Add field
        </Button>
      </div>
    </div>

    <div
      v-if="isDisabled"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      {{ disabledMessage }}
    </div>

    <div v-else-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div
      v-else-if="!fields.length"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      {{ emptyMessage }}
    </div>

    <div v-else ref="listContainer" class="space-y-2">
      <div
        v-for="field in fields"
        :key="field.id"
        :data-item-id="field.id"
        class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
      >
        <Icon
          name="lucide:grip-vertical"
          class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
        />

        <div
          class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
        >
          <Icon :name="getTypeIcon(field.type)" class="size-4" />
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-sm font-medium tracking-tight">{{ field.label }}</span>
            <span
              v-if="field.system_key"
              v-tippy="'Library field. Use the Active toggle or the field library to disable it.'"
              class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs tracking-tight"
            >
              Predefined
            </span>
            <span
              v-if="field.required"
              class="bg-info/10 text-info-foreground border-info/20 rounded-md border px-1.5 py-0.5 text-xs tracking-tight"
            >
              Required
            </span>
            <span
              v-if="!field.is_active"
              class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs tracking-tight"
            >
              Hidden
            </span>
          </div>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            {{ getTypeLabel(field.type) }}
            <template v-if="hasOptions(field.type) && field.options?.length">
              · {{ field.options.length }} option{{ field.options.length === 1 ? "" : "s" }}
            </template>
          </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button v-if="canUpdate" variant="ghost" size="iconSm" v-tippy="'Edit'" @click="openEditDialog(field)">
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            v-if="canDelete && !field.system_key"
            variant="ghost"
            size="iconSm"
            class="hover:bg-destructive/10 text-destructive"
            v-tippy="'Delete'"
            @click="confirmDelete(field)"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </div>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <ResponsiveDialog v-model:open="dialogOpen" dialog-max-width="760px" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editing ? "Edit Field" : "Add Field" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
            <div class="space-y-2">
              <Tabs v-model="activeLocale" variant="segmented">
                <TabsList>
                  <TabsIndicator />
                  <TabsTrigger
                    v-for="locale in FIELD_LOCALE_TABS"
                    :key="locale.value"
                    :value="locale.value"
                  >
                    {{ locale.label }}
                  </TabsTrigger>
                </TabsList>
              </Tabs>
              <p class="text-muted-foreground text-xs tracking-tight">
                The selected language applies to the label, placeholder and help text below.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="custom-field-label">Label</Label>
              <Input
                id="custom-field-label"
                v-model="labelField"
                :required="activeLocale === 'en'"
                :placeholder="activeLocale === 'en' ? 'e.g. Company name' : 'Nama perusahaan'"
              />
              <FieldError :errors="localizedLabelErrors" />
            </div>

            <div class="space-y-2">
              <Label>Field type</Label>
              <Select v-model="form.type" :disabled="isPredefinedEditing">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select a field type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup
                    v-for="group in FIELD_GROUPS"
                    v-show="typesByGroup[group.key]?.length"
                    :key="group.key"
                  >
                    <SelectLabel>{{ group.label }}</SelectLabel>
                    <SelectItem
                      v-for="type in typesByGroup[group.key]"
                      :key="type.value"
                      :value="type.value"
                    >
                      <span class="flex items-center gap-x-2">
                        <Icon :name="type.icon" class="text-muted-foreground size-4 shrink-0" />
                        {{ type.label }}
                      </span>
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <p
                v-if="isPredefinedEditing"
                class="text-muted-foreground text-xs tracking-tight"
              >
                Field type is fixed for library fields.
              </p>
              <FieldError :errors="errors.type" />
            </div>

            <FieldTypeSettings
              v-model:placeholder="placeholderField"
              v-model:help-text="helpTextField"
              v-model:validation="form.validation"
              v-model:settings="form.settings"
              :type="form.type"
              :errors="errors"
              :error-locale="activeLocale"
              :allow-file-config="false"
              id-prefix="custom-field"
            >
              <template #options>
                <div v-if="showOptions" class="space-y-2">
                  <Label>Options</Label>
                  <div class="space-y-2">
                    <div
                      v-for="(option, index) in form.options"
                      :key="index"
                      class="flex items-center gap-x-2"
                    >
                      <div
                        v-if="isPredefinedEditing"
                        class="text-muted-foreground bg-muted/40 flex-1 rounded-md border px-3 py-2 text-sm tracking-tight"
                      >
                        {{ optionText(option) }}
                      </div>
                      <template v-else>
                        <Input
                          :model-value="optionText(option)"
                          :placeholder="`Option ${index + 1}`"
                          @update:model-value="(v) => setOptionText(index, v)"
                        />
                        <Button
                          variant="ghost"
                          size="iconSm"
                          type="button"
                          class="hover:bg-destructive/10 text-destructive shrink-0"
                          v-tippy="'Remove'"
                          @click="removeOption(index)"
                        >
                          <Icon name="hugeicons:delete-02" class="size-4" />
                        </Button>
                      </template>
                    </div>
                  </div>
                  <Button
                    v-if="!isPredefinedEditing"
                    variant="outline"
                    size="sm"
                    type="button"
                    @click="addOption"
                  >
                    <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
                    Add option
                  </Button>
                  <p v-else class="text-muted-foreground text-xs tracking-tight">
                    Options for library fields are managed in the field library.
                  </p>
                  <FieldError :errors="errors.options" />
                </div>
              </template>
            </FieldTypeSettings>

            <div class="flex items-center gap-2">
              <Switch id="custom-field-active" v-model="form.is_active" />
              <Label for="custom-field-active" class="cursor-pointer">Active</Label>
            </div>

            <FieldPreviewFrame :field="previewField" :locale="activeLocale" />

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
                {{ editing ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </ResponsiveDialog>

    <!-- Delete confirmation -->
    <ResponsiveDialog v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete field?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.label || "This field" }}" will be removed from the {{ formNoun }}.
            Existing responses are not deleted.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Delete" }}
            </Button>
          </div>
        </div>
      </template>
    </ResponsiveDialog>

    <!-- Predefined field library -->
    <PredefinedFieldsDialog
      v-if="library"
      v-model:open="libraryOpen"
      :event-id="event.id"
      :context="context"
      @changed="refresh"
    />
  </div>
</template>

<script setup>
import FieldPreviewFrame from "@/components/custom-field-editor/FieldPreviewFrame.vue";
import FieldTypeSettings from "@/components/custom-field-editor/FieldTypeSettings.vue";
import { Button } from "@/components/ui/button";
import ResponsiveDialog from "@/components/ui/responsive-dialog/ResponsiveDialog.vue";
import PredefinedFieldsDialog from "@/components/ticket/PredefinedFieldsDialog.vue";
import { Input } from "@/components/ui/input";
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useSortableList } from "@/composables/useSortableList";
import {
  buildSettingsPayload,
  buildTranslatablePayload,
  buildValidationPayload,
  cleanTranslatable,
  emptyFieldState,
  FIELD_LOCALE_TABS,
  hydrateFieldState,
  previewFieldFrom,
} from "@/lib/customFieldEditor";
import {
  FIELD_GROUPS,
  FIELD_TYPES,
  getTypeIcon,
  getTypeLabel,
  hasOptions,
  localizedLabel,
} from "@/lib/formFieldTypes";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  event: { type: Object, required: true },
  // Which centralized context these fields belong to. Defaults to the historical
  // Business Matching behavior so existing callers stay unchanged.
  context: { type: String, default: "business_matching" },
  // Show the "Add from library" action + predefined-field dialog.
  library: { type: Boolean, default: false },
});

const client = useSanctumClient();
const { hasPermission } = usePermission();

const isRegistration = computed(() => props.context === "ticket_registration");

const panelIntro = computed(() =>
  isRegistration.value
    ? "Questions every attendee answers - the buyer at checkout and other attendees via their ticket links. Drag to reorder."
    : "Custom intake fields shown to visitors who opt into Business Matching. Order them to control how they appear on the form."
);

const disabledMessage = computed(() =>
  isRegistration.value
    ? "Registration fields are available once ticketing is enabled. Enable it in the Settings tab."
    : "Business Matching fields are available once ticketing is enabled. Enable it in the Settings tab."
);

const emptyMessage = computed(() =>
  isRegistration.value
    ? "No fields yet. Add the first field to start collecting registration details."
    : "No fields yet. Add the first field to start collecting business matching details."
);

const formNoun = computed(() =>
  isRegistration.value ? "registration form" : "Business Matching form"
);

const libraryOpen = ref(false);

const activeLocale = ref("en");

const canCreate = computed(() => hasPermission("event_custom_fields.create"));
const canUpdate = computed(() => hasPermission("event_custom_fields.update"));
const canDelete = computed(() => hasPermission("event_custom_fields.delete"));

const baseUrl = computed(() => `/api/events/${props.event.id}/custom-fields`);
const listUrl = computed(() => `${baseUrl.value}?context=${props.context}`);

const { data, pending, error, refresh } = await useLazySanctumFetch(() => listUrl.value, {
  key: () => `event-custom-fields-${props.context}-${props.event.id}`,
});

const fields = ref([]);
watch(
  data,
  (v) => {
    fields.value = v?.data ?? [];
  },
  { immediate: true }
);

// The custom-fields endpoint is feature-gated: it returns 404 with
// error_code TICKETS_DISABLED when ticketing is off for the event.
const isDisabled = computed(
  () => !pending.value && error.value?.data?.error_code === "TICKETS_DISABLED"
);

// Types that don't fit a ticket-checkout intake: file needs upload infra these
// contexts don't wire, and section is a layout-only divider. Ticket registration
// additionally drops rich_text (matches the backend allowedTypesFor catalog).
const EXCLUDED_TYPES = computed(() =>
  isRegistration.value ? ["file", "section", "rich_text"] : ["file", "section"]
);

// Build the field-type Select options grouped exactly like the form builder catalog.
const typesByGroup = computed(() => {
  const grouped = {};
  for (const [value, config] of Object.entries(FIELD_TYPES)) {
    if (EXCLUDED_TYPES.value.includes(value)) continue;
    const groupKey = config.group;
    if (!grouped[groupKey]) grouped[groupKey] = [];
    grouped[groupKey].push({ value, label: config.label, icon: config.icon });
  }
  return grouped;
});

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive(emptyFieldState());

// One language tab drives all three translatable inputs.
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

const previewField = computed(() =>
  previewFieldFrom(form, {
    label: Object.keys(cleanTranslatable(form.label)).length
      ? form.label
      : { en: getTypeLabel(form.type) },
    options: form.options.filter((o) => String(o.value ?? "").trim() || o.label),
  })
);

// Hide the manual options editor for preset-backed fields (e.g. birth_year uses
// settings.options_preset=years, so its options are generated, not authored).
const showOptions = computed(() => hasOptions(form.type) && !form.settings?.options_preset);

// Predefined (library) fields carry a system_key. Their type and options are
// curated: type is locked, options are shown read-only, and they can't be deleted.
const isPredefinedEditing = computed(() => Boolean(editing.value?.system_key));

const addOption = () => {
  form.options.push({ value: "", label: "" });
};

const removeOption = (index) => {
  form.options.splice(index, 1);
};

// Options are stored as { value, label } objects (label may be a {locale} map for
// predefined fields). Resolve the display text for the active language tab.
const optionText = (option) =>
  localizedLabel(option.label, activeLocale.value) || String(option.value ?? "");

// Custom fields use a single-language option where value == label. Predefined
// options are read-only, so this is never called for them (label maps stay intact).
const setOptionText = (index, text) => {
  form.options[index] = { value: text, label: text };
};

const resetForm = () => {
  // Assign into the existing reactive object rather than replacing it, so the
  // computed proxies and the FieldTypeSettings bindings stay wired up.
  Object.assign(form, emptyFieldState());
  errors.value = {};
  activeLocale.value = "en";
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (field) => {
  editing.value = field;
  errors.value = {};
  activeLocale.value = "en";
  Object.assign(form, hydrateFieldState(field));
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  if (!String(form.label.en ?? "").trim()) {
    activeLocale.value = "en";
    toast.error("English label is required");
    return;
  }

  saving.value = true;
  errors.value = {};
  try {
    const label = cleanTranslatable(form.label);
    label.en = String(form.label.en).trim();

    // `validation` carries required, so the legacy top-level `required` flag is
    // deliberately left out: sending it makes the backend rebuild validation
    // from scratch and drop min/max.
    const payload = {
      context: props.context,
      label,
      placeholder: buildTranslatablePayload(form.placeholder),
      help_text: buildTranslatablePayload(form.help_text),
      type: form.type,
      is_active: form.is_active,
      validation: buildValidationPayload(form, form.type),
      settings: buildSettingsPayload(form, form.type),
    };

    // Custom fields send their options as plain strings (the backend canonicalizes
    // to { value, label }). Predefined fields omit options entirely so their curated
    // multi-language labels are preserved untouched.
    if (showOptions.value && !isPredefinedEditing.value) {
      payload.options = form.options
        .map((o) => String(o.value ?? "").trim())
        .filter((v) => v.length > 0);
    }

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Field updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Field created");
    }
    dialogOpen.value = false;
    await refresh();
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Save failed", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);
const deleting = ref(false);

const confirmDelete = (field) => {
  deletingItem.value = field;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Field deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

// --- Drag reorder ---
const listContainer = ref(null);
useSortableList(listContainer, fields, {
  enabled: canUpdate,
  onReorder: async () => {
    const orders = fields.value.map((f, idx) => ({ id: f.id, order: idx + 1 }));
    try {
      await client(`${baseUrl.value}/reorder`, {
        method: "POST",
        body: { context: props.context, orders },
      });
      fields.value.forEach((f, idx) => (f.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder fields");
      await refresh();
    }
  },
});
</script>
