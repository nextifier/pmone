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
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
            {{ editing ? "Edit Field" : "Add Field" }}
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
              <Switch id="brand-field-required" v-model="form.is_required" />
              <Label for="brand-field-required" class="cursor-pointer">Required</Label>
            </div>

            <div class="flex items-center gap-2">
              <Switch id="brand-field-active" v-model="form.is_active" />
              <Label for="brand-field-active" class="cursor-pointer">Active</Label>
            </div>

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
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
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
    </DialogResponsive>
  </div>
</template>

<script setup>
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
import { useSortableList } from "@/composables/useSortableList";
import { FIELD_GROUPS, FIELD_TYPES, getTypeIcon, getTypeLabel, hasOptions } from "@/lib/formFieldTypes";
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

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

const EMPTY_TRANSLATABLE = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

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

const form = reactive({
  label: EMPTY_TRANSLATABLE(),
  type: "text",
  options: [],
  is_required: false,
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

const addOption = () => form.options.push("");
const removeOption = (index) => form.options.splice(index, 1);

const resetForm = () => {
  Object.assign(form, {
    label: EMPTY_TRANSLATABLE(),
    type: "text",
    options: [],
    is_required: false,
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
    options: Array.isArray(field.options) ? [...field.options] : [],
    is_required: field.is_required ?? false,
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

    const payload = {
      label,
      type: form.type,
      is_required: form.is_required,
      is_active: form.is_active,
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
