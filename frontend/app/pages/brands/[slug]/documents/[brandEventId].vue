<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Back + Title -->
    <div class="flex items-center gap-x-3">
      <NuxtLink
        :to="`/brands/${route.params.slug}`"
        class="text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-lg transition"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-5" />
      </NuxtLink>
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-lg font-medium tracking-tight">Event Documents</h2>
        <p v-if="pageData" class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
          {{ pageData.brand.name }} - {{ pageData.event.title }}
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="pageData">
      <!-- Empty State -->
      <div
        v-if="documents.length === 0"
        class="border-border flex flex-col items-center gap-3 rounded-xl border px-4 py-12"
      >
        <div class="bg-muted flex size-12 items-center justify-center rounded-full">
          <Icon name="hugeicons:file-01" class="text-muted-foreground size-6" />
        </div>
        <p class="text-muted-foreground text-sm">No documents required for this event.</p>
      </div>

      <!-- Document List -->
      <div v-else class="space-y-3">
        <div
          v-for="item in documents"
          :key="item.document.id"
          class="border-border rounded-xl border p-5"
        >
          <div class="flex items-start justify-between gap-x-3">
            <div class="min-w-0 flex-1 space-y-1">
              <div class="flex items-center gap-x-2">
                <h4 class="font-medium tracking-tight">{{ item.document.title }}</h4>
                <Badge v-if="item.document.is_required" variant="destructive" class="text-xs font-normal tracking-tight">
                  Required
                </Badge>
                <Badge v-if="item.document.blocks_next_step" variant="outline" class="text-xs font-normal tracking-tight">
                  Blocks
                </Badge>
              </div>
              <Badge variant="secondary" class="text-xs font-normal">
                {{ documentTypeLabel(item.document.document_type) }}
              </Badge>
            </div>

            <!-- Status Indicator -->
            <div class="shrink-0">
              <div
                v-if="getStatus(item) === 'completed'"
                class="flex items-center gap-x-1 text-green-600"
              >
                <Icon name="hugeicons:checkmark-circle-02" class="size-5" />
                <span class="text-xs font-medium tracking-tight sm:text-sm">Done</span>
              </div>
              <div
                v-else-if="getStatus(item) === 'needs_reagreement'"
                class="flex items-center gap-x-1 text-amber-600"
              >
                <Icon name="hugeicons:alert-02" class="size-5" />
                <span class="text-xs font-medium tracking-tight sm:text-sm">Updated</span>
              </div>
              <div v-else class="flex items-center gap-x-1 text-muted-foreground">
                <Icon name="hugeicons:circle" class="size-5" />
                <span class="text-xs font-medium tracking-tight sm:text-sm">Pending</span>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div
            v-if="item.document.description"
            class="prose prose-sm text-muted-foreground mt-3 max-w-none tracking-tight"
            v-html="item.document.description"
          />

          <!-- Deadline -->
          <p v-if="item.document.submission_deadline" class="text-muted-foreground mt-2 text-xs tracking-tight sm:text-sm">
            Deadline: {{ formatDate(item.document.submission_deadline) }}
          </p>

          <!-- Submission Form -->
          <div class="mt-4 space-y-3">
            <!-- Checkbox Agreement -->
            <template v-if="item.document.document_type === 'checkbox_agreement'">
              <div class="flex items-start gap-x-2">
                <Checkbox
                  :id="`agree_${item.document.id}`"
                  :checked="getStatus(item) === 'completed'"
                  :disabled="submittingId === item.document.id"
                  @click="handleAgree(item)"
                />
                <Label :for="`agree_${item.document.id}`" class="text-sm font-normal leading-snug">
                  I have read and agree to the above terms and conditions
                </Label>
              </div>
              <p v-if="getStatus(item) === 'completed' && item.submission" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Agreed on {{ formatDate(item.submission.agreed_at) }} (v{{ item.submission.document_version }})
              </p>
              <p v-if="getStatus(item) === 'needs_reagreement'" class="text-xs tracking-tight text-amber-600 sm:text-sm">
                The document has been updated. Please re-agree to the latest version.
              </p>
            </template>

            <!-- File Upload -->
            <template v-else-if="item.document.document_type === 'file_upload'">
              <!-- Existing file -->
              <div v-if="item.submission?.submission_file" class="flex items-center gap-x-2">
                <Icon name="hugeicons:file-01" class="text-muted-foreground size-4" />
                <a
                  :href="item.submission.submission_file.url || item.submission.submission_file"
                  target="_blank"
                  class="text-primary text-sm hover:underline"
                >
                  View uploaded file
                </a>
                <Icon name="hugeicons:checkmark-circle-02" class="size-4 text-green-500" />
              </div>

              <div class="space-y-2">
                <Label>Upload File</Label>
                <input
                  type="file"
                  :ref="(el) => (fileInputRefs[item.document.id] = el)"
                  @change="(e) => handleFileSelect(item, e)"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  class="border-border text-sm file:bg-muted file:text-muted-foreground block w-full rounded-lg border file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm"
                />
                <Button
                  v-if="selectedFiles[item.document.id]"
                  size="sm"
                  :disabled="submittingId === item.document.id"
                  @click="handleFileUpload(item)"
                >
                  <Icon v-if="submittingId === item.document.id" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                  Upload
                </Button>
              </div>

              <!-- Templates -->
              <div v-if="item.document.template_en || item.document.template_id || item.document.example_file" class="flex flex-wrap gap-2">
                <a
                  v-if="item.document.template_en"
                  :href="getMediaUrl(item.document.template_en)"
                  target="_blank"
                  class="text-primary inline-flex items-center gap-x-1 text-xs hover:underline"
                >
                  <Icon name="hugeicons:download-04" class="size-3" />
                  Template (EN)
                </a>
                <a
                  v-if="item.document.template_id"
                  :href="getMediaUrl(item.document.template_id)"
                  target="_blank"
                  class="text-primary inline-flex items-center gap-x-1 text-xs hover:underline"
                >
                  <Icon name="hugeicons:download-04" class="size-3" />
                  Template (ID)
                </a>
                <a
                  v-if="item.document.example_file"
                  :href="getMediaUrl(item.document.example_file)"
                  target="_blank"
                  class="text-primary inline-flex items-center gap-x-1 text-xs hover:underline"
                >
                  <Icon name="hugeicons:download-04" class="size-3" />
                  Example
                </a>
              </div>
            </template>

            <!-- Text Input -->
            <template v-else-if="item.document.document_type === 'text_input'">
              <div class="space-y-2">
                <Textarea
                  v-model="textInputs[item.document.id]"
                  placeholder="Enter your response..."
                  rows="3"
                />
                <Button
                  size="sm"
                  :disabled="submittingId === item.document.id || !textInputs[item.document.id]?.trim()"
                  @click="handleTextSubmit(item)"
                >
                  <Icon v-if="submittingId === item.document.id" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                  {{ item.submission ? 'Update' : 'Submit' }}
                </Button>
              </div>
              <p v-if="item.submission?.text_value" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Last submitted: {{ formatDate(item.submission.submitted_at) }}
              </p>
            </template>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();

