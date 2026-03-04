<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-base font-semibold tracking-tight">Form Fields</h2>
      <button
        @click="openAddDialog"
        class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="lucide:plus" class="size-4 shrink-0" />
        <span>Add Field</span>
      </button>
    </div>

    <!-- Sortable fields list -->
    <div v-if="fields.length" ref="sortableEl" class="space-y-2">
      <FieldCard
        v-for="field in fields"
        :key="field.id"
        :field="field"
        @edit="openEditDialog(field)"
        @delete="confirmDeleteField(field)"
      />
    </div>

    <div v-else-if="!loadingFields" class="text-muted-foreground py-12 text-center text-sm">
      No fields added yet. Click "Add Field" to get started.
    </div>

    <div v-else class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <!-- Add/Edit Field Dialog -->
    <DialogResponsive v-model:open="fieldDialogOpen" dialog-max-width="560px">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-primary text-lg font-semibold tracking-tight">
            {{ editingField ? "Edit Field" : "Add Field" }}
          </h3>

          <div class="mt-4 space-y-4">
            <!-- Type selector (only for new fields) -->
            <div v-if="!editingField" class="space-y-2">
              <label class="text-sm font-medium">Field Type</label>
              <FieldTypeSelector :selected="fieldForm.type" @select="fieldForm.type = $event" />
            </div>

            <div v-else class="flex items-center gap-x-2">
              <span class="text-muted-foreground text-sm">Type:</span>
              <span class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs">
                {{ fieldTypeLabel(fieldForm.type) }}
              </span>
            </div>

            <div class="space-y-2">
              <label for="field_label" class="text-sm font-medium">Label</label>
              <input
                id="field_label"
                v-model="fieldForm.label"
                type="text"
                required
                placeholder="Field label"
                class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
                :class="{ 'border-destructive': fieldErrors.label }"
              />
              <p v-if="fieldErrors.label" class="text-destructive text-xs">
                {{ fieldErrors.label[0] }}
              </p>
            </div>

            <div class="space-y-2">
              <label for="field_placeholder" class="text-sm font-medium">Placeholder</label>
              <input
                id="field_placeholder"
                v-model="fieldForm.placeholder"
                type="text"
                placeholder="Placeholder text"
                class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
              />
            </div>

            <div class="space-y-2">
              <label for="field_help_text" class="text-sm font-medium">Help Text</label>
              <textarea
                id="field_help_text"
                v-model="fieldForm.help_text"
                rows="2"
                placeholder="Help text for this field"
                class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
              />
            </div>

            <!-- Options (for select, multi_select, checkbox_group, radio) -->
            <div v-if="hasOptions(fieldForm.type)" class="space-y-2">
              <label class="text-sm font-medium">Options</label>
              <div class="space-y-2">
                <div
                  v-for="(option, idx) in fieldForm.options"
                  :key="idx"
                  class="flex items-center gap-x-2"
                >
                  <input
                    v-model="fieldForm.options[idx].label"
                    type="text"
                    placeholder="Option label"
                    class="border-border bg-background focus:ring-primary flex-1 rounded-md border px-3 py-1.5 text-sm tracking-tight focus:ring-2 focus:outline-none"
                  />
                  <input
                    v-model="fieldForm.options[idx].value"
                    type="text"
                    placeholder="Value"
                    class="border-border bg-background focus:ring-primary w-24 rounded-md border px-3 py-1.5 text-sm tracking-tight focus:ring-2 focus:outline-none"
                  />
                  <button
                    type="button"
                    @click="fieldForm.options.splice(idx, 1)"
                    class="text-muted-foreground hover:text-destructive rounded p-1 transition"
                  >
                    <Icon name="lucide:x" class="size-4" />
                  </button>
                </div>
              </div>
              <button
                type="button"
                @click="fieldForm.options.push({ label: '', value: '' })"
                class="text-primary flex items-center gap-x-1 text-sm font-medium"
              >
                <Icon name="lucide:plus" class="size-3.5" />
                <span>Add Option</span>
              </button>
            </div>

            <!-- Validation -->
            <div class="border-border space-y-3 rounded-lg border p-3">
              <h4 class="text-sm font-medium">Validation</h4>

              <div class="flex items-center gap-2">
                <Switch v-model="fieldForm.validation.required" />
                <label class="text-sm">Required</label>
              </div>

              <div
                v-if="showMinMax(fieldForm.type)"
                class="grid grid-cols-2 gap-3"
              >
                <div class="space-y-1">
                  <label class="text-xs font-medium">Min</label>
                  <input
                    v-model.number="fieldForm.validation.min"
                    type="number"
                    placeholder="Min"
                    class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-1.5 text-sm focus:ring-2 focus:outline-none"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-medium">Max</label>
                  <input
                    v-model.number="fieldForm.validation.max"
                    type="number"
                    placeholder="Max"
                    class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-1.5 text-sm focus:ring-2 focus:outline-none"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="fieldDialogOpen = false"
              :disabled="savingField"
            >
              Cancel
            </button>
            <button
              @click="saveField"
              :disabled="savingField || !fieldForm.label || !fieldForm.type"
              class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="savingField" class="size-4" />
              <span v-else>{{ editingField ? "Update Field" : "Add Field" }}</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Confirmation Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete field?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This will permanently delete the "{{ fieldToDelete?.label }}" field.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteDialogOpen = false"
              :disabled="deletingField"
            >
              Cancel
            </button>
            <button
              @click="deleteField"
              :disabled="deletingField"
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="deletingField" class="size-4 text-white" />
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { useSortable } from "@vueuse/integrations/useSortable";
import DialogResponsive from "@/components/DialogResponsive.vue";
import FieldCard from "@/components/form-builder/FieldCard.vue";
import FieldTypeSelector from "@/components/form-builder/FieldTypeSelector.vue";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  form: { type: Object, required: true },
});

