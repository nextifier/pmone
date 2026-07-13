<template>
  <div>
    <!-- Header: title + status -->
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h4 class="text-base font-medium tracking-tight">{{ doc.title }}</h4>
        <Badge
          v-if="doc.is_required && status !== 'completed'"
          variant="outline"
          class="text-sm font-normal tracking-tight"
        >
          {{ $t("ed.docs.required") }}
        </Badge>
        <Badge
          v-if="status === 'needs_reagreement'"
          variant="warning"
          class="text-sm font-normal tracking-tight"
        >
          {{ $t("ed.docs.updated") }}
        </Badge>
        <Icon
          v-if="status === 'completed'"
          name="hugeicons:checkmark-circle-02"
          class="text-success-foreground size-4 shrink-0"
        />
      </div>
      <p v-if="doc.submission_deadline" class="text-muted-foreground mt-1 text-sm tracking-tight">
        {{ $t("ed.docs.deadline", { date: formatDeadline(doc.submission_deadline) }) }} ({{
          $dayjs(doc.submission_deadline).fromNow()
        }})
      </p>

      <!-- Description -->
      <div
        v-if="doc.description"
        class="prose prose-sm dark:prose-invert text-muted-foreground mt-1.5 max-w-none [&_ol]:text-sm sm:[&_ol]:text-sm [&_p]:text-sm [&_p]:leading-relaxed [&_p]:tracking-tight sm:[&_p]:text-sm [&_ul]:text-sm sm:[&_ul]:text-sm"
        v-html="doc.description"
      />
    </div>

    <!-- File actions -->
    <div v-if="hasFiles" class="mt-3 flex flex-wrap gap-2">
      <!-- Event Rules / read-only docs: open PDF in new tab -->
      <template v-if="mode === 'view'">
        <a
          v-if="doc.template_en"
          :href="getMediaUrl(doc.template_en)"
          target="_blank"
          rel="noopener"
          class="border-border bg-card hover:bg-muted inline-flex w-full items-center gap-1.5 rounded-lg border p-3 text-sm font-medium tracking-tight transition-colors sm:w-auto sm:text-sm"
        >
          <Icon name="teenyicons:pdf-solid" class="text-destructive size-9" />
          {{ $t("ed.docs.viewDocEn") }}
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
        <a
          v-if="doc.template_id"
          :href="getMediaUrl(doc.template_id)"
          target="_blank"
          rel="noopener"
          class="border-border bg-card hover:bg-muted inline-flex w-full items-center gap-1.5 rounded-lg border p-3 text-sm font-medium tracking-tight transition-colors sm:w-auto sm:text-sm"
        >
          <Icon name="teenyicons:pdf-solid" class="text-destructive size-9" />
          {{ $t("ed.docs.viewDocId") }}
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
      </template>

      <!-- Operational docs: templates open in new tab -->
      <template v-else>
        <a
          v-if="doc.template_en"
          :href="getMediaUrl(doc.template_en)"
          target="_blank"
          rel="noopener"
          class="border-border bg-card hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm font-medium tracking-tight transition-colors sm:text-sm"
        >
          <Icon name="hugeicons:download-01" class="text-foreground size-4.5" />
          {{ $t("ed.docs.downloadTemplateEn") }}
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
        <a
          v-if="doc.template_id"
          :href="getMediaUrl(doc.template_id)"
          target="_blank"
          rel="noopener"
          class="border-border bg-card hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm font-medium tracking-tight transition-colors sm:text-sm"
        >
          <Icon name="hugeicons:download-01" class="text-foreground size-4.5" />
          {{ $t("ed.docs.downloadTemplateId") }}
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
      </template>

      <!-- Example file: always open in new tab -->
      <a
        v-if="doc.example_file"
        :href="getMediaUrl(doc.example_file)"
        target="_blank"
        rel="noopener"
        class="border-border bg-card hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm font-medium tracking-tight transition-colors sm:text-sm"
      >
        <Icon name="hugeicons:file-search" class="text-muted-foreground size-4.5" />
        {{ $t("ed.docs.viewExample") }}
        <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
      </a>
    </div>

    <!-- Submission area (only for action mode with apiBase) -->
    <div v-if="mode === 'action' && apiBase" class="mt-6">
      <!-- Past deadline message -->
      <div
        v-if="isPastDeadline && status !== 'completed'"
        class="text-muted-foreground bg-muted rounded-lg px-3 py-2 text-sm tracking-tight"
      >
        {{ $t("ed.docs.deadlinePassed") }}
      </div>

      <!-- Centralized custom fields (multi-field mini-form) -->
      <template v-else-if="doc.fields?.length">
        <div class="space-y-3">
          <CustomFieldGroup
            :fields="activeFields"
            :model-value="customValues"
            value-key="ulid"
            :locale="locale"
            :errors="customErrors"
            error-prefix="field_values."
            :existing-files="existingFilesByUlid"
            :upload-handler="uploadHandlers.uploadHandler"
            :revert-handler="uploadHandlers.revertHandler"
            @update:model-value="(v) => (customValues = v)"
          />
          <Button size="sm" :disabled="submitting" @click="handleCustomSubmit">
            <Spinner v-if="submitting" class="mr-1.5 size-4" />
            {{ currentSubmission ? $t("ed.docs.update") : $t("ed.docs.submit") }}
          </Button>
        </div>
      </template>

      <!-- TODO remove after production custom-fields migration: legacy single-value documents -->
      <!-- File Upload type -->
      <template v-else-if="doc.document_type === 'file_upload'">
        <!-- Existing uploaded file: show file info + replace option -->
        <div v-if="currentSubmission?.submission_file && !isReplacingFile" class="space-y-2.5">
          <div class="flex items-center gap-x-2">
            <Icon name="hugeicons:file-01" class="text-muted-foreground size-4 shrink-0" />
            <a
              :href="getMediaUrl(currentSubmission.submission_file)"
              target="_blank"
              class="text-primary truncate text-sm tracking-tight hover:underline sm:text-sm"
            >
              {{ currentSubmission.submission_file?.alt || currentSubmission.submission_file?.file_name || 'View uploaded file' }}
            </a>
            <Icon
              name="hugeicons:checkmark-circle-02"
              class="text-success-foreground size-4 shrink-0"
            />
          </div>
          <Button v-if="!isPastDeadline" size="sm" variant="outline" @click="isReplacingFile = true">
            <Icon name="hugeicons:exchange-01" class="mr-1 size-3.5" />
            {{ $t("ed.docs.replaceFile") }}
          </Button>
        </div>

        <!-- Upload area: shown when no file yet OR replacing -->
        <div v-else class="space-y-2.5">
          <Label>{{ $t("ed.docs.uploadLabel", { title: doc.title }) }}</Label>
          <InputFile
            ref="fileInputRef"
            v-model="tmpFile"
            :accepted-file-types="[
              'application/pdf',
              'image/jpeg',
              'image/png',
              'image/jpg',
              'application/msword',
              'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]"
            :allow-multiple="false"
            :max-files="1"
          />
          <div class="flex gap-2">
            <Button
              v-if="tmpFile.length"
              size="sm"
              :disabled="submitting"
              @click="handleFileUpload"
            >
              <Spinner v-if="submitting" class="mr-1.5 size-4" />
              {{ currentSubmission?.submission_file ? $t("ed.docs.replace") : $t("ed.docs.upload") }}
            </Button>
            <Button
              v-if="isReplacingFile"
              size="sm"
              variant="ghost"
              @click="
                isReplacingFile = false;
                tmpFile = [];
              "
            >
              {{ $t("ed.docs.cancel") }}
            </Button>
          </div>
        </div>
      </template>

      <!-- Text Input type -->
      <template v-else-if="doc.document_type === 'text_input'">
        <div v-if="status === 'completed' && !isEditingText" class="space-y-2">
          <p class="bg-muted rounded-lg px-3 py-2 text-sm tracking-tight sm:text-sm">
            {{ currentSubmission?.text_value }}
          </p>
          <Button v-if="!isPastDeadline" size="sm" variant="ghost" @click="isEditingText = true">
            <Icon name="hugeicons:edit-02" class="mr-1 size-3.5" />
            {{ $t("ed.docs.edit") }}
          </Button>
        </div>
        <div v-else-if="!isPastDeadline" class="space-y-2">
          <Textarea v-model="textValue" :placeholder="$t('ed.docs.placeholder')" rows="3" />
          <div class="flex gap-2">
            <Button size="sm" :disabled="submitting || !textValue.trim()" @click="handleTextSubmit">
              <Spinner v-if="submitting" class="mr-1.5 size-4" />
              {{ currentSubmission?.text_value ? $t("ed.docs.update") : $t("ed.docs.submit") }}
            </Button>
            <Button
              v-if="status === 'completed'"
              size="sm"
              variant="ghost"
              @click="isEditingText = false"
            >
              {{ $t("ed.docs.cancel") }}
            </Button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { CustomFieldGroup } from "@/components/ui/custom-field";
