<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-x-2">
      <div class="flex items-center gap-x-2">
        <h2 class="text-base font-semibold tracking-tighter">Form fields</h2>
        <span
          v-if="fields.length"
          class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
        >
          {{ fields.length }}
        </span>
      </div>
      <Button size="sm" @click="openAddDialog">
        <Icon name="hugeicons:add-01" class="size-4" />
        <span>Add field</span>
      </Button>
    </div>

    <!-- Sortable fields list -->
    <div v-if="fields.length" ref="sortableEl" class="space-y-2">
      <FieldCard
        v-for="(field, index) in fields"
        :key="field.id"
        :field="field"
        :depth="depthFor(index)"
        @edit="openEditDialog(field)"
        @delete="confirmDeleteField(field)"
        @duplicate="duplicateField(field)"
        @toggle-required="toggleRequired"
      />
    </div>

    <Empty v-else-if="!loadingFields" class="border border-dashed p-6 md:p-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:list-plus" />
        </EmptyMedia>
        <EmptyTitle>No fields yet</EmptyTitle>
        <EmptyDescription>
          Add your first field to start building this form.
        </EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button size="sm" @click="openAddDialog">
          <Icon name="hugeicons:add-01" class="size-4" />
          <span>Add field</span>
        </Button>
      </EmptyContent>
    </Empty>

    <div v-else class="space-y-2">
      <Skeleton v-for="i in 4" :key="i" class="h-14 w-full rounded-lg" />
    </div>

    <!-- Add/Edit Field Dialog -->
    <!-- 1080px, not 760: the editor splits into settings + live preview at
         `@4xl` and needs the room for both columns to stay usable. -->
    <ResponsiveDialog
      v-model:open="fieldDialogOpen"
      :title="editingField ? 'Edit field' : 'Add field'"
      description="Configure this form field: its label, type, validation and advanced options."
      dialog-max-width="1080px"
      :overflow-content="true"
      :prevent-close="editorDirty"
      @close-prevented="discardDialogOpen = true"
    >
      <template #default>
        <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
          <h3 class="text-foreground text-lg font-semibold tracking-tighter">
            {{ editingField ? "Edit field" : "Add field" }}
          </h3>
          <div class="mt-4">
            <FieldEditor
              v-if="fieldDialogOpen"
              ref="editorRef"
              :key="editingField?.ulid || 'new'"
              :form-slug="slug"
              :editing-field="editingField"
              @saved="handleFieldSaved"
              @cancel="requestClose"
            />
          </div>
        </div>
      </template>
    </ResponsiveDialog>

    <!-- Escape, the backdrop and Cancel all land here once something has been
         typed. A field editor holds several minutes of work and dismissing it
         is one stray click away. -->
    <ConfirmDialog
      v-model:open="discardDialogOpen"
      title="Discard changes?"
      description="This field has unsaved changes. Closing now loses them."
      confirm-label="Discard"
      variant="destructive"
      @confirm="closeFieldDialog"
    />

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Delete field?"
      :description="deleteFieldDescription"
      confirm-label="Delete"
      variant="destructive"
      :pending="deletingField"
      @confirm="deleteField"
    />
  </div>
</template>

<script setup>
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import FieldCard from "@/components/form-builder/FieldCard.vue";
import FieldEditor from "@/components/form-builder/FieldEditor.vue";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { toast } from "vue-sonner";

const props = defineProps({
  form: { type: Object, required: true },
});

/**
 * `update:fields` keeps the live preview in step with every add, edit, delete,
 * duplicate, reorder and required-toggle without a round trip through the
 * parent's form payload.
 */
const emit = defineEmits(["refresh", "update:fields"]);

const client = useSanctumClient();
const route = useRoute();
const sortableEl = ref(null);

const seededFields = Array.isArray(props.form?.fields)
  ? [...props.form.fields].sort((a, b) => (a.order_column || 0) - (b.order_column || 0))
  : [];
const fields = ref(seededFields);
const loadingFields = ref(seededFields.length === 0);
const deletingField = ref(false);

