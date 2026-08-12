<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-muted-foreground text-sm tracking-tight">
        Fields exhibitors fill in when submitting this document. Drag to reorder.
      </p>
      <Button size="sm" type="button" @click="openCreateDialog">
        <Icon name="hugeicons:add-01" class="-ml-1 size-4 shrink-0" />
        Add field
      </Button>
    </div>

    <div v-if="pending" class="space-y-2">
      <Skeleton v-for="i in 3" :key="i" class="h-15 w-full rounded-xl" />
    </div>

    <Empty v-else-if="!fields.length" class="border border-dashed p-6 md:p-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:list-plus" />
        </EmptyMedia>
        <EmptyTitle>No fields yet</EmptyTitle>
        <EmptyDescription>
          Add the first field to build this document's mini-form.
        </EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button size="sm" type="button" @click="openCreateDialog">
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
        :label="fieldLabel(field)"
        :icon="getTypeIcon(field.type)"
        :type-label="getTypeLabel(field.type)"
        :detail="optionCount(field)"
      >
        <template #badges>
          <Badge v-if="isRequired(field)" variant="info" plain>Required</Badge>
          <Badge v-if="field.is_active === false" variant="muted" plain>Hidden</Badge>
        </template>

        <template #actions>
          <Button
            variant="ghost"
            size="iconSm"
            type="button"
            v-tippy="'Edit'"
            :aria-label="`Edit ${fieldLabel(field)}`"
            @click="openEditDialog(field)"
          >
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            variant="ghost"
            size="iconSm"
            type="button"
            class="hover:bg-destructive/10 text-destructive-foreground"
            v-tippy="'Delete'"
            :aria-label="`Delete ${fieldLabel(field)}`"
            @click="confirmDelete(field)"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </template>
      </FieldListRow>
    </div>

    <!-- Create / Edit dialog -->
    <ResponsiveDialog
      v-model:open="dialogOpen"
      :title="editing ? 'Edit field' : 'Add field'"
      description="Configure this document field: its label, type, validation and options."
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
          label-placeholder="e.g. Company profile"
          label-placeholder-localized="Profil perusahaan"
          :placeholder-value="placeholderField"
          :help-text-value="helpTextField"
          :settings-type="form.type"
          :exclude-types="EXCLUDED_TYPES"
          :show-options="showOptions"
          options-mode="strings"
          :preview-field="previewField"
          preview-disabled
          id-prefix="doc-field"
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
              <Switch id="doc-field-active" v-model="form.is_active" />
              <Label for="doc-field-active" class="cursor-pointer">Active</Label>
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
  // Full API base for this document's fields, e.g.
  // /api/projects/{u}/events/{s}/documents/{ulid}/fields
  fieldsBase: { type: String, required: true },
  // Seed rows so the list renders before the first refetch.
  initialFields: { type: Array, default: () => [] },
});

/** The document's type and filters are derived from its fields, so the list that owns this builder has to refetch. */
const emit = defineEmits(["changed"]);

// Documents allow every input type; only the layout-only section divider is
// excluded (files ARE allowed here, unlike ticket contexts).
const EXCLUDED_TYPES = ["section"];

const fieldLabel = (field) =>
  field.label ||
  field.label_translations?.en ||
  Object.values(field.label_translations ?? {})[0] ||
  "Untitled";

const isRequired = (field) => Boolean(field.validation?.required);

const optionCount = (field) =>
  hasOptions(field.type) && field.options?.length
    ? `${field.options.length} option${field.options.length === 1 ? "" : "s"}`
    : "";

const baseUrl = computed(() => props.fieldsBase);

const {
  form,
  errors,
  editing,
  activeLocale,
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
} = useCustomFieldForm({ optionsAs: "strings" });

const {
  fields,
  loading: pending,
  saving,
  deleteDialogOpen,
  deletingItem,
  deleting,
  saveField,
  confirmDelete,
  handleDelete: removeField,
  reorder,
} = useCustomFieldCrud({ baseUrl, idKey: "ulid" });

const dialogOpen = ref(false);
const discardOpen = ref(false);

// Seeded from the parent so the list renders before the first refetch.
fields.value = [...props.initialFields];
pending.value = false;

watch(
  () => props.initialFields,
  (v) => {
    if (!fields.value.length) fields.value = [...(v ?? [])];
  }
);


const deleteDescription = computed(
  () =>
    `"${deletingItem.value ? fieldLabel(deletingItem.value) : "This field"}" will be removed from this document. Existing submissions are not deleted.`
);

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

  const payload = {
    label,
    placeholder: buildTranslatablePayload(form.placeholder),
    help_text: buildTranslatablePayload(form.help_text),
    type: form.type,
    is_active: form.is_active,
    validation: buildValidationPayload(form, form.type),
    settings: buildSettingsPayload(form, form.type),
  };

  if (showOptions.value) {
    payload.options = form.options.map((o) => String(o).trim()).filter((o) => o.length > 0);
  }

  if (await saveField(editing.value, payload, applyServerErrors)) {
    closeDialog();
    emit("changed");
  }
};

/**
 * The document's own type and filters are derived from its fields, so the list
 * that owns this builder has to recompute after every change to the set.
 */
async function handleDelete() {
  await removeField();
  emit("changed");
}

const listContainer = ref(null);
useSortableList(listContainer, fields, { onReorder: reorder });
</script>
