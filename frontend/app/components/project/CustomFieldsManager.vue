<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title">Custom Fields</div>
    </div>
    <div class="frame-panel">
      <p class="text-muted-foreground mb-4 text-sm">
        Define custom fields that brands need to fill in for this project.
      </p>

      <!-- Existing fields list -->
      <div v-if="fields.length" ref="sortableEl" class="mb-6 space-y-2">
        <div
          v-for="field in fields"
          :key="field.id"
          class="bg-muted/50 flex items-center gap-x-3 rounded-lg border px-3 py-2.5"
        >
          <Icon name="lucide:grip-vertical" class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab" />

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-x-2">
              <span class="text-sm font-medium">{{ field.label }}</span>
              <span class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs">{{ fieldTypeLabel(field.type) }}</span>
              <span v-if="field.is_required" class="text-destructive text-xs">Required</span>
            </div>
            <div v-if="field.type === 'select' && field.options?.length" class="text-muted-foreground mt-0.5 text-xs">
              Options: {{ field.options.join(', ') }}
            </div>
          </div>

          <div class="flex items-center gap-x-1">
            <button
              type="button"
              @click="editField(field)"
              class="text-muted-foreground hover:text-foreground rounded p-1 transition"
            >
              <Icon name="lucide:pencil" class="size-3.5" />
            </button>
            <button
              type="button"
              @click="deleteField(field)"
              class="text-muted-foreground hover:text-destructive rounded p-1 transition"
            >
              <Icon name="lucide:trash-2" class="size-3.5" />
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-muted-foreground mb-6 text-center text-sm py-6">
        No custom fields defined yet.
      </div>

      <!-- Add/Edit form -->
      <div class="border-t pt-4">
        <h5 class="mb-3 text-sm font-medium">{{ editing ? 'Edit Field' : 'Add New Field' }}</h5>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="cf_label">Label</Label>
            <Input id="cf_label" v-model="fieldForm.label" placeholder="e.g. Business Concept" />
          </div>

          <div class="space-y-2">
            <Label for="cf_type">Type</Label>
            <Select v-model="fieldForm.type">
              <SelectTrigger id="cf_type">
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="text">Text</SelectItem>
                <SelectItem value="number">Number</SelectItem>
                <SelectItem value="textarea">Textarea</SelectItem>
                <SelectItem value="select">Select (Dropdown)</SelectItem>
                <SelectItem value="year_select">Year Select</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="fieldForm.type === 'select'" class="space-y-2 sm:col-span-2">
            <Label>Options</Label>
            <TagsInput v-model="fieldForm.options" class="text-sm">
              <TagsInputItem
                v-for="opt in fieldForm.options"
                :key="opt"
                :value="opt"
              >
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="Add option and press Enter..." />
            </TagsInput>
          </div>

          <div class="flex items-center gap-x-2 sm:col-span-2">
            <Switch :checked="fieldForm.is_required" @update:checked="fieldForm.is_required = $event" />
            <Label class="cursor-pointer" @click="fieldForm.is_required = !fieldForm.is_required">Required</Label>
          </div>
        </div>

        <div class="mt-4 flex items-center gap-x-2">
          <Button size="sm" :disabled="!fieldForm.label || saving" @click="saveField">
            <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            {{ editing ? 'Update Field' : 'Add Field' }}
          </Button>
          <Button v-if="editing" size="sm" variant="ghost" @click="cancelEdit">
            Cancel
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useSortable } from "@vueuse/integrations/useSortable";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Switch } from "@/components/ui/switch";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
});

const client = useSanctumClient();
const sortableEl = ref(null);
const fields = ref([]);
const saving = ref(false);
const editing = ref(null);

const fieldForm = reactive({
  label: "",
  type: "text",
  options: [],
  is_required: false,
});

function fieldTypeLabel(type) {
  const labels = {
    text: "Text",
    number: "Number",
    textarea: "Textarea",
    select: "Select",
    year_select: "Year",
  };
  return labels[type] || type;
}

function resetForm() {
  fieldForm.label = "";
  fieldForm.type = "text";
  fieldForm.options = [];
  fieldForm.is_required = false;
  editing.value = null;
}

function editField(field) {
  editing.value = field.id;
  fieldForm.label = field.label;
  fieldForm.type = field.type;
  fieldForm.options = field.options || [];
  fieldForm.is_required = field.is_required;
}

function cancelEdit() {
  resetForm();
}

async function fetchFields() {
  try {
    const res = await client(`/api/projects/${props.projectUsername}/custom-fields`);
    fields.value = res.data;
  } catch (e) {
    console.error("Failed to load custom fields:", e);
  }
}

async function saveField() {
  if (!fieldForm.label) return;
  saving.value = true;

  try {
    const body = {
      label: fieldForm.label,
      type: fieldForm.type,
      options: fieldForm.type === "select" ? fieldForm.options : null,
      is_required: fieldForm.is_required,
    };

    if (editing.value) {
      await client(`/api/projects/${props.projectUsername}/custom-fields/${editing.value}`, {
        method: "PUT",
        body,
      });
      toast.success("Custom field updated");
    } else {
      await client(`/api/projects/${props.projectUsername}/custom-fields`, {
        method: "POST",
        body,
      });
      toast.success("Custom field added");
    }

    resetForm();
    await fetchFields();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to save custom field");
  } finally {
    saving.value = false;
  }
}

async function deleteField(field) {
  if (!confirm(`Delete custom field "${field.label}"? This will remove the field definition but won't delete any existing values.`)) {
    return;
  }

  try {
    await client(`/api/projects/${props.projectUsername}/custom-fields/${field.id}`, {
      method: "DELETE",
    });
    toast.success("Custom field deleted");
    await fetchFields();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete custom field");
  }
}

async function updateOrder() {
  try {
    const orders = fields.value.map((f, i) => ({
      id: f.id,
      order: i + 1,
    }));

    await client(`/api/projects/${props.projectUsername}/custom-fields/reorder`, {
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
