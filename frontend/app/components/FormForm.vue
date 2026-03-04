<template>
  <div class="space-y-10">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Cover Image -->
      <div class="space-y-2">
        <label class="text-sm font-medium">Cover Image</label>
        <p class="text-muted-foreground text-xs">1500x500 pixels recommended</p>
        <InputFileImage
          ref="coverImageInputRef"
          v-model="imageFiles.cover_image"
          :initial-image="initialCoverImage"
          v-model:delete-flag="deleteFlags.cover_image"
          container-class="relative isolate aspect-[3/1] max-w-full"
        />
        <p v-if="errors.tmp_cover_image" class="text-destructive text-xs">
          {{ errors.tmp_cover_image[0] }}
        </p>
      </div>

      <div class="space-y-2">
        <label for="title" class="text-sm font-medium">Title</label>
        <input
          id="title"
          v-model="formData.title"
          type="text"
          required
          placeholder="My Form"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.title }"
        />
        <p v-if="errors.title" class="text-destructive text-xs">{{ errors.title[0] }}</p>
      </div>

      <!-- Description with TipTapEditor -->
      <div class="space-y-2">
        <label for="description" class="text-sm font-medium">Description</label>
        <TipTapEditor
          v-model="formData.description"
          placeholder="Describe your form"
          :sticky="false"
          min-height="150px"
        />
        <p v-if="errors.description" class="text-destructive text-xs">
          {{ errors.description[0] }}
        </p>
      </div>

      <div class="space-y-2">
        <label for="slug" class="text-sm font-medium">Slug</label>
        <input
          id="slug"
          v-model="formData.slug"
          type="text"
          placeholder="auto-generated"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.slug }"
        />
        <p v-if="errors.slug" class="text-destructive text-xs">{{ errors.slug[0] }}</p>
        <p class="text-muted-foreground text-xs">Leave empty to auto-generate from title</p>
      </div>

      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="space-y-2">
          <label for="status" class="text-sm font-medium">Status</label>
          <Select v-model="formData.status">
            <SelectTrigger id="status">
              <SelectValue placeholder="Select status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="published">Published</SelectItem>
              <SelectItem value="closed">Closed</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.status" class="text-destructive text-xs">{{ errors.status[0] }}</p>
        </div>

        <!-- Project with Avatar -->
        <div class="space-y-2">
          <label for="project_id" class="text-sm font-medium">Project</label>
          <Select v-model="formData.project_id">
            <SelectTrigger class="w-full">
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
          <p v-if="errors.project_id" class="text-destructive text-xs">
            {{ errors.project_id[0] }}
          </p>
        </div>
      </div>

      <!-- Tags -->
      <div class="space-y-2">
        <label class="text-sm font-medium">Tags</label>
        <TagsInput v-model="formData.tags" class="text-sm">
          <TagsInputItem v-for="tag in formData.tags" :key="tag" :value="tag">
            <TagsInputItemText />
            <TagsInputItemDelete />
          </TagsInputItem>
          <TagsInputInput placeholder="Add tag..." />
        </TagsInput>
        <p class="text-muted-foreground text-xs">Press Enter to add a tag</p>
      </div>

      <div class="flex items-center gap-2">
        <Switch id="is_active" v-model="formData.is_active" />
        <label for="is_active" class="text-sm font-medium">Active</label>
      </div>

      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="space-y-2">
          <label for="opens_at" class="text-sm font-medium">Opens At</label>
          <DateTimePicker
            v-model="formData.opens_at"
            placeholder="Select open date"
            :default-hour="0"
            :default-minute="0"
          />
          <p v-if="errors.opens_at" class="text-destructive text-xs">{{ errors.opens_at[0] }}</p>
        </div>

        <div class="space-y-2">
          <label for="closes_at" class="text-sm font-medium">Closes At</label>
          <DateTimePicker
            v-model="formData.closes_at"
            placeholder="Select close date"
            :default-hour="23"
            :default-minute="59"
          />
          <p v-if="errors.closes_at" class="text-destructive text-xs">
            {{ errors.closes_at[0] }}
          </p>
        </div>
      </div>

      <div class="space-y-2">
        <label for="response_limit" class="text-sm font-medium">Response Limit</label>
        <input
          id="response_limit"
          v-model.number="formData.response_limit"
          type="number"
          min="0"
          placeholder="Unlimited"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.response_limit }"
        />
        <p v-if="errors.response_limit" class="text-destructive text-xs">
          {{ errors.response_limit[0] }}
        </p>
        <p class="text-muted-foreground text-xs">
          Maximum number of responses. Leave empty for unlimited.
        </p>
      </div>

      <!-- Settings Section -->
      <div class="border-border space-y-4 rounded-lg border p-4">
        <h3 class="text-sm font-semibold tracking-tight">Settings</h3>

        <div class="space-y-2">
          <label for="confirmation_message" class="text-sm font-medium">
            Confirmation Message
          </label>
          <textarea
            id="confirmation_message"
            v-model="formData.settings.confirmation_message"
            rows="2"
            placeholder="Thank you for your response!"
            class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          />
        </div>

        <div class="space-y-2">
          <label for="redirect_url" class="text-sm font-medium">Redirect URL</label>
          <input
            id="redirect_url"
            v-model="formData.settings.redirect_url"
            type="url"
            placeholder="https://example.com/thank-you"
            class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          />
          <p class="text-muted-foreground text-xs">
            Redirect to this URL after form submission (optional)
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Switch id="require_email" v-model="formData.settings.require_email" />
          <label for="require_email" class="text-sm font-medium">Require Email</label>
        </div>

        <div class="flex items-center gap-2">
          <Switch id="prevent_duplicate" v-model="formData.settings.prevent_duplicate" />
          <label for="prevent_duplicate" class="text-sm font-medium">Prevent Duplicate</label>
        </div>

        <div v-if="formData.settings.prevent_duplicate" class="space-y-2">
          <label for="prevent_duplicate_by" class="text-sm font-medium">
            Prevent Duplicate By
          </label>
          <Select v-model="formData.settings.prevent_duplicate_by">
            <SelectTrigger id="prevent_duplicate_by">
              <SelectValue placeholder="Select method" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="email">Email</SelectItem>
              <SelectItem value="fingerprint">Browser Fingerprint</SelectItem>
              <SelectItem value="both">Both</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div class="flex gap-2">
        <button
          type="submit"
          :disabled="loading"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-2 rounded-md px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="loading" class="size-4" />
          <span>{{ loading ? loadingText : submitText }}</span>
        </button>
        <nuxt-link
          to="/forms"
          class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          Cancel
        </nuxt-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import DateTimePicker from "@/components/DateTimePicker.vue";
