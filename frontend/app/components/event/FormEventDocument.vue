<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <!-- Title -->
    <div class="space-y-2">
      <Label for="title">Title <span class="text-destructive">*</span></Label>
      <Input id="title" v-model="form.title" placeholder="Document title" required />
      <p v-if="errors.title" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.title) ? errors.title[0] : errors.title }}
      </p>
    </div>

    <!-- Document Type -->
    <div class="space-y-2">
      <Label for="document_type">Type <span class="text-destructive">*</span></Label>
      <Select v-model="form.document_type" required>
        <SelectTrigger id="document_type" class="w-full">
          <SelectValue placeholder="Select type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="checkbox_agreement">Checkbox Agreement</SelectItem>
          <SelectItem value="file_upload">File Upload</SelectItem>
          <SelectItem value="text_input">Text Input</SelectItem>
        </SelectContent>
      </Select>
      <p class="text-muted-foreground text-xs">
        <template v-if="form.document_type === 'checkbox_agreement'">
          Exhibitors must agree to the content by checking a checkbox.
        </template>
        <template v-else-if="form.document_type === 'file_upload'">
          Exhibitors must upload a file (PDF, image, etc.).
        </template>
        <template v-else-if="form.document_type === 'text_input'">
          Exhibitors must provide a text response.
        </template>
      </p>
      <p v-if="errors.document_type" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.document_type) ? errors.document_type[0] : errors.document_type }}
      </p>
    </div>

    <!-- Description -->
    <div class="space-y-2">
      <Label for="description">Description / Content</Label>
      <TipTapEditor
        v-model="form.description"
        model-type="App\Models\EventDocument"
        collection="description_images"
        :sticky="false"
        min-height="120px"
        placeholder="Document content or instructions for exhibitors..."
      />
      <p v-if="errors.description" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
      </p>
    </div>

    <!-- Required & Blocks -->
    <div class="grid grid-cols-2 gap-x-3">
      <div class="flex items-center gap-x-2">
        <Switch id="is_required" v-model="form.is_required" />
        <Label for="is_required" class="text-sm font-normal">Required</Label>
      </div>
      <div class="flex items-center gap-x-2">
        <Switch id="blocks_next_step" v-model="form.blocks_next_step" />
        <Label for="blocks_next_step" class="text-sm font-normal">Blocks Next Step</Label>
      </div>
    </div>

    <!-- Submission Deadline -->
    <div class="space-y-2">
      <Label for="submission_deadline">Submission Deadline</Label>
      <DateTimePicker
        v-model="form.submission_deadline"
        placeholder="No deadline"
        :default-hour="23"
        :default-minute="59"
      />
      <p v-if="errors.submission_deadline" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.submission_deadline) ? errors.submission_deadline[0] : errors.submission_deadline }}
      </p>
    </div>

    <!-- Booth Types -->
    <div class="space-y-2">
      <Label>Applicable Booth Types</Label>
      <p class="text-muted-foreground text-xs">Leave unchecked to apply to all booth types.</p>
      <div class="space-y-2">
        <div
          v-for="option in boothTypeOptions"
          :key="option.value"
          class="flex items-center gap-x-2"
        >
          <Checkbox
            :id="`doc_booth_type_${option.value}`"
            :checked="form.booth_types.includes(option.value)"
            @update:checked="toggleBoothType(option.value)"
          />
          <Label :for="`doc_booth_type_${option.value}`" class="text-sm font-normal">
            {{ option.label }}
          </Label>
        </div>
      </div>
    </div>

    <!-- Increment Version (edit only) -->
    <div v-if="isEdit" class="space-y-2">
      <div class="flex items-center gap-x-2">
        <Switch id="increment_version" v-model="incrementVersion" />
        <Label for="increment_version" class="text-sm font-normal">
          Increment content version (v{{ props.document?.content_version || 1 }} -> v{{ (props.document?.content_version || 1) + 1 }})
        </Label>
      </div>
      <p class="text-muted-foreground text-xs">
        Incrementing the version will require exhibitors who already agreed to re-agree.
      </p>
    </div>

    <!-- Submit -->
    <div class="flex justify-end pt-2">
      <Button type="submit" :disabled="submitting">
        <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ isEdit ? "Update Document" : "Create Document" }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import DateTimePicker from "@/components/DateTimePicker.vue";
import TipTapEditor from "@/components/TipTapEditor.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  document: { type: Object, default: null },
  apiBase: { type: String, required: true },
});
const emit = defineEmits(["success"]);

const client = useSanctumClient();
const submitting = ref(false);
const errors = ref({});
const incrementVersion = ref(false);

const form = reactive({
  title: "",
  document_type: "checkbox_agreement",
  description: "",
  is_required: true,
  blocks_next_step: false,
  submission_deadline: null,
  booth_types: [],
});

watch(
  () => props.document,
  (newDoc) => {
    incrementVersion.value = false;
    if (newDoc) {
      form.title = newDoc.title || "";
      form.document_type = newDoc.document_type || "checkbox_agreement";
      form.description = newDoc.description || "";
      form.is_required = newDoc.is_required ?? true;
      form.blocks_next_step = newDoc.blocks_next_step ?? false;
      form.submission_deadline = newDoc.submission_deadline ? new Date(newDoc.submission_deadline) : null;
      form.booth_types = newDoc.booth_types || [];
    } else {
      form.title = "";
      form.document_type = "checkbox_agreement";
      form.description = "";
      form.is_required = true;
      form.blocks_next_step = false;
      form.submission_deadline = null;
      form.booth_types = [];
    }
  },
  { immediate: true }
);

const isEdit = computed(() => !!props.document);

const boothTypeOptions = [
  { value: "raw_space", label: "Raw Space" },
  { value: "standard_shell_scheme", label: "Standard Shell Scheme" },
  { value: "enhanced_shell_scheme", label: "Enhanced Shell Scheme" },
  { value: "table_chair_only", label: "Table & Chair Only" },
];

function toggleBoothType(value) {
  const idx = form.booth_types.indexOf(value);
  if (idx >= 0) {
    form.booth_types.splice(idx, 1);
  } else {
    form.booth_types.push(value);
  }
}

function formatDateTimeForBackend(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

async function handleSubmit() {
  submitting.value = true;
  errors.value = {};
  try {
    const url = isEdit.value ? `${props.apiBase}/${props.document.ulid}` : props.apiBase;
    const method = isEdit.value ? "PUT" : "POST";
    const body = {
      title: form.title,
      document_type: form.document_type,
      description: form.description || null,
      is_required: form.is_required,
      blocks_next_step: form.blocks_next_step,
      submission_deadline: formatDateTimeForBackend(form.submission_deadline),
      booth_types: form.booth_types.length > 0 ? form.booth_types : null,
    };

    if (isEdit.value && incrementVersion.value) {
      body.increment_version = true;
    }

    await client(url, { method, body });
    toast.success(isEdit.value ? "Document updated" : "Document created");
    emit("success");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to save document");
    }
  } finally {
    submitting.value = false;
  }
}
</script>
