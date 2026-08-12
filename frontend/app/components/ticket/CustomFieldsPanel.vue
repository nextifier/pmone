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
          <Icon name="hugeicons:add-01" class="-ml-1 size-4 shrink-0" />
          Add field
        </Button>
      </div>
    </div>

    <Empty v-if="isDisabled" class="border border-dashed p-6 md:p-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:ticket-01" />
        </EmptyMedia>
        <EmptyTitle>Ticketing is off</EmptyTitle>
        <EmptyDescription>{{ disabledMessage }}</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else-if="pending" class="space-y-2">
      <Skeleton v-for="i in 3" :key="i" class="h-15 w-full rounded-xl" />
    </div>

    <Empty v-else-if="!fields.length" class="border border-dashed p-6 md:p-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:list-plus" />
        </EmptyMedia>
        <EmptyTitle>No fields yet</EmptyTitle>
        <EmptyDescription>{{ emptyMessage }}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent v-if="canCreate">
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
        :icon="getTypeIcon(field.type)"
        :type-label="getTypeLabel(field.type)"
        :detail="optionCount(field)"
        :draggable="canUpdate"
      >
        <template #badges>
          <Badge
            v-if="field.system_key"
            v-tippy="'Library field. Use the Active toggle or the field library to disable it.'"
            variant="muted"
            plain
          >
            Predefined
          </Badge>
          <Badge v-if="field.required" variant="info" plain>Required</Badge>
          <Badge v-if="!field.is_active" variant="muted" plain>Hidden</Badge>
        </template>

        <template #actions>
          <Button
            v-if="canUpdate"
            variant="ghost"
            size="iconSm"
            v-tippy="'Edit'"
            :aria-label="`Edit ${field.label}`"
            @click="openEditDialog(field)"
          >
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            v-if="canDelete && !field.system_key"
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

    <!-- Create / Edit dialog -->
    <ResponsiveDialog
      v-model:open="dialogOpen"
      :title="editing ? 'Edit field' : 'Add field'"
      description="Configure this intake field: its label, type, validation and options."
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
          label-placeholder="e.g. Company name"
          label-placeholder-localized="Nama perusahaan"
          :placeholder-value="placeholderField"
          :help-text-value="helpTextField"
          :settings-type="form.type"
          :exclude-types="EXCLUDED_TYPES"
          :type-locked="isPredefinedEditing"
          type-locked-note="Field type is fixed for library fields."
          :show-options="showOptions"
          options-mode="single"
          :options-readonly="isPredefinedEditing"
          options-readonly-note="Options for library fields are managed in the field library."
          :allow-file-config="false"
          :preview-field="previewField"
          id-prefix="custom-field"
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
              <Switch id="custom-field-active" v-model="form.is_active" />
              <Label for="custom-field-active" class="cursor-pointer">Active</Label>
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
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import FieldDialogBody from "@/components/custom-field-editor/FieldDialogBody.vue";
import FieldListRow from "@/components/custom-field-editor/FieldListRow.vue";
import PredefinedFieldsDialog from "@/components/ticket/PredefinedFieldsDialog.vue";
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
  event: { type: Object, required: true },
  // Which centralized context these fields belong to. Defaults to the historical
  // Business Matching behavior so existing callers stay unchanged.
  context: { type: String, default: "business_matching" },
  // Show the "Add from library" action + predefined-field dialog.
  library: { type: Boolean, default: false },
});

const emit = defineEmits(["update:fields"]);

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
    ? "Add the first field to start collecting registration details."
    : "Add the first field to start collecting business matching details."
);

const formNoun = computed(() =>
  isRegistration.value ? "registration form" : "Business Matching form"
);

const libraryOpen = ref(false);

const canCreate = computed(() => hasPermission("event_custom_fields.create"));
const canUpdate = computed(() => hasPermission("event_custom_fields.update"));
const canDelete = computed(() => hasPermission("event_custom_fields.delete"));

const baseUrl = computed(() => `/api/events/${props.event.id}/custom-fields`);
const listUrl = computed(() => `${baseUrl.value}?context=${props.context}`);

// SSR-friendly list: this panel renders on the server, so the fetch stays here
// rather than moving into useCustomFieldCrud, which is handed the refs instead.
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

/**
 * Streams the list out for the live preview beside this panel.
 *
 * `deep` because reordering happens in place through SortableJS - the array
 * identity never changes on a drag, so a shallow watch would miss every
 * reorder.
 */
watch(fields, (list) => emit("update:fields", list), { immediate: true, deep: true });

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

const optionCount = (field) =>
  hasOptions(field.type) && field.options?.length
    ? `${field.options.length} option${field.options.length === 1 ? "" : "s"}`
    : "";

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
} = useCustomFieldForm({
  // Preset-backed fields (e.g. birth_year via options_preset=years) generate
  // their options, so the manual editor stays hidden for them.
  optionsVisible: (draft) => !draft.settings?.options_preset,
});

const {
  saving,
  deleteDialogOpen,
  deletingItem,
  deleting,
  saveField,
  confirmDelete,
  handleDelete,
  reorder,
} = useCustomFieldCrud({
  baseUrl,
  idKey: "id",
  reorderMethod: "POST",
  reorderExtra: () => ({ context: props.context }),
  externalFields: fields,
  externalRefresh: refresh,
  externalLoading: pending,
});

const dialogOpen = ref(false);
const discardOpen = ref(false);

// Predefined (library) fields carry a system_key. Their type and options are
// curated: type is locked, options are shown read-only, and they can't be deleted.
const isPredefinedEditing = computed(() => Boolean(editing.value?.system_key));

const deleteDescription = computed(
  () =>
    `"${deletingItem.value?.label || "This field"}" will be removed from the ${formNoun.value}. Existing responses are not deleted.`
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

  if (await saveField(editing.value, payload, applyServerErrors)) {
    closeDialog();
  }
};

const listContainer = ref(null);
useSortableList(listContainer, fields, { enabled: canUpdate, onReorder: reorder });
</script>