import InputFileImage from "@/components/InputFileImage.vue";
import TipTapEditor from "@/components/TipTapEditor.vue";
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
});

const emit = defineEmits(["saved"]);
const sanctumFetch = useSanctumClient();
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
    confirmation_message: "",
    redirect_url: "",
    require_email: false,
    prevent_duplicate: false,
    prevent_duplicate_by: "email",
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
          confirmation_message: settings.confirmation_message || "",
          redirect_url: settings.redirect_url || "",
          require_email: settings.require_email || false,
          prevent_duplicate: settings.prevent_duplicate || false,
          prevent_duplicate_by: settings.prevent_duplicate_by || "email",
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
    body.opens_at = body.opens_at instanceof Date ? body.opens_at.toISOString() : null;
    body.closes_at = body.closes_at instanceof Date ? body.closes_at.toISOString() : null;
    if (!body.response_limit) body.response_limit = null;
    if (body.project_id) {
      body.project_id = Number(body.project_id);
    } else {
      body.project_id = null;
    }

    // Handle cover image
    const coverValue = imageFiles.value.cover_image?.[0];
    if (coverValue && coverValue.startsWith("tmp-")) {
      body.tmp_cover_image = coverValue;
    } else if (deleteFlags.value.cover_image && !coverValue) {
      body.delete_cover_image = true;
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

defineExpose({
  handleSubmit,
});
</script>