const loading = ref(true);
const pageData = ref(null);
const documents = ref([]);
const submittingId = ref(null);
const selectedFiles = ref({});
const textInputs = ref({});
const fileInputRefs = ref({});

const documentTypeLabels = {
  checkbox_agreement: "Checkbox Agreement",
  file_upload: "File Upload",
  text_input: "Text Input",
};

function documentTypeLabel(type) {
  return documentTypeLabels[type] || type;
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getMediaUrl(media) {
  if (!media) return "";
  if (typeof media === "string") return media;
  return media.url || media.original || "";
}

function getStatus(item) {
  if (!item.submission) return "pending";
  if (item.submission.needs_reagreement) return "needs_reagreement";
  if (item.document.document_type === "checkbox_agreement" && item.submission.agreed_at) return "completed";
  if (item.document.document_type === "file_upload" && item.submission.submission_file) return "completed";
  if (item.document.document_type === "text_input" && item.submission.text_value) return "completed";
  return "pending";
}

async function fetchDocuments() {
  loading.value = true;
  try {
    const res = await client(
      `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/documents`
    );
    pageData.value = res.data;
    documents.value = res.data.documents || [];

    // Initialize text inputs from existing submissions
    documents.value.forEach((item) => {
      if (item.document.document_type === "text_input" && item.submission?.text_value) {
        textInputs.value[item.document.id] = item.submission.text_value;
      }
    });
  } catch (err) {
    toast.error("Failed to load documents");
  } finally {
    loading.value = false;
  }
}

async function handleAgree(item) {
  submittingId.value = item.document.id;
  try {
    const res = await client(
      `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/documents/${item.document.ulid}`,
      { method: "POST", body: {} }
    );
    item.submission = res.data;
    toast.success("Agreement recorded");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to submit");
  } finally {
    submittingId.value = null;
  }
}

function handleFileSelect(item, event) {
  const file = event.target.files?.[0];
  if (file) {
    selectedFiles.value[item.document.id] = file;
  }
}

async function handleFileUpload(item) {
  const file = selectedFiles.value[item.document.id];
  if (!file) return;

  submittingId.value = item.document.id;
  try {
    // First upload to temp
    const formData = new FormData();
    formData.append("file", file);
    const uploadRes = await client("/api/tmp-upload", {
      method: "POST",
      body: formData,
    });

    // Then submit document with temp file reference
    const res = await client(
      `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/documents/${item.document.ulid}`,
      {
        method: "POST",
        body: { tmp_submission_file: uploadRes },
      }
    );
    item.submission = res.data;
    selectedFiles.value[item.document.id] = null;
    toast.success("File uploaded successfully");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to upload file");
  } finally {
    submittingId.value = null;
  }
}

async function handleTextSubmit(item) {
  const text = textInputs.value[item.document.id]?.trim();
  if (!text) return;

  submittingId.value = item.document.id;
  try {
    const res = await client(
      `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/documents/${item.document.ulid}`,
      {
        method: "POST",
        body: { text_value: text },
      }
    );
    item.submission = res.data;
    toast.success("Response submitted");
  } catch (err) {
    toast.error(err?.data?.message || "Failed to submit");
  } finally {
    submittingId.value = null;
  }
}

onMounted(fetchDocuments);

usePageMeta(null, {
  title: computed(() => {
    if (pageData.value) {
      return `Documents - ${pageData.value.event.title}`;
    }
    return "Event Documents";
  }),
});
</script>
