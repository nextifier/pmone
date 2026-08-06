<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title">Custom Fields</div>
      <div class="frame-description">
        Define custom fields that brands need to fill in for this project. Drag to reorder.
      </div>
    </div>
    <div class="frame-panel">
      <div v-if="canManage" class="mb-4 flex justify-end">
        <Button size="sm" @click="openCreateDialog">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          Add field
        </Button>
      </div>

      <div v-if="loading" class="flex justify-center py-6">
        <Spinner class="size-5" />
      </div>

      <div
        v-else-if="!fields.length"
        class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
      >
        No custom fields defined yet.
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
            <Icon :name="typeIcon(field.type)" class="size-4" />
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-x-2">
              <span class="truncate text-sm font-medium tracking-tight">{{ field.label }}</span>
              <span
                v-if="field.is_required"
                class="bg-info/10 text-info-foreground border-info/20 shrink-0 rounded-md border px-1.5 py-0.5 text-xs tracking-tight"
              >
                Required
              </span>
              <span
                v-if="!field.is_active"
                class="bg-muted text-muted-foreground shrink-0 rounded-md px-1.5 py-0.5 text-xs tracking-tight"
              >
                Hidden
              </span>
              <span
                v-else-if="!field.is_public"
                class="bg-warning/10 text-warning-foreground border-warning/20 shrink-0 rounded-md border px-1.5 py-0.5 text-xs tracking-tight"
              >
                Internal
              </span>
            </div>
            <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
              {{ typeLabel(field.type) }}
              <template v-if="hasOptions(field.type) && field.options?.length">
                · {{ field.options.length }} option{{ field.options.length === 1 ? "" : "s" }}
              </template>
            </p>
          </div>

          <div class="flex shrink-0 items-center gap-1">
            <Button v-if="canManage" variant="ghost" size="iconSm" v-tippy="'Edit'" @click="openEditDialog(field)">
              <Icon name="hugeicons:edit-02" class="size-4" />
            </Button>
            <Button
              v-if="canManage"
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
    </div>

    <!-- Create / Edit dialog -->
    <ResponsiveDialog v-model:open="dialogOpen" dialog-max-width="760px" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
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
              <Label for="brand-field-label">Label</Label>
              <Input
                id="brand-field-label"
                v-model="labelField"
                :required="activeLocale === 'en'"
                :placeholder="activeLocale === 'en' ? 'e.g. Business Concept' : 'Konsep bisnis'"
              />
              <FieldError :errors="localizedLabelErrors" />
            </div>

            <div class="space-y-2">
              <Label>Field type</Label>
              <Select v-model="form.type">
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
              <FieldError :errors="errors.type" />
            </div>

            <FieldTypeSettings
              v-model:placeholder="placeholderField"
              v-model:help-text="helpTextField"
              v-model:validation="form.validation"
              v-model:settings="form.settings"
              :type="editorType"
              :errors="errors"
              :error-locale="activeLocale"
              :allow-file-config="false"
              id-prefix="brand-field"
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
                      <Input v-model="form.options[index]" :placeholder="`Option ${index + 1}`" />
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
                    </div>
                  </div>
                  <Button variant="outline" size="sm" type="button" @click="addOption">
                    <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
                    Add option
                  </Button>
                  <FieldError :errors="errors.options" />
                </div>
              </template>
            </FieldTypeSettings>

            <div class="flex items-center gap-2">
              <Switch id="brand-field-active" v-model="form.is_active" />
              <Label for="brand-field-active" class="cursor-pointer">Active</Label>
            </div>

            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <Switch id="brand-field-public" v-model="form.is_public" />
                <Label for="brand-field-public" class="cursor-pointer">Show on public page</Label>
              </div>
              <p class="text-muted-foreground text-xs tracking-tight">
                Off keeps this field internal. Brands still fill it in, but it won't appear on the
                public brand page or the live preview.
              </p>
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
          <div class="text-foreground text-lg font-semibold tracking-tighter">Delete field?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.label || "This field" }}" will be removed from this project. Existing
            brand values are not deleted.
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
  </div>
</template>

<script setup>
import FieldPreviewFrame from "@/components/custom-field-editor/FieldPreviewFrame.vue";
import FieldTypeSettings from "@/components/custom-field-editor/FieldTypeSettings.vue";
import { Button } from "@/components/ui/button";
import ResponsiveDialog from "@/components/ui/responsive-dialog/ResponsiveDialog.vue";
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
  resolveEditorType,
} from "@/lib/formFieldTypes";
import { computed, reactive, ref } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
});