import { createTmpUploadHandlers } from "@/lib/uploadHandlers";
import { toast } from "vue-sonner";

const { t, locale } = useI18n();
const client = useSanctumClient();

const props = defineProps({
  doc: { type: Object, required: true },
  submission: { type: Object, default: null },
  status: { type: String, default: "pending" },
  mode: {
    type: String,
    default: "action",
    validator: (v) => ["view", "action"].includes(v),
  },
  apiBase: { type: String, default: "" },
});

const emit = defineEmits(["submitted"]);

const { $dayjs } = useNuxtApp();

const dateLocale = computed(() => (locale.value === "zh" ? "zh-CN" : "en-US"));

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return (
    d.toLocaleDateString(dateLocale.value, { day: "numeric", month: "long", year: "numeric" }) +
    " " +
    String(d.getHours()).padStart(2, "0") +
    ":" +
    String(d.getMinutes()).padStart(2, "0")
  );
}

const hasFiles = computed(() => {
  return props.doc.template_en || props.doc.template_id || props.doc.example_file;
});

const isPastDeadline = computed(() => {
  if (!props.doc.submission_deadline) return false;
  return new Date() > new Date(props.doc.submission_deadline);
});

// Submission state
const currentSubmission = ref(props.submission);
const submitting = ref(false);
const tmpFile = ref([]);
const fileInputRef = ref(null);
const textValue = ref(props.submission?.text_value || "");
const isEditingText = ref(false);
const isReplacingFile = ref(false);

