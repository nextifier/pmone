<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!--
      One component, two surfaces. `section` only gates which blocks render;
      `formData` always holds the whole record and every save posts it whole, so
      splitting the builder across two tabs can never drop the other tab's
      values. (FormController merges `settings` server-side as well.)
    -->
    <template v-if="showEditor">
      <!-- Cover Image -->
      <Field :data-invalid="!!errors?.tmp_cover_image">
        <FieldLabel>Cover image</FieldLabel>
        <InputFileImage
          ref="coverImageInputRef"
          v-model="imageFiles.cover_image"
          :initial-image="initialCoverImage"
          v-model:delete-flag="deleteFlags.cover_image"
          container-class="relative isolate aspect-[3/1] max-w-full"
        />
        <p class="text-muted-foreground text-xs sm:text-sm">1500x500 pixels recommended</p>
        <FieldError :errors="errors.tmp_cover_image" />
      </Field>

      <Field :data-invalid="!!errors?.title">
        <FieldLabel for="title">Title</FieldLabel>
        <Input
          id="title"
          v-model="formData.title"
          required
          placeholder="My Form"
          :aria-invalid="!!errors?.title"
        />
        <FieldError :errors="errors.title" />
      </Field>

      <!-- Description with TipTapEditor -->
      <Field :data-invalid="!!errors?.description">
        <FieldLabel for="description">Description</FieldLabel>
        <TipTapEditor
          v-model="formData.description"
          placeholder="Describe your form"
          :sticky="false"
          min-height="150px"
        />
        <FieldError :errors="errors.description" />
      </Field>

      <!-- Layout -->
      <div class="frame">
        <div class="frame-header">
          <h3 class="frame-title">Layout</h3>
          <p class="frame-description">
            How questions are presented to people filling in this form.
          </p>
        </div>
        <div class="frame-panel">
          <div class="space-y-1.5">
            <div class="flex items-center gap-2">
              <Switch id="multi_step" v-model="multiStep" />
              <Label for="multi_step">One question at a time</Label>
            </div>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Show a single question per screen instead of the whole form at once.
            </p>
          </div>
        </div>
      </div>
    </template>

    <template v-if="showSettings">
      <Field :data-invalid="!!errors?.slug">
        <FieldLabel for="slug">Slug</FieldLabel>
        <Input
          id="slug"
          v-model="formData.slug"
          placeholder="auto-generated"
          :aria-invalid="!!errors?.slug"
        />
        <p class="text-muted-foreground text-xs sm:text-sm">Leave empty to auto-generate from title</p>
        <FieldError :errors="errors.slug" />
      </Field>

      <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
        <Field :data-invalid="!!errors?.status">
          <FieldLabel for="status">Status</FieldLabel>
          <Select v-model="formData.status">
            <SelectTrigger id="status" class="w-full" :aria-invalid="!!errors?.status">
              <SelectValue placeholder="Select status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="published">Published</SelectItem>
              <SelectItem value="closed">Closed</SelectItem>
            </SelectContent>
          </Select>
          <FieldError :errors="errors.status" />
        </Field>

        <!-- Project with Avatar -->
        <Field :data-invalid="!!errors?.project_id">
          <FieldLabel for="project_id">Project</FieldLabel>
          <Select v-model="formData.project_id">
            <SelectTrigger class="w-full" :aria-invalid="!!errors?.project_id">
              <template #default>
                <div v-if="selectedProject" class="flex items-center gap-2">
                  <Avatar :model="selectedProject" size="sm" class="size-5" rounded="rounded" />
                  <span class="truncate">{{ selectedProject.name }}</span>
                </div>
                <span v-else class="text-muted-foreground">Select project (optional)</span>
              </template>
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="null">
                <div class="flex items-center gap-2">
                  <div class="bg-muted flex size-5 items-center justify-center rounded">
                    <Icon name="lucide:minus" class="text-muted-foreground size-3" />
                  </div>
                  <span>No project</span>
                </div>
              </SelectItem>
              <SelectItem
                v-for="project in projects"
                :key="project.id"
                :value="String(project.id)"
              >
                <div class="flex items-center gap-2">
                  <Avatar :model="project" size="sm" class="size-5" rounded="rounded" />
                  <span class="truncate">{{ project.name }}</span>
                </div>
              </SelectItem>
            </SelectContent>
          </Select>
          <FieldError :errors="errors.project_id" />
        </Field>
      </div>

      <!-- Active: only meaningful once the form is published -->
      <div v-if="formData.status === 'published'" class="space-y-1.5">
        <div class="flex items-center gap-2">
          <Switch id="is_active" v-model="formData.is_active" />
          <Label for="is_active">Active</Label>
        </div>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Pause or resume accepting responses without changing the status.
        </p>
      </div>

      <!-- Tags -->
      <Field>
        <FieldLabel>Tags</FieldLabel>
        <TagsInput v-model="formData.tags" class="text-sm">
          <TagsInputItem v-for="tag in formData.tags" :key="tag" :value="tag">
            <TagsInputItemText />
            <TagsInputItemDelete />
          </TagsInputItem>
          <TagsInputInput placeholder="Add tag" />
        </TagsInput>
        <p class="text-muted-foreground text-xs sm:text-sm">Press Enter to add a tag</p>
      </Field>

      <!-- Schedule & limit -->
      <div class="frame">
        <div class="frame-header">
          <h3 class="frame-title">Availability</h3>
          <p class="frame-description">Control when this form accepts responses.</p>
        </div>
        <div class="frame-panel space-y-6">
          <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
            <Field :data-invalid="!!errors?.opens_at">
              <FieldLabel for="opens_at">Opens at</FieldLabel>
              <DatePicker with-time
                v-model="formData.opens_at"
                placeholder="Select open date"
                :default-hour="0"
                :default-minute="0"
              />
              <FieldError :errors="errors.opens_at" />
            </Field>

            <Field :data-invalid="!!errors?.closes_at">
              <FieldLabel for="closes_at">Closes at</FieldLabel>
              <DatePicker with-time
                v-model="formData.closes_at"
                placeholder="Select close date"
                :default-hour="23"
                :default-minute="59"
              />
              <FieldError :errors="errors.closes_at" />
            </Field>
          </div>

          <Field :data-invalid="!!errors?.response_limit">
            <FieldLabel for="response_limit">Response limit</FieldLabel>
            <InputNumber
              id="response_limit"
              v-model="formData.response_limit"
              :min="0"
              placeholder="Unlimited"
              :aria-invalid="!!errors?.response_limit"
            />
            <p class="text-muted-foreground text-xs sm:text-sm">
              Maximum number of responses. Leave empty for unlimited.
            </p>
            <FieldError :errors="errors.response_limit" />
          </Field>
        </div>
      </div>

      <!-- Submission settings -->
      <div class="frame">
        <div class="frame-header">
          <h3 class="frame-title">Submission settings</h3>
          <p class="frame-description">What happens when someone submits a response.</p>
        </div>
        <div class="frame-panel space-y-6">
          <Field>
            <FieldLabel for="confirmation_message">Confirmation message</FieldLabel>
            <Textarea
              id="confirmation_message"
              v-model="formData.settings.confirmation_message"
              :rows="2"
              placeholder="Thank you for your response!"
            />
          </Field>

          <Field>
            <FieldLabel for="closed_message">Closed message</FieldLabel>
            <Textarea
              id="closed_message"
              v-model="formData.settings.closed_message"
              :rows="2"
              placeholder="This form is no longer accepting responses."
            />
            <p class="text-muted-foreground text-xs sm:text-sm">
              Shown when the form is closed or has reached its response limit.
            </p>
          </Field>

          <Field>
            <FieldLabel for="redirect_url">Redirect URL</FieldLabel>
            <InputLink id="redirect_url" v-model="formData.settings.redirect_url" />
            <p class="text-muted-foreground text-xs sm:text-sm">
              Redirect to this URL after form submission (optional)
            </p>
          </Field>

          <Field class="gap-6" :data-invalid="!!errors?.['settings.notification_emails.to']">
            <div class="grid gap-1">
              <FieldLabel>Notification emails</FieldLabel>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Each new response is emailed to these recipients.
              </p>
            </div>
            <EmailRecipientsInput
              v-model="formData.settings.notification_emails.to"
              label="To (Recipients)"
              description="Primary recipients for new responses"
              add-label="Add To Email"
            />
            <EmailRecipientsInput
              v-model="formData.settings.notification_emails.cc"
              label="CC (Carbon Copy)"
              description="Optional CC recipients"
              add-label="Add CC Email"
            />
            <EmailRecipientsInput
              v-model="formData.settings.notification_emails.bcc"
              label="BCC (Blind Carbon Copy)"
              description="Optional BCC recipients"
              add-label="Add BCC Email"
            />
            <FieldError :errors="errors['settings.notification_emails.to']" />
          </Field>

          <div class="flex items-center gap-2">
            <Switch id="require_email" v-model="formData.settings.require_email" />
            <Label for="require_email">Require email</Label>
          </div>

          <div class="flex items-center gap-2">
            <Switch id="prevent_duplicate" v-model="formData.settings.prevent_duplicate" />
            <Label for="prevent_duplicate">Prevent duplicate submissions</Label>
          </div>

          <Field v-if="formData.settings.prevent_duplicate">
            <FieldLabel for="prevent_duplicate_by">Prevent duplicate by</FieldLabel>
            <Select v-model="formData.settings.prevent_duplicate_by">
              <SelectTrigger id="prevent_duplicate_by" class="w-full">
                <SelectValue placeholder="Select method" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="email">Email</SelectItem>
                <SelectItem value="fingerprint">Browser Fingerprint</SelectItem>
                <SelectItem value="both">Both</SelectItem>
              </SelectContent>
            </Select>
          </Field>
        </div>
      </div>
    </template>

    <div v-if="showActions" class="flex gap-2">
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" class="size-4" />
        <span>{{ loading ? loadingText : submitText }}</span>
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
      <Button variant="outline" as-child>
        <nuxt-link to="/forms">Cancel</nuxt-link>
      </Button>
    </div>
  </form>
