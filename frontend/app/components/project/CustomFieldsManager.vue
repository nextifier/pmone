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
          <Icon name="hugeicons:add-01" class="-ml-1 size-4 shrink-0" />
          Add field
        </Button>
      </div>

      <div v-if="loading" class="space-y-2">
        <Skeleton v-for="i in 3" :key="i" class="h-15 w-full rounded-xl" />
      </div>

      <Empty v-else-if="!fields.length" class="border border-dashed p-6 md:p-12">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="hugeicons:list-plus" />
          </EmptyMedia>
          <EmptyTitle>No custom fields yet</EmptyTitle>
          <EmptyDescription>
            Add a field to start collecting details from brands in this project.
          </EmptyDescription>
        </EmptyHeader>
        <EmptyContent v-if="canManage">
          <Button size="sm" @click="openCreateDialog">
            <Icon name="hugeicons:add-01" class="size-4" />
            <span>Add field</span>
          </Button>
        </EmptyContent>
      </Empty>

      <div v-else ref="listContainer" class="space-y-2">
        <FieldListRow
          v-for="field in fields"
          :key="field.id"
          :field="field"
          :label="field.label"
          :icon="typeIcon(field.type)"
          :type-label="typeLabel(field.type)"
          :detail="optionCount(field)"
          :draggable="canManage"
        >
          <template #badges>
            <Badge v-if="field.is_required" variant="info" plain>Required</Badge>
            <Badge v-if="!field.is_active" variant="muted" plain>Hidden</Badge>
            <Badge v-else-if="!field.is_public" variant="warning" plain>Internal</Badge>
          </template>

          <template #actions>
            <Button
              v-if="canManage"
              variant="ghost"
              size="iconSm"
              v-tippy="'Edit'"
              :aria-label="`Edit ${field.label}`"
              @click="openEditDialog(field)"
            >
              <Icon name="hugeicons:edit-02" class="size-4" />
            </Button>
            <Button
              v-if="canManage"
              variant="ghost"
              size="iconSm"
              class="hover:bg-destructive/10 text-destructive-foreground"
              v-tippy="'Delete'"
              :aria-label="`Delete ${field.label}`"
              @click="confirmDelete(field)"
            >
              <Icon name="hugeicons:delete-02" class="size-4" />
            </Button>
          </template>
        </FieldListRow>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <ResponsiveDialog
      v-model:open="dialogOpen"
      :title="editing ? 'Edit field' : 'Add field'"
      description="Configure this brand profile field: its label, type, validation and options."
      dialog-max-width="760px"
      :overflow-content="true"
      :prevent-close="isDirty"
      @close-prevented="discardOpen = true"
    >
      <template #default>
        <FieldDialogBody
          :form="form"
          :errors="errors"
          :editing="editing"
          :saving="saving"
          :locale="activeLocale"
          :locales-with-errors="localesWithErrors"
          :label-value="labelField"
          :label-errors="localizedLabelErrors"
          label-placeholder="e.g. Business Concept"
          label-placeholder-localized="Konsep bisnis"
          :placeholder-value="placeholderField"
          :help-text-value="helpTextField"
          :settings-type="editorType"
          :exclude-types="EXCLUDED_TYPES"
          :extra-types="[YEAR_SELECT]"
          :show-options="showOptions"
          options-mode="strings"
          :allow-file-config="false"
          :preview-field="previewField"
          id-prefix="brand-field"
          @update:locale="activeLocale = $event"
          @update:label-value="labelField = $event"
          @update:placeholder-value="placeholderField = $event"
          @update:help-text-value="helpTextField = $event"
          @update:type="form.type = $event"
          @submit="handleSubmit"
          @cancel="requestClose"
        >
          <template #extra>
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
          </template>
        </FieldDialogBody>
      </template>
    </ResponsiveDialog>

    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Delete field?"
      :description="deleteDescription"
      confirm-label="Delete"
      variant="destructive"
      :pending="deleting"
      @confirm="handleDelete"
    />

    <ConfirmDialog
      v-model:open="discardOpen"
      title="Discard changes?"
      description="This field has unsaved changes. Closing now loses them."
      confirm-label="Discard"
      variant="destructive"
      @confirm="closeDialog"
    />
  </div>
