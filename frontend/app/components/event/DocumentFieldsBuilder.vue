<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-muted-foreground text-sm tracking-tight">
        Fields exhibitors fill in when submitting this document. Drag to reorder.
      </p>
      <Button size="sm" type="button" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add field
      </Button>
    </div>

    <div v-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div
      v-else-if="!fields.length"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No fields yet. Add the first field to build this document's mini-form.
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
            <span class="text-sm font-medium tracking-tight">{{ fieldLabel(field) }}</span>
            <Badge v-if="isRequired(field)" variant="info" plain>Required</Badge>
            <Badge v-if="field.is_active === false" variant="muted" plain>Hidden</Badge>
          </div>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            {{ getTypeLabel(field.type) }}
            <template v-if="hasOptions(field.type) && field.options?.length">
              · {{ field.options.length }} option{{ field.options.length === 1 ? "" : "s" }}
            </template>
          </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button variant="ghost" size="iconSm" type="button" v-tippy="'Edit'" @click="openEditDialog(field)">
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            variant="ghost"
            size="iconSm"
            type="button"
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
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editing ? "Edit field" : "Add field" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label>Label</Label>
              <Tabs v-model="activeLocale" variant="segmented">
                <TabsList>
                  <TabsIndicator />
                  <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
                    {{ locale.label }}
                  </TabsTrigger>
                </TabsList>
              </Tabs>
              <Input
                v-model="labelField"
                :required="activeLocale === 'en'"
                :placeholder="activeLocale === 'en' ? 'e.g. Company profile' : 'Profil perusahaan'"
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

            <div class="flex items-center gap-2">
              <Switch id="doc-field-required" v-model="form.required" />
              <Label for="doc-field-required" class="cursor-pointer">Required</Label>
            </div>

            <div class="flex items-center gap-2">
              <Switch id="doc-field-active" v-model="form.is_active" />
              <Label for="doc-field-active" class="cursor-pointer">Active</Label>
            </div>

            <!-- Live preview -->
            <div v-if="form.label.en" class="space-y-2 border-t pt-3">
              <p class="text-muted-foreground text-xs tracking-tight">Preview</p>
              <div class="bg-muted/40 rounded-lg p-3">
                <CustomFieldRenderer
                  :key="form.type"
                  :field="previewField"
                  is-first
                  :model-value="previewValue"
                  locale="en"
                  disabled
                  preview
                />
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
                {{ editing ? "Save changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete field?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem ? fieldLabel(deletingItem) : "This field" }}" will be removed from this
            document. Existing submissions are not deleted.
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
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
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
import { CustomFieldRenderer, defaultValueFor, normalizeField } from "@/components/ui/custom-field";
import { useSortableList } from "@/composables/useSortableList";
import { FIELD_GROUPS, FIELD_TYPES, getTypeIcon, getTypeLabel, hasOptions } from "@/lib/formFieldTypes";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  // Full API base for this document's fields, e.g.
  // /api/projects/{u}/events/{s}/documents/{ulid}/fields
  fieldsBase: { type: String, required: true },
  // Seed rows so the list renders before the first refetch.
  initialFields: { type: Array, default: () => [] },
});

const client = useSanctumClient();

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

const EMPTY_TRANSLATABLE = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");

const fields = ref([...props.initialFields]);
const pending = ref(false);

watch(
  () => props.initialFields,
  (v) => {
    if (!fields.value.length) fields.value = [...(v ?? [])];
  }
);

async function refresh() {
  pending.value = true;
  try {
    const res = await client(props.fieldsBase);
    fields.value = res?.data ?? [];
  } catch {
    // keep whatever we already had
  } finally {
    pending.value = false;
  }
}

// Documents allow every input type; only the layout-only section divider is
// excluded (files ARE allowed here, unlike ticket contexts).
const EXCLUDED_TYPES = ["section"];

const typesByGroup = computed(() => {
  const grouped = {};
  for (const [value, config] of Object.entries(FIELD_TYPES)) {
    if (EXCLUDED_TYPES.includes(value)) continue;
    const groupKey = config.group;
    if (!grouped[groupKey]) grouped[groupKey] = [];
    grouped[groupKey].push({ value, label: config.label, icon: config.icon });
  }
  return grouped;
});

const fieldLabel = (field) =>
  field.label || field.label_translations?.en || Object.values(field.label_translations ?? {})[0] || "Untitled";

const isRequired = (field) => Boolean(field.validation?.required);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive({
  label: EMPTY_TRANSLATABLE(),
  type: "text",
  options: [],
  required: false,
  is_active: true,
});

const labelField = computed({
  get: () => form.label[activeLocale.value] ?? "",
  set: (value) => {
    form.label = { ...form.label, [activeLocale.value]: value };
  },
});

const localizedLabelErrors = computed(
  () => errors.value[`label.${activeLocale.value}`] ?? errors.value.label ?? null
);

const showOptions = computed(() => hasOptions(form.type));

const previewField = computed(() => ({
  ulid: "preview",
  type: form.type,
  label: form.label,
  options: showOptions.value ? form.options.filter((o) => String(o).trim().length > 0) : [],
  validation: { required: form.required },
  settings: {},
}));

const previewValue = computed(() => defaultValueFor(normalizeField(previewField.value, "en")));

const addOption = () => form.options.push("");
const removeOption = (index) => form.options.splice(index, 1);

const resetForm = () => {
  Object.assign(form, {
    label: EMPTY_TRANSLATABLE(),
    type: "text",
    options: [],
    required: false,
    is_active: true,
  });
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
  Object.assign(form, {
    label: {
      ...EMPTY_TRANSLATABLE(),
      ...(field.label_translations ?? (field.label ? { en: field.label } : {})),
    },
    type: field.type ?? "text",
    options: Array.isArray(field.options)
      ? field.options.map((o) => (typeof o === "object" ? o.value : o))
      : [],
    required: Boolean(field.validation?.required),
    is_active: field.is_active ?? true,
  });
  dialogOpen.value = true;
};

function cleanTranslatable(t) {
  const out = {};
  for (const [k, v] of Object.entries(t ?? {})) {
    const trimmed = v == null ? "" : String(v).trim();
    if (trimmed.length > 0) out[k] = trimmed;
  }
  return out;
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

    const payload = {
      label,
      type: form.type,
      validation: { required: form.required },
      is_active: form.is_active,
    };

    if (showOptions.value) {
      payload.options = form.options.map((o) => String(o).trim()).filter((o) => o.length > 0);
    }

    if (editing.value) {
      await client(`${props.fieldsBase}/${editing.value.ulid}`, { method: "PUT", body: payload });
      toast.success("Field updated");
    } else {
      await client(props.fieldsBase, { method: "POST", body: payload });
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
    await client(`${props.fieldsBase}/${deletingItem.value.ulid}`, { method: "DELETE" });
    toast.success("Field deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

// --- Drag reorder (PUT /reorder, integer ids) ---
const listContainer = ref(null);
useSortableList(listContainer, fields, {
  onReorder: async () => {
    const orders = fields.value.map((f, idx) => ({ id: f.id, order: idx + 1 }));
    try {
      await client(`${props.fieldsBase}/reorder`, { method: "PUT", body: { orders } });
      fields.value.forEach((f, idx) => (f.order_column = idx + 1));
    } catch {
      toast.error("Failed to reorder fields");
      await refresh();
    }
  },
});
</script>