</template>

<script setup>

import { toLocalDateTimeString } from "@/lib/utils";
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import { EmailRecipientsInput } from "@/components/ui/email-recipients-input";
import { Input } from "@/components/ui/input";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputLink } from "@/components/ui/input-link";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  form: {
    type: Object,
    default: null,
  },
  prefill: {
    type: Object,
    default: null,
  },
  templateKey: {
    type: String,
    default: null,
  },
  /**
   * Which half of the builder to render. `editor` is what respondents see
   * (cover, title, description, layout); `settings` is how the form behaves
   * (slug, status, project, availability, submission). `all` keeps the original
   * single-page form for /forms/create.
   */
  section: {
    type: String,
    default: "all",
    validator: (value) => ["all", "editor", "settings"].includes(value),
  },
  /** Off when the page owns the save button (a PreviewPanel footer or header). */
  showActions: {
    type: Boolean,
    default: true,
  },
});

const showEditor = computed(() => props.section === "all" || props.section === "editor");
const showSettings = computed(() => props.section === "all" || props.section === "settings");

const emit = defineEmits(["saved", "update:preview"]);
const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();
const { signalRefresh } = useDataRefresh();

const formData = ref({
  title: "",
  description: "",
  slug: "",
  status: "draft",
  is_active: true,
  opens_at: null,
  closes_at: null,
  response_limit: null,
  project_id: null,
  tags: [],
  settings: {
    layout: "single_page",
    confirmation_message: "",
    closed_message: "",
    redirect_url: "",
    require_email: false,
    prevent_duplicate: false,
    prevent_duplicate_by: "fingerprint",
    notification_emails: { to: [], cc: [], bcc: [] },
  },
});