const client = useSanctumClient();
const { hasPermission } = usePermission();

// Brand-field management is gated by the project update ability (matches the
// backend `can:projects.update` on the mutation routes).
const canManage = computed(() => hasPermission("projects.update"));

const baseUrl = computed(() => `/api/projects/${props.projectUsername}/custom-fields`);

// The convenience "Year Select" alias is brand-specific (the backend maps it to
// select + options_preset=years). It is not in the shared catalog, so inject it.
const YEAR_SELECT = { value: "year_select", label: "Year Select", icon: "lucide:calendar-days", group: "datetime" };

const activeLocale = ref("en");
const loading = ref(true);
const fields = ref([]);

const typeLabel = (type) => (type === "year_select" ? YEAR_SELECT.label : getTypeLabel(type));
const typeIcon = (type) => (type === "year_select" ? YEAR_SELECT.icon : getTypeIcon(type));

// Brand fields exclude the same types as ticket registration (no file upload
// pipeline, no layout section, no rich text) and add the year_select alias.
const EXCLUDED_TYPES = ["file", "section", "rich_text"];

const typesByGroup = computed(() => {
  const grouped = {};
  for (const [value, config] of Object.entries(FIELD_TYPES)) {
    if (EXCLUDED_TYPES.includes(value)) continue;
    (grouped[config.group] ||= []).push({ value, label: config.label, icon: config.icon });
  }
  (grouped[YEAR_SELECT.group] ||= []).push(YEAR_SELECT);
  return grouped;
});

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

// Brand fields keep options as plain strings and add their own public toggle.
const emptyBrandState = () => ({ ...emptyFieldState(), is_public: true });

const form = reactive(emptyBrandState());

// year_select is an editor-only alias; resolve it before reading type flags so
// the settings, preview and payload builders all see `select`.
const editorType = computed(() => resolveEditorType(form.type));

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

// Raw type on purpose: year_select generates its options from a preset, so the
// manual options editor stays hidden for it.
const showOptions = computed(() => hasOptions(form.type));

const previewField = computed(() =>
  previewFieldFrom(form, {
    label: Object.keys(cleanTranslatable(form.label)).length
      ? form.label
      : { en: typeLabel(form.type) },
    options: form.options.filter((o) => String(o).trim().length > 0),
  })
);

const addOption = () => form.options.push("");
const removeOption = (index) => form.options.splice(index, 1);

const resetForm = () => {
  // Assign into the existing reactive object rather than replacing it, so the
  // computed proxies and the FieldTypeSettings bindings stay wired up.
  Object.assign(form, emptyBrandState());
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
  Object.assign(form, hydrateFieldState(field, { optionsAs: "strings" }), {
    is_public: field.is_public ?? true,
  });
  dialogOpen.value = true;
};

async function fetchFields() {
  loading.value = true;
  try {
    const res = await client(baseUrl.value);
    fields.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load custom fields:", e);
  } finally {
    loading.value = false;
  }
}

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

    // `validation` carries required, so the legacy `is_required` flag is left
    // out: sending it makes the backend rebuild validation and drop min/max.
    // `is_public` stays - the backend folds it into settings.public.
    const payload = {
      label,
      placeholder: buildTranslatablePayload(form.placeholder),
      help_text: buildTranslatablePayload(form.help_text),
      type: form.type,
      is_active: form.is_active,
      is_public: form.is_public,
      validation: buildValidationPayload(form, editorType.value),
      settings: buildSettingsPayload(form, editorType.value, {
        presets: form.type === "year_select",
      }),
    };

    if (showOptions.value) {
      payload.options = form.options.map((o) => String(o).trim()).filter((o) => o.length > 0);
    }

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Custom field updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Custom field added");
    }
    dialogOpen.value = false;
    await fetchFields();
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error(err?.data?.message || "Failed to save custom field");
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
    toast.success("Custom field deleted");
    deleteDialogOpen.value = false;
    await fetchFields();
  } catch (err) {
    toast.error(err?.data?.message || "Failed to delete custom field");
  } finally {
    deleting.value = false;
  }
};

// --- Drag reorder ---
const listContainer = ref(null);
useSortableList(listContainer, fields, {
  enabled: canManage,
  onReorder: async () => {
    const orders = fields.value.map((f, idx) => ({ id: f.id, order: idx + 1 }));
    try {
      await client(`${baseUrl.value}/reorder`, { method: "PUT", body: { orders } });
      fields.value.forEach((f, idx) => (f.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder fields");
      await fetchFields();
    }
  },
});

onMounted(fetchFields);
</script>