const emit = defineEmits(["refresh"]);

const client = useSanctumClient();
const route = useRoute();
const sortableEl = ref(null);
const fields = ref([]);
const loadingFields = ref(true);
const savingField = ref(false);
const deletingField = ref(false);

// Field dialog state
const fieldDialogOpen = ref(false);
const editingField = ref(null);
const fieldErrors = ref({});

const fieldForm = reactive({
  type: "text",
  label: "",
  placeholder: "",
  help_text: "",
  options: [],
  validation: {
    required: false,
    min: null,
    max: null,
  },
});

// Delete dialog state
const deleteDialogOpen = ref(false);
const fieldToDelete = ref(null);

const slug = computed(() => route.params.slug);

const fieldTypeLabel = (type) => {
  const labels = {
    text: "Text",
    textarea: "Textarea",
    email: "Email",
    number: "Number",
    phone: "Phone",
    url: "URL",
    date: "Date",
    time: "Time",
    select: "Select",
    multi_select: "Multi Select",
    checkbox: "Checkbox",
    checkbox_group: "Checkbox Group",
    radio: "Radio",
    file: "File Upload",
    rating: "Rating",
    linear_scale: "Linear Scale",
  };
  return labels[type] || type;
};

const hasOptions = (type) => {
  return ["select", "multi_select", "checkbox_group", "radio"].includes(type);
};

const showMinMax = (type) => {
  return ["text", "textarea", "number", "email", "phone", "url"].includes(type);
};

function resetFieldForm() {
  fieldForm.type = "text";
  fieldForm.label = "";
  fieldForm.placeholder = "";
  fieldForm.help_text = "";
  fieldForm.options = [];
  fieldForm.validation = { required: false, min: null, max: null };
  editingField.value = null;
  fieldErrors.value = {};
}

function openAddDialog() {
  resetFieldForm();
  fieldDialogOpen.value = true;
}

function openEditDialog(field) {
  editingField.value = field;
  fieldForm.type = field.type;
  fieldForm.label = field.label;
  fieldForm.placeholder = field.placeholder || "";
  fieldForm.help_text = field.help_text || "";
  fieldForm.options = field.options
    ? field.options.map((o) => (typeof o === "string" ? { label: o, value: o } : { ...o }))
    : [];
  fieldForm.validation = {
    required: field.validation?.required || false,
    min: field.validation?.min ?? null,
    max: field.validation?.max ?? null,
  };
  fieldDialogOpen.value = true;
}

function confirmDeleteField(field) {
  fieldToDelete.value = field;
  deleteDialogOpen.value = true;
}

async function fetchFields() {
  try {
    loadingFields.value = true;
    const res = await client(`/api/forms/${slug.value}/fields`);
    fields.value = res.data || [];
  } catch (e) {
    console.error("Failed to load fields:", e);
  } finally {
    loadingFields.value = false;
  }
}

async function saveField() {
  if (!fieldForm.label || !fieldForm.type) return;
  savingField.value = true;
  fieldErrors.value = {};

  try {
    const body = {
      type: fieldForm.type,
      label: fieldForm.label,
      placeholder: fieldForm.placeholder || null,
      help_text: fieldForm.help_text || null,
      options: hasOptions(fieldForm.type) ? fieldForm.options.filter((o) => o.label) : null,
      validation: {
        required: fieldForm.validation.required,
        ...(fieldForm.validation.min != null && { min: fieldForm.validation.min }),
        ...(fieldForm.validation.max != null && { max: fieldForm.validation.max }),
      },
    };

    if (editingField.value) {
      await client(`/api/forms/${slug.value}/fields/${editingField.value.ulid}`, {
        method: "PUT",
        body,
      });
      toast.success("Field updated");
    } else {
      await client(`/api/forms/${slug.value}/fields`, {
        method: "POST",
        body,
      });
      toast.success("Field added");
    }

    fieldDialogOpen.value = false;
    resetFieldForm();
    await fetchFields();
    emit("refresh");
  } catch (e) {
    if (e.response?.status === 422 && e.response?._data?.errors) {
      fieldErrors.value = e.response._data.errors;
    }
    toast.error(e?.data?.message || e?.response?._data?.message || "Failed to save field");
  } finally {
    savingField.value = false;
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
  }
}

// Setup sortable
onMounted(async () => {
  await fetchFields();

  await nextTick();

  if (sortableEl.value) {
    useSortable(sortableEl.value, fields, {
      animation: 200,
      handle: ".drag-handle",
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      onEnd: async () => {
        await nextTick();
        await updateOrder();
      },
    });
  }
});

// Re-init sortable when fields change
watch(
  () => fields.value.length,
  async () => {
    await nextTick();
    if (sortableEl.value) {
      useSortable(sortableEl.value, fields, {
        animation: 200,
        handle: ".drag-handle",
        ghostClass: "sortable-ghost",
        chosenClass: "sortable-chosen",
        onEnd: async () => {
          await nextTick();
          await updateOrder();
        },
      });
    }
  }
);
</script>