const fieldDialogOpen = ref(false);
const editingField = ref(null);
const editorRef = ref(null);
const discardDialogOpen = ref(false);

/** Drives `prevent-close`; false while the editor is untouched or unmounted. */
const editorDirty = computed(() => !!editorRef.value?.isDirty);

function requestClose() {
  if (editorDirty.value) {
    discardDialogOpen.value = true;
    return;
  }
  closeFieldDialog();
}

function closeFieldDialog() {
  discardDialogOpen.value = false;
  fieldDialogOpen.value = false;
}

const deleteDialogOpen = ref(false);
const fieldToDelete = ref(null);

const deleteFieldDescription = computed(
  () => `This will permanently delete the "${fieldToDelete.value?.label}" field.`
);

const slug = computed(() => route.params.slug);

watch(fields, (value) => emit("update:fields", value), { deep: true, immediate: true });

/**
 * Everything after a section header belongs to that section until the next one.
 * Computed from position rather than stored, so the flat list stays flat and
 * SortableJS keeps working on it unchanged.
 */
function depthFor(index) {
  if (fields.value[index]?.type === "section") return 0;
  for (let i = index - 1; i >= 0; i -= 1) {
    if (fields.value[i]?.type === "section") return 1;
  }
  return 0;
}

function openAddDialog() {
  editingField.value = null;
  fieldDialogOpen.value = true;
}

function openEditDialog(field) {
  editingField.value = field;
  fieldDialogOpen.value = true;
}

function confirmDeleteField(field) {
  fieldToDelete.value = field;
  deleteDialogOpen.value = true;
}

async function handleFieldSaved() {
  discardDialogOpen.value = false;
  fieldDialogOpen.value = false;
  editingField.value = null;
  await fetchFields();
  emit("refresh");
}

async function fetchFields() {
  try {
    if (!fields.value.length) {
      loadingFields.value = true;
    }
    const res = await client(`/api/forms/${slug.value}/fields`);
    fields.value = res.data || [];
  } catch (e) {
    console.error("Failed to load fields:", e);
  } finally {
    loadingFields.value = false;
  }
}

async function duplicateField(field) {
  try {
    await client(`/api/forms/${slug.value}/fields`, {
      method: "POST",
      body: {
        type: field.type,
        label: `${field.label} (copy)`,
        placeholder: field.placeholder || null,
        help_text: field.help_text || null,
        options: field.options || null,
        validation: field.validation || { required: false },
        settings: field.settings || {},
      },
    });
    toast.success("Field duplicated");
    await fetchFields();
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to duplicate field");
  }
}

async function toggleRequired(field, required) {
  try {
    await client(`/api/forms/${slug.value}/fields/${field.ulid}`, {
      method: "PUT",
      body: {
        validation: { ...(field.validation || {}), required: !!required },
      },
    });
    const target = fields.value.find((f) => f.ulid === field.ulid);
    if (target) {
      target.validation = { ...(target.validation || {}), required: !!required };
    }
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update field");
    await fetchFields();
  }
}

async function deleteField() {
  if (!fieldToDelete.value) return;
  deletingField.value = true;

  try {
    await client(`/api/forms/${slug.value}/fields/${fieldToDelete.value.ulid}`, {
      method: "DELETE",
    });
    toast.success("Field deleted");
    deleteDialogOpen.value = false;
    fieldToDelete.value = null;
    await fetchFields();
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete field");
  } finally {
    deletingField.value = false;
  }
}

async function updateOrder() {
  try {
    const orders = fields.value.map((f, i) => ({
      id: f.id,
      order: i + 1,
    }));

    await client(`/api/forms/${slug.value}/fields/reorder`, {
      method: "PUT",
      body: { orders },
    });
  } catch (e) {
    console.error("Failed to update order:", e);
    toast.error("Failed to save field order");
  }
}

useSortableList(sortableEl, fields, {
  onReorder: updateOrder,
});

onMounted(() => {
  // Seeded from the parent's payload; only go to the network when that was
  // empty, instead of firing a redundant request on every visit.
  if (!fields.value.length) {
    fetchFields();
  }
});
</script>
