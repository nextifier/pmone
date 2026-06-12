<template>
  <div class="mx-auto max-w-2xl space-y-6">
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
        <Icon name="lucide:plus" class="size-4" />
        <span>Add field</span>
      </Button>
    </div>

    <!-- Sortable fields list -->
    <div v-if="fields.length" ref="sortableEl" class="space-y-2">
      <FieldCard
        v-for="field in fields"
        :key="field.id"
        :field="field"
        @edit="openEditDialog(field)"
        @delete="confirmDeleteField(field)"
        @duplicate="duplicateField(field)"
        @toggle-required="toggleRequired"
      />
    </div>

    <Empty v-else-if="!loadingFields" class="border border-dashed p-6 md:p-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="lucide:list-plus" />
        </EmptyMedia>
        <EmptyTitle>No fields yet</EmptyTitle>
        <EmptyDescription>
          Add your first field to start building this form.
        </EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button size="sm" @click="openAddDialog">
          <Icon name="lucide:plus" class="size-4" />
          <span>Add field</span>
        </Button>
      </EmptyContent>
    </Empty>

    <div v-else class="space-y-2">
      <Skeleton v-for="i in 4" :key="i" class="h-14 w-full rounded-lg" />
    </div>

    <!-- Add/Edit Field Dialog -->
    <DialogResponsive v-model:open="fieldDialogOpen" dialog-max-width="760px" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-primary text-lg font-semibold tracking-tighter">
            {{ editingField ? "Edit field" : "Add field" }}
          </h3>
          <div class="mt-4">
            <FieldEditor
              v-if="fieldDialogOpen"
              :key="editingField?.ulid || 'new'"
              :form-slug="slug"
              :editing-field="editingField"
              @saved="handleFieldSaved"
              @cancel="fieldDialogOpen = false"
            />
          </div>
        </div>
      </template>
    </DialogResponsive>

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

const emit = defineEmits(["refresh"]);

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

const deleteDialogOpen = ref(false);
const fieldToDelete = ref(null);

const deleteFieldDescription = computed(
  () => `This will permanently delete the "${fieldToDelete.value?.label}" field.`
);

const slug = computed(() => route.params.slug);

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

onMounted(async () => {
  await fetchFields();
});
</script>