// Stored as an enum so a third presentation can be added without the boolean
// turning into a lie, but there are only two of them today, so the control is
// the toggle authors expect.
const multiStep = computed({
  get: () => formData.value.settings.layout === "multi_step",
  set: (on) => {
    formData.value.settings.layout = on ? "multi_step" : "single_page";
  },
});

const imageFiles = ref({ cover_image: [] });
const deleteFlags = ref({ cover_image: false });
const initialCoverImage = ref(null);
const coverImageInputRef = ref(null);

const errors = ref({});
const internalLoading = ref(false);

const submitText = computed(() => (props.mode === "create" ? "Create Form" : "Save Changes"));
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Saving..."));
const loading = computed(() => internalLoading.value);

// Fetch projects for select
const projects = ref([]);
onMounted(async () => {
  try {
    const response = await sanctumFetch("/api/projects?per_page=100");
    projects.value = response.data || [];
  } catch (e) {
    console.error("Failed to load projects:", e);
  }
});

// Selected project for Avatar display
const selectedProject = computed(() => {
  if (!formData.value.project_id) return null;
  return projects.value.find((p) => String(p.id) === String(formData.value.project_id));
});

// Apply template prefill on the create page (title/description only;
// fields are created server-side from the template key)
watch(
  () => props.prefill,
  (prefill) => {
    if (props.mode !== "create") return;
    if (prefill) {
      formData.value.title = prefill.title || "";
      formData.value.description = prefill.description || "";
    }
  },
  { immediate: true }
);

// Notification emails support a legacy flat array (to-only) and the new {to,cc,bcc} object.
const normalizeNotificationEmails = (value) => {
  if (Array.isArray(value)) {
    return { to: [...value], cc: [], bcc: [] };
  }
  return {
    to: value?.to ?? [],
    cc: value?.cc ?? [],
    bcc: value?.bcc ?? [],
  };
};