// Centralized custom-field state.
const uploadHandlers = createTmpUploadHandlers(client);
const activeFields = computed(() => (props.doc.fields || []).filter((f) => f.is_active !== false));
const customValues = ref({ ...(props.submission?.field_values || {}) });
const customErrors = ref({});

// Already-submitted files, grouped by field ulid, so file fields repopulate on
// load/refresh (data comes from EventDocumentSubmissionResource `files`).
// Untagged legacy media (field_ulid === null) belongs to the synthesized
// `legacy_file` field — mirrors the backend's submissionHasFileForField rule.
const existingFilesByUlid = computed(() => {
  const map = {};
  const legacyFileUlid = activeFields.value.find(
    (f) => f.type === "file" && f.system_key === "legacy_file"
  )?.ulid;
  for (const file of currentSubmission.value?.files || []) {
    const ulid = file.field_ulid || legacyFileUlid;
    if (!ulid) continue;
    (map[ulid] ||= []).push(file);
  }
  return map;
});

watch(
  () => props.submission,
  (val) => {
    currentSubmission.value = val;
    textValue.value = val?.text_value || "";
    customValues.value = { ...(val?.field_values || {}) };
  }
);

function getMediaUrl(media) {
  if (!media) return "";
  if (typeof media === "string") return media;
  return media.url || media.original || "";
}

async function handleFileUpload() {
  if (!tmpFile.value.length || !props.apiBase) return;
  submitting.value = true;
  try {
    const res = await client(`${props.apiBase}/${props.doc.ulid}`, {
      method: "POST",
      body: { tmp_submission_file: tmpFile.value[0] },
    });
    currentSubmission.value = res.data;
    tmpFile.value = [];
    isReplacingFile.value = false;
    toast.success(t("ed.docs.fileUploaded"));
    emit("submitted", res.data);
  } catch (err) {
    toast.error(err?.data?.message || t("ed.docs.failedToUpload"));
  } finally {
    submitting.value = false;
  }
}

async function handleCustomSubmit() {
  if (!props.apiBase) return;

  const fileUlids = new Set(
    activeFields.value.filter((f) => f.type === "file").map((f) => f.ulid)
  );

  const field_values = {};
  const files = {};
  for (const [ulid, value] of Object.entries(customValues.value)) {
    if (fileUlids.has(ulid)) {
      const list = Array.isArray(value) ? value : value ? [value] : [];
      const tmp = list.filter((v) => typeof v === "string" && v.startsWith("tmp-"));
      if (tmp.length) files[ulid] = tmp.length === 1 ? tmp[0] : tmp;
    } else {
      field_values[ulid] = value;
    }
  }

  submitting.value = true;
  customErrors.value = {};
  try {
    const res = await client(`${props.apiBase}/${props.doc.ulid}`, {
      method: "POST",
      body: { field_values, files },
    });
    currentSubmission.value = res.data;
    customValues.value = { ...(res.data?.field_values || {}) };
    toast.success(t("ed.docs.responseSubmitted"));
    emit("submitted", res.data);
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      customErrors.value = err.data.errors;
    }
    toast.error(err?.data?.message || t("ed.docs.failedToSubmit"));
  } finally {
    submitting.value = false;
  }
}

async function handleTextSubmit() {
  if (!textValue.value.trim() || !props.apiBase) return;
  submitting.value = true;
  try {
    const res = await client(`${props.apiBase}/${props.doc.ulid}`, {
      method: "POST",
      body: { text_value: textValue.value.trim() },
    });
    currentSubmission.value = res.data;
    isEditingText.value = false;
    toast.success(t("ed.docs.responseSubmitted"));
    emit("submitted", res.data);
  } catch (err) {
    toast.error(err?.data?.message || t("ed.docs.failedToSubmit"));
  } finally {
    submitting.value = false;
  }
}
</script>
