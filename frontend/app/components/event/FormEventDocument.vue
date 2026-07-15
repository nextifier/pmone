<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <!-- Title -->
    <div class="space-y-2">
      <Label for="title">Title</Label>
      <Input id="title" v-model="form.title" placeholder="Document title" required />
      <p v-if="errors.title" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.title) ? errors.title[0] : errors.title }}
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
        placeholder="Document content or instructions for exhibitors"
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
      <DatePicker with-time
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
          v-for="option in BOOTH_TYPE_OPTIONS"
          :key="option.value"
          class="flex items-center gap-x-2"
        >
          <Checkbox
            :id="`doc_booth_type_${option.value}`"
            :model-value="form.booth_types.includes(option.value)"
            @update:model-value="toggleBoothType(option.value)"
          />
          <Label :for="`doc_booth_type_${option.value}`" class="text-sm font-normal">
            {{ option.label }}
          </Label>
        </div>
      </div>
    </div>

    <!-- Document Files -->
    <div class="space-y-4">
      <Label class="text-sm font-medium">Document Files</Label>

      <!-- Template EN -->
      <div class="space-y-2">
        <Label class="text-muted-foreground text-xs">Template (English)</Label>
        <AttachmentLink
          v-if="existingTemplateEn && !deleteTemplateEn"
          :file="existingTemplateEn"
          :label="'Template EN'"
          fallback-name="Template EN"
        >
          <template #actions>
            <AttachmentAction type="button" aria-label="Remove" @click="deleteTemplateEn = true">
              <Icon name="hugeicons:delete-02" class="size-4" />
            </AttachmentAction>
          </template>
        </AttachmentLink>
        <InputFile
          v-else
          v-model="tmpTemplateEn"
          :accepted-file-types="['application/pdf']"
          max-file-size="50MB"
        />
      </div>

      <!-- Template ID -->
      <div class="space-y-2">
        <Label class="text-muted-foreground text-xs">Template (Indonesian)</Label>
        <AttachmentLink
          v-if="existingTemplateId && !deleteTemplateId"
          :file="existingTemplateId"
          :label="'Template ID'"
          fallback-name="Template ID"
        >
          <template #actions>
            <AttachmentAction type="button" aria-label="Remove" @click="deleteTemplateId = true">
              <Icon name="hugeicons:delete-02" class="size-4" />
            </AttachmentAction>
          </template>
        </AttachmentLink>
        <InputFile
          v-else
          v-model="tmpTemplateId"
          :accepted-file-types="['application/pdf']"
          max-file-size="50MB"
        />
      </div>

      <!-- Example File -->
      <div class="space-y-2">
        <Label class="text-muted-foreground text-xs">Example File</Label>
        <AttachmentLink
          v-if="existingExampleFile && !deleteExampleFile"
          :file="existingExampleFile"
          :label="'Example File'"
          fallback-name="Example File"
        >
          <template #actions>
            <AttachmentAction type="button" aria-label="Remove" @click="deleteExampleFile = true">
              <Icon name="hugeicons:delete-02" class="size-4" />
            </AttachmentAction>
          </template>
        </AttachmentLink>
        <InputFile
          v-else
          v-model="tmpExampleFile"
          :accepted-file-types="['application/pdf']"
          max-file-size="50MB"
        />
      </div>
    </div>

    <!-- Fields (mini-form) -->
    <div class="space-y-3 border-t pt-4">
      <div>
        <h4 class="text-sm font-semibold tracking-tighter">Fields</h4>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Questions exhibitors answer when submitting this document.
        </p>
      </div>
      <DocumentFieldsBuilder
        v-if="isEdit && props.document?.ulid"
        :fields-base="`${props.apiBase}/${props.document.ulid}/fields`"
        :initial-fields="props.document.fields || []"
        @changed="emit('changed')"
      />
      <p
        v-else
        class="text-muted-foreground rounded-md border border-dashed px-3 py-4 text-sm tracking-tight"
      >
        Save the document first, then add its fields.
      </p>
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
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

<script setup>

import InputFile from "@/components/InputFile.vue";
import DocumentFieldsBuilder from "@/components/event/DocumentFieldsBuilder.vue";
import { AttachmentAction } from "@/components/ui/attachment";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  document: { type: Object, default: null },
  apiBase: { type: String, required: true },
});
const emit = defineEmits(["success", "changed"]);

const client = useSanctumClient();
const submitting = ref(false);
const errors = ref({});
const incrementVersion = ref(false);

// File uploads
const tmpTemplateEn = ref([]);
const tmpTemplateId = ref([]);
const tmpExampleFile = ref([]);
const deleteTemplateEn = ref(false);
const deleteTemplateId = ref(false);
const deleteExampleFile = ref(false);

const existingTemplateEn = computed(() => props.document?.template_en || null);
const existingTemplateId = computed(() => props.document?.template_id || null);
const existingExampleFile = computed(() => props.document?.example_file || null);

const form = reactive({
  title: "",
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
    tmpTemplateEn.value = [];
    tmpTemplateId.value = [];
    tmpExampleFile.value = [];
    deleteTemplateEn.value = false;
    deleteTemplateId.value = false;
    deleteExampleFile.value = false;
    if (newDoc) {
      form.title = newDoc.title || "";
      form.description = newDoc.description || "";
      form.is_required = newDoc.is_required ?? true;
      form.blocks_next_step = newDoc.blocks_next_step ?? false;
      form.submission_deadline = newDoc.submission_deadline ? new Date(newDoc.submission_deadline) : null;
      form.booth_types = newDoc.booth_types || [];
    } else {
      form.title = "";
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
      description: form.description || null,
      is_required: form.is_required,
      blocks_next_step: form.blocks_next_step,
      submission_deadline: formatDateTimeForBackend(form.submission_deadline),
      booth_types: form.booth_types.length > 0 ? form.booth_types : null,
    };

    if (isEdit.value && incrementVersion.value) {
      body.increment_version = true;
    }

    // File uploads
    const enValue = tmpTemplateEn.value?.[0];
    if (enValue && enValue.startsWith("tmp-")) {
      body.tmp_template_en = enValue;
    } else if (deleteTemplateEn.value) {
      body.delete_template_en = true;
    }

    const idValue = tmpTemplateId.value?.[0];
    if (idValue && idValue.startsWith("tmp-")) {
      body.tmp_template_id = idValue;
    } else if (deleteTemplateId.value) {
      body.delete_template_id = true;
    }

    const exValue = tmpExampleFile.value?.[0];
    if (exValue && exValue.startsWith("tmp-")) {
      body.tmp_example_file = exValue;
    } else if (deleteExampleFile.value) {
      body.delete_example_file = true;
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

const { metaSymbol } = useShortcuts();

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => handleSubmit(),
  },
});
</script>