// Populate form when editing
watch(
  () => props.form,
  (newForm) => {
    if (newForm && props.mode === "edit") {
      const settings = newForm.settings || {};
      formData.value = {
        title: newForm.title || "",
        description: newForm.description || "",
        slug: newForm.slug || "",
        status: newForm.status || "draft",
        is_active: newForm.is_active ?? true,
        opens_at: newForm.opens_at ? new Date(newForm.opens_at) : null,
        closes_at: newForm.closes_at ? new Date(newForm.closes_at) : null,
        response_limit: newForm.response_limit || null,
        project_id: newForm.project?.id ? String(newForm.project.id) : null,
        tags: newForm.tags || [],
        settings: {
          layout: settings.layout || "single_page",
          confirmation_message: settings.confirmation_message || "",
          closed_message: settings.closed_message || "",
          redirect_url: settings.redirect_url || "",
          require_email: settings.require_email || false,
          prevent_duplicate: settings.prevent_duplicate || false,
          prevent_duplicate_by: settings.prevent_duplicate_by || "fingerprint",
          notification_emails: normalizeNotificationEmails(settings.notification_emails),
        },
      };
      initialCoverImage.value = newForm.cover_image || null;
    }
  },
  { immediate: true }
);

async function handleSubmit() {
  internalLoading.value = true;
  errors.value = {};

  try {
    const endpoint =
      props.mode === "create" ? "/api/forms" : `/api/forms/${props.form.slug}`;
    const method = props.mode === "create" ? "POST" : "PUT";

    const body = { ...formData.value };

    // Clean up empty values
    if (!body.slug) delete body.slug;
    body.opens_at = body.opens_at instanceof Date ? toLocalDateTimeString(body.opens_at) : null;
    body.closes_at = body.closes_at instanceof Date ? toLocalDateTimeString(body.closes_at) : null;
    if (!body.response_limit) body.response_limit = null;
    if (body.project_id) {
      body.project_id = Number(body.project_id);
    } else {
      body.project_id = null;
    }

    // Trim and drop empty notification email rows (clone settings to avoid mutating live state)
    const cleanEmails = (list) =>
      Array.isArray(list) ? list.map((email) => email.trim()).filter(Boolean) : [];
    const notificationEmails = body.settings?.notification_emails || {};
    body.settings = {
      ...body.settings,
      notification_emails: {
        to: cleanEmails(notificationEmails.to),
        cc: cleanEmails(notificationEmails.cc),
        bcc: cleanEmails(notificationEmails.bcc),
      },
    };

    // Handle cover image
    const coverValue = imageFiles.value.cover_image?.[0];
    if (coverValue && coverValue.startsWith("tmp-")) {
      body.tmp_cover_image = coverValue;
    } else if (deleteFlags.value.cover_image && !coverValue) {
      body.delete_cover_image = true;
    }

    if (props.mode === "create" && props.templateKey) {
      body.template = props.templateKey;
    }

    const response = await sanctumFetch(endpoint, {
      method,
      body,
    });

    if (response.data) {
      const successMessage =
        props.mode === "create" ? "Form created successfully!" : "Form updated successfully!";
      toast.success(successMessage);

      signalRefresh("forms-list");
      emit("saved", response.data);

      if (props.mode === "create") {
        navigateTo(`/forms/${response.data.slug}`);
      }
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || `Failed to ${props.mode} form`;
      toast.error(errorMessage);
    }
    console.error(`Error ${props.mode}ing form:`, err);
  } finally {
    internalLoading.value = false;
  }
}

/**
 * Everything the live preview needs, shaped like PublicFormResource so the
 * builder can hand it straight to PublicFormView. Cover image is the saved one:
 * a freshly picked file only exists as a temporary upload id here, and resolving
 * it to a URL would mean a round trip the preview does not need.
 */
watch(
  [formData, initialCoverImage],
  () => {
    emit("update:preview", {
      title: formData.value.title,
      description: formData.value.description,
      slug: formData.value.slug,
      status: formData.value.status,
      cover_image: deleteFlags.value.cover_image ? null : initialCoverImage.value,
      settings: {
        layout: formData.value.settings.layout,
        confirmation_message: formData.value.settings.confirmation_message,
        closed_message: formData.value.settings.closed_message,
        redirect_url: formData.value.settings.redirect_url,
        require_email: formData.value.settings.require_email,
        prevent_duplicate: false,
        prevent_duplicate_by: formData.value.settings.prevent_duplicate_by,
      },
    });
  },
  { deep: true, immediate: true }
);

defineExpose({
  handleSubmit,
  loading,
});
</script>