</template>

<script setup>
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import FieldDialogBody from "@/components/custom-field-editor/FieldDialogBody.vue";
import FieldListRow from "@/components/custom-field-editor/FieldListRow.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Label } from "@/components/ui/label";
import ResponsiveDialog from "@/components/ui/responsive-dialog/ResponsiveDialog.vue";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { useSortableList } from "@/composables/useSortableList";
import {
  buildSettingsPayload,
  buildTranslatablePayload,
  buildValidationPayload,
  cleanTranslatable,
} from "@/lib/customFieldEditor";
import { getTypeIcon, getTypeLabel, hasOptions } from "@/lib/formFieldTypes";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
});

const emit = defineEmits(["update:fields"]);

const { hasPermission } = usePermission();

// Brand-field management is gated by the project update ability (matches the
// backend `can:projects.update` on the mutation routes).
const canManage = computed(() => hasPermission("projects.update"));

const baseUrl = computed(() => `/api/projects/${props.projectUsername}/custom-fields`);

// The convenience "Year Select" alias is brand-specific (the backend maps it to
// select + options_preset=years). It is not in the shared catalog, so inject it.
const YEAR_SELECT = {
  value: "year_select",
  label: "Year Select",
  icon: "hugeicons:calendar-02",
  group: "datetime",
};

// Brand fields exclude the same types as ticket registration (no file upload
// pipeline, no layout section, no rich text) and add the year_select alias.
const EXCLUDED_TYPES = ["file", "section", "rich_text"];

const typeLabel = (type) => (type === "year_select" ? YEAR_SELECT.label : getTypeLabel(type));
const typeIcon = (type) => (type === "year_select" ? YEAR_SELECT.icon : getTypeIcon(type));

const optionCount = (field) =>
  hasOptions(field.type) && field.options?.length
    ? `${field.options.length} option${field.options.length === 1 ? "" : "s"}`
    : "";

const {
  form,
  errors,
  editing,
  activeLocale,
  editorType,
  showOptions,
  labelField,
  placeholderField,
  helpTextField,
  localizedLabelErrors,
  localesWithErrors,
  previewField,
  localProblem,
  isDirty,
  startCreate,
  startEdit,
  applyServerErrors,
} = useCustomFieldForm({
  optionsAs: "strings",
  // Brand fields carry a public/internal toggle the other contexts do not.
  extraState: () => ({ is_public: true }),
  extraHydrate: (field) => ({ is_public: field.is_public ?? true }),
  typeLabel,
});

const {
  fields,
  loading,
  saving,
  deleteDialogOpen,
  deletingItem,
  deleting,
  fetchFields,
  saveField,
  confirmDelete,
  handleDelete,
  reorder,
} = useCustomFieldCrud({ baseUrl, idKey: "id", noun: "custom field" });

const dialogOpen = ref(false);
const discardOpen = ref(false);

const deleteDescription = computed(
  () =>
    `"${deletingItem.value?.label || "This field"}" will be removed from this project. Existing brand values are not deleted.`
);

/**
 * Streams the list out for the live preview beside this manager.
 *
 * `deep` because reordering happens in place through SortableJS - the array
 * identity never changes on a drag, so a shallow watch would miss every
 * reorder.
 */
watch(fields, (list) => emit("update:fields", list), { immediate: true, deep: true });

function openCreateDialog() {
  startCreate();
  dialogOpen.value = true;
}

function openEditDialog(field) {
  startEdit(field);
  dialogOpen.value = true;
}

function closeDialog() {
  discardOpen.value = false;
  dialogOpen.value = false;
}

function requestClose() {
  if (isDirty.value) {
    discardOpen.value = true;
    return;
  }
  closeDialog();
}

const handleSubmit = async () => {
  const problem = localProblem.value;
  if (problem) {
    if (problem.locale) activeLocale.value = problem.locale;
    toast.error(problem.message);
    return;
  }

  errors.value = {};

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

  if (await saveField(editing.value, payload, applyServerErrors)) {
    closeDialog();
  }
};

const listContainer = ref(null);
useSortableList(listContainer, fields, { enabled: canManage, onReorder: reorder });

onMounted(fetchFields);
</script>
