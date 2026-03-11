<template>
  <div>
    <!-- Header: title + status -->
    <div>
      <div class="flex items-center gap-2">
        <h4 class="text-base font-medium tracking-tight">{{ doc.title }}</h4>
        <Badge
          v-if="doc.is_required && status !== 'completed'"
          variant="outline"
          class="text-xs font-normal tracking-tight"
        >
          Required
        </Badge>
        <Badge
          v-if="status === 'needs_reagreement'"
          variant="outline"
          class="border-amber-200 bg-amber-50 text-xs font-normal tracking-tight text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
        >
          Updated
        </Badge>
        <Icon
          v-if="status === 'completed'"
          name="hugeicons:checkmark-circle-02"
          class="text-success-foreground size-4 shrink-0"
        />
      </div>
      <p v-if="doc.submission_deadline" class="text-muted-foreground mt-1 text-sm tracking-tight">
        Deadline: {{ formatDeadline(doc.submission_deadline) }} ({{
          $dayjs(doc.submission_deadline).fromNow()
        }})
      </p>

      <!-- Description -->
      <div
        v-if="doc.description"
        class="prose prose-sm text-muted-foreground mt-1.5 max-w-none [&_ol]:text-xs sm:[&_ol]:text-sm [&_p]:text-xs [&_p]:leading-relaxed [&_p]:tracking-tight sm:[&_p]:text-sm [&_ul]:text-xs sm:[&_ul]:text-sm"
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
          class="border-border bg-card hover:bg-muted inline-flex w-full items-center gap-1.5 rounded-xl border p-4 text-xs font-medium tracking-tight transition-colors sm:w-auto sm:text-sm"
        >
          <Icon name="teenyicons:pdf-solid" class="text-destructive size-10" />
          View Document (EN)
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
        <a
          v-if="doc.template_id"
          :href="getMediaUrl(doc.template_id)"
          target="_blank"
          rel="noopener"
          class="border-border bg-card hover:bg-muted inline-flex w-full items-center gap-1.5 rounded-xl border p-4 text-xs font-medium tracking-tight transition-colors sm:w-auto sm:text-sm"
        >
          <Icon name="teenyicons:pdf-solid" class="text-destructive size-10" />
          View Document (ID)
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
          class="border-border bg-card hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
        >
          <Icon name="hugeicons:download-01" class="text-primary size-4.5" />
          Download Template (EN)
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
        <a
          v-if="doc.template_id"
          :href="getMediaUrl(doc.template_id)"
          target="_blank"
          rel="noopener"
          class="border-border bg-card hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
        >
          <Icon name="hugeicons:download-01" class="text-primary size-4.5" />
          Download Template (ID)
          <Icon name="hugeicons:arrow-up-right-01" class="text-muted-foreground size-3" />
        </a>
      </template>

      <!-- Example file: always open in new tab -->
      <a
        v-if="doc.example_file"
        :href="getMediaUrl(doc.example_file)"
        target="_blank"
        rel="noopener"
        class="border-border bg-card hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
      >
        <Icon name="hugeicons:file-search" class="text-muted-foreground size-4.5" />
        View Example
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
        Submission deadline has passed.
      </div>

      <!-- File Upload type -->
      <template v-else-if="doc.document_type === 'file_upload'">
        <!-- Existing uploaded file: show file info + replace option -->
        <div v-if="currentSubmission?.submission_file && !isReplacingFile" class="space-y-2.5">
          <div class="flex items-center gap-x-2">
            <Icon name="hugeicons:file-01" class="text-muted-foreground size-4 shrink-0" />
            <a
              :href="getMediaUrl(currentSubmission.submission_file)"
              target="_blank"
              class="text-primary truncate text-xs tracking-tight hover:underline sm:text-sm"
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
            Replace File
          </Button>
        </div>

        <!-- Upload area: shown when no file yet OR replacing -->
        <div v-else class="space-y-2.5">
          <Label>Upload your {{ doc.title }} here</Label>
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
              <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              {{ currentSubmission?.submission_file ? "Replace" : "Upload" }}
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
              Cancel
            </Button>
          </div>
        </div>
      </template>

      <!-- Text Input type -->
      <template v-else-if="doc.document_type === 'text_input'">
        <div v-if="status === 'completed' && !isEditingText" class="space-y-2">
          <p class="bg-muted rounded-lg px-3 py-2 text-xs tracking-tight sm:text-sm">
            {{ currentSubmission?.text_value }}
          </p>
          <Button v-if="!isPastDeadline" size="sm" variant="ghost" @click="isEditingText = true">
            <Icon name="hugeicons:edit-02" class="mr-1 size-3.5" />
            Edit
          </Button>
        </div>
        <div v-else-if="!isPastDeadline" class="space-y-2">
          <Textarea v-model="textValue" placeholder="Enter your response..." rows="3" />
          <div class="flex gap-2">
            <Button size="sm" :disabled="submitting || !textValue.trim()" @click="handleTextSubmit">
              <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              {{ currentSubmission?.text_value ? "Update" : "Submit" }}
            </Button>
            <Button
              v-if="status === 'completed'"
              size="sm"
              variant="ghost"
              @click="isEditingText = false"
            >
              Cancel
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
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

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

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return (
    d.toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" }) +
    " " +
    String(d.getHours()).padStart(2, "0") +
    ":" +
    String(d.getMinutes()).padStart(2, "0")
  );
}

const typeIcon = computed(() => {
  if (props.doc.document_type === "checkbox_agreement") return "hugeicons:file-validation";
  if (props.doc.document_type === "file_upload") return "hugeicons:file-upload";
  if (props.doc.document_type === "text_input") return "hugeicons:text-font";
  return "hugeicons:file-01";
});

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

watch(
  () => props.submission,
  (val) => {
    currentSubmission.value = val;
    textValue.value = val?.text_value || "";
  }
);

function getMediaUrl(media) {
  if (!media) return "";
  if (typeof media === "string") return media;
  return media.url || media.original || "";
}

function downloadFilename(title, lang) {
  const slug = title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
  return `${slug}-${lang}.pdf`;
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
    toast.success("File uploaded successfully");
    emit("submitted", res.data);
  } catch (err) {
    toast.error(err?.data?.message || "Failed to upload file");
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
    toast.success("Response submitted");
    emit("submitted", res.data);
  } catch (err) {
    toast.error(err?.data?.message || "Failed to submit");
  } finally {
    submitting.value = false;
  }
}
</script>
