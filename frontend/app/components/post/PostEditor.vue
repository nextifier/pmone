<template>
  <div>
    <form @submit.prevent="handleSubmit" class="grid gap-y-8">
      <!-- Autosave Status & Preview -->
      <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-4">
        <div class="flex items-center gap-2">
          <Switch id="autosave-toggle" v-model="autosaveEnabled" :disabled="showRestoreDialog" />
          <Label for="autosave-toggle" class="text-muted-foreground cursor-pointer text-sm">
            <PostAutosaveStatus
              v-if="autosaveEnabled"
              :is-saving="autosave.isSaving.value"
              :is-saved="autosave.isSaved.value"
              :has-error="autosave.hasError.value"
              :last-saved-at="autosave.lastSavedAt.value"
              :error="autosave.autosaveStatus.value.error"
            />
            <span v-else class="text-muted-foreground text-sm">Autosave disabled</span>
          </Label>
        </div>

        <button
          type="button"
          @click="showPreview"
          class="bg-muted hover:bg-border flex items-center gap-x-1.5 rounded-lg px-3 py-2 text-sm tracking-tight transition active:scale-98"
        >
          <Icon name="hugeicons:view" class="size-4.5 shrink-0" />
          <span>{{ mode === "edit" ? "Preview Changes" : "Preview Post" }}</span>
        </button>
      </div>

      <div class="grid grid-cols-1 gap-y-6">
        <div class="space-y-2">
          <Label for="title">Title</Label>
          <Input id="title" v-model="form.title" type="text" required />
          <InputErrorMessage :errors="errors.title" />
        </div>

        <div class="space-y-2">
          <Label for="slug">Slug (Optional)</Label>
          <Input
            id="slug"
            v-model="form.slug"
            type="text"
            placeholder="Leave empty to auto-generate from title"
          />
          <p class="text-muted-foreground text-xs tracking-tight">
            URL-friendly version of the title. Leave empty to auto-generate.
          </p>
          <div
            v-if="slugChecking"
            class="text-muted-foreground flex items-center gap-2 text-xs tracking-tight"
          >
            <Spinner class="size-3" />
            Checking availability...
          </div>
          <div
            v-else-if="slugAvailable === false"
            class="text-destructive flex items-center gap-2 text-xs tracking-tight"
          >
            <Icon name="lucide:x-circle" class="size-3" />
            This slug is already taken
          </div>
          <div
            v-else-if="slugAvailable === true"
            class="flex items-center gap-2 text-xs tracking-tight text-green-600 dark:text-green-400"
          >
            <Icon name="lucide:check-circle" class="size-3" />
            Slug is available
          </div>
          <InputErrorMessage :errors="errors.slug" />
        </div>

        <div class="space-y-2">
          <Label for="excerpt">Excerpt</Label>
          <Textarea id="excerpt" v-model="form.excerpt" maxlength="500" />
          <p class="text-muted-foreground text-xs tracking-tight">
            Brief description of the post (max 500 characters)
          </p>
          <InputErrorMessage :errors="errors.excerpt" />
        </div>

        <div class="space-y-4">
          <Label>Featured Image</Label>
          <InputFileImage
            ref="featuredImageInputRef"
            v-model="imageFiles.featured_image"
            :initial-image="initialData?.featured_image"
            v-model:delete-flag="deleteFlags.featured_image"
            container-class="relative isolate aspect-video w-full"
          />
          <InputErrorMessage :errors="errors.tmp_featured_image" />

          <div class="space-y-2">
            <Label for="featured_image_caption">Image Caption (Optional)</Label>
            <Input
              id="featured_image_caption"
              v-model="form.featured_image_caption"
              type="text"
              maxlength="500"
              placeholder="Add a caption for the featured image..."
            />
            <InputErrorMessage :errors="errors.featured_image_caption" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Content <span class="text-destructive">*</span></Label>
          <PostTipTapEditor
            v-model="form.content"
            :post-id="postId"
            placeholder="Start writing your post content..."
          />
          <InputErrorMessage :errors="errors.content" />
        </div>

        <div class="space-y-2">
          <Label for="tags">Tags</Label>
          <TagsInputComponent v-model="form.tags" placeholder="Add tags..." />
          <p class="text-muted-foreground text-xs tracking-tight">Press Enter to add a tag</p>
          <InputErrorMessage :errors="errors.tags" />
        </div>
      </div>

      <!-- Post Settings -->
      <div class="grid grid-cols-1 gap-y-6">
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-2">
            <Label for="status">Status</Label>
            <Select v-model="form.status">
              <SelectTrigger class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="published">Published</SelectItem>
                <SelectItem value="scheduled">Scheduled</SelectItem>
                <SelectItem v-if="mode === 'edit'" value="archived">Archived</SelectItem>
              </SelectContent>
            </Select>
            <InputErrorMessage :errors="errors.status" />
          </div>

          <div class="space-y-2">
            <Label for="visibility">Visibility</Label>
            <Select v-model="form.visibility">
              <SelectTrigger class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="public">Public</SelectItem>
                <SelectItem value="private">Private</SelectItem>
                <SelectItem value="members_only">Members Only</SelectItem>
              </SelectContent>
            </Select>
            <InputErrorMessage :errors="errors.visibility" />
          </div>
        </div>

        <div v-if="form.status === 'scheduled'" class="space-y-2">
          <Label for="published_at">Publish Date & Time</Label>
          <Input id="published_at" v-model="form.published_at" type="datetime-local" />
          <InputErrorMessage :errors="errors.published_at" />
        </div>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label>Post Authors</Label>
            <p class="text-muted-foreground text-xs tracking-tight">Add authors for this post</p>
          </div>

          <!-- Authors List -->
          <div v-if="form.authors.length > 0" class="space-y-3">
            <div
              v-for="(author, index) in form.authors"
              :key="index"
              class="border-border flex items-center gap-3 rounded-lg border p-3"
            >
              <!-- User Select -->
              <div class="flex-1">
                <Select v-model="author.user_id">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select author..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="user in getAvailableUsersForRow(index)"
                      :key="user.id"
                      :value="user.id"
                    >
                      {{ user.name }} ({{ user.email }})
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <!-- Reorder Buttons -->
              <div class="flex gap-1">
                <button
                  type="button"
                  @click="moveAuthorUp(index)"
                  :disabled="index === 0"
                  class="hover:bg-accent flex size-8 items-center justify-center rounded-lg transition disabled:opacity-30"
                  title="Move up"
                >
                  <Icon name="lucide:chevron-up" class="size-4" />
                </button>
                <button
                  type="button"
                  @click="moveAuthorDown(index)"
                  :disabled="index === form.authors.length - 1"
                  class="hover:bg-accent flex size-8 items-center justify-center rounded-lg transition disabled:opacity-30"
                  title="Move down"
                >
                  <Icon name="lucide:chevron-down" class="size-4" />
                </button>
              </div>

              <!-- Remove Button -->
              <button
                type="button"
                @click="removeAuthor(index)"
                class="hover:bg-destructive/10 hover:text-destructive flex size-8 items-center justify-center rounded-lg transition"
                title="Remove author"
              >
                <Icon name="lucide:x" class="size-4" />
              </button>
            </div>
          </div>

          <!-- Add Author Button -->
          <button
            type="button"
            @click="addAuthor"
            class="border-input hover:bg-muted flex w-full items-center justify-center gap-2 rounded-lg border border-dashed py-3 text-sm font-medium transition"
          >
            <Icon name="lucide:plus" class="size-4" />
            Add Author
          </button>

          <InputErrorMessage :errors="errors.authors" />
        </div>

        <div class="flex items-center gap-2">
          <Switch id="featured" v-model="form.featured" />
          <Label for="featured" class="cursor-pointer font-normal">Mark as featured post </Label>
        </div>
      </div>

      <!-- SEO Meta -->
      <div class="grid grid-cols-1 gap-y-6">
        <div class="space-y-2">
          <Label for="meta_title">Meta Title</Label>
          <Input id="meta_title" v-model="form.meta_title" type="text" />
          <p class="text-muted-foreground text-xs tracking-tight">
            Leave empty to auto-generate from title
          </p>
          <InputErrorMessage :errors="errors.meta_title" />
        </div>

        <div class="space-y-2">
          <Label for="meta_description">Meta Description</Label>
          <Textarea id="meta_description" v-model="form.meta_description" />
          <p class="text-muted-foreground text-xs tracking-tight">
            Leave empty to auto-generate from excerpt
          </p>
          <InputErrorMessage :errors="errors.meta_description" />
        </div>

        <div class="space-y-4">
          <Label>OG Image</Label>
          <InputFileImage
            ref="ogImageInputRef"
            v-model="imageFiles.og_image"
            :initial-image="initialData?.og_image"
            v-model:delete-flag="deleteFlags.og_image"
            container-class="relative isolate aspect-video w-full"
          />
          <p class="text-muted-foreground text-xs tracking-tight">
            Image for social media sharing (Open Graph). Recommended size: 1200x630px
          </p>
          <InputErrorMessage :errors="errors.tmp_og_image" />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2">
        <button
          v-if="mode === 'edit' && autosave.localBackup.value"
          type="button"
          @click="discardAutosave"
          class="border-input hover:bg-accent hover:text-accent-foreground flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium tracking-tighter transition"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          Discard Draft
        </button>
        <button
          type="button"
          @click="emit('cancel')"
          class="border-input hover:bg-accent hover:text-accent-foreground rounded-lg border px-4 py-2 text-sm font-medium tracking-tighter transition"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="loading || !form.title || !form.content || hasFilesUploading()"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tighter transition disabled:opacity-50"
        >
          <Spinner v-if="loading" />
          {{ submitButtonText }}
        </button>
      </div>
    </form>

    <PostPreview
      v-model:open="showPreviewModal"
      :preview-data="previewFormData"
      :published-post="initialData"
      :mode="mode"
    />

    <DialogResponsive v-model:open="showRestoreDialog" dialog-max-width="450px">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="flex items-start gap-3">
          <div
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900"
          >
            <Icon name="lucide:archive-restore" class="size-5 text-blue-600 dark:text-blue-400" />
          </div>
          <div class="flex-1">
            <h3 class="text-primary text-lg font-semibold">Restore Draft?</h3>
            <p class="text-muted-foreground mt-1.5 text-sm leading-relaxed">
              You have unsaved changes from a previous session. Would you like to restore them or
              start fresh?
            </p>
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button
            type="button"
            @click="handleDiscardRestore"
            class="border-input hover:bg-accent hover:text-accent-foreground rounded-lg border px-4 py-2 text-sm font-medium transition"
          >
            Discard
          </button>
          <button
            type="button"
            @click="handleRestoreChanges"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
          >
            Restore Draft
          </button>
        </div>
      </div>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  initialData: {
    type: Object,
    default: null,
  },
  postSlug: {
    type: String,
    default: null,
  },
});

const emit = defineEmits(["cancel", "success"]);

const FILE_STATUS = {
  PROCESSING: 3,
};

const client = useSanctumClient();
const featuredImageInputRef = ref(null);
const ogImageInputRef = ref(null);

const deleteFlags = ref({
  featured_image: false,
  og_image: false,
});

const imageFiles = ref({
  featured_image: [],
  og_image: [],
});

const form = reactive({
  title: "",
  slug: "",
  excerpt: "",
  content: "",
  status: "draft",
  visibility: "public",
  published_at: null,
  featured: false,
  meta_title: "",
  meta_description: "",
  featured_image_caption: "",
  tags: [],
  authors: [],
});

const loading = ref(false);
const errors = ref({});
const postId = ref(props.initialData?.id || null);
const postSlug = ref(props.postSlug || null);
const availableUsers = ref([]);
const slugChecking = ref(false);
const slugAvailable = ref(null);
const showPreviewModal = ref(false);
const showRestoreDialog = ref(false);
const pendingRestoreData = ref(null);
const currentPreviewImageUrl = ref(null);
let slugCheckTimeout = null;

// Autosave preference storage key
const AUTOSAVE_PREF_KEY = "post-autosave-preference";

// Load autosave preference from localStorage (default to true if not set)
function loadAutosavePreference() {
  if (import.meta.client) {
    const stored = localStorage.getItem(AUTOSAVE_PREF_KEY);
    return stored === null ? true : stored === "true";
  }
  return true;
}

// Save autosave preference to localStorage
function saveAutosavePreference(enabled) {
  if (import.meta.client) {
    localStorage.setItem(AUTOSAVE_PREF_KEY, String(enabled));
  }
}

// Autosave composable - initially disabled to prevent autosave during initialization
const autosaveEnabled = ref(false);
const userAutosavePreference = ref(loadAutosavePreference());

const autosave = useAutosave(toRef(form), {
  postId: postId,
  enabled: autosaveEnabled,
  debounceTime: 2000,
  localStorageKey: computed(() =>
    postId.value ? `post-autosave-${postId.value}` : "post-autosave-new"
  ),
});

// Watch for user preference changes and persist to localStorage
watch(autosaveEnabled, (newValue) => {
  // Only save preference if we're past initialization (restore dialog closed)
  if (!showRestoreDialog.value) {
    saveAutosavePreference(newValue);
    userAutosavePreference.value = newValue;
  }
});

onMounted(async () => {
  await loadUsers();
  if (props.initialData) {
    populateForm();
  }

  // Check for existing autosave
  await checkAndRestoreAutosave();
});

onBeforeUnmount(() => {
  clearTimeout(slugCheckTimeout);
  // Cleanup preview image URL if exists
  if (currentPreviewImageUrl.value) {
    URL.revokeObjectURL(currentPreviewImageUrl.value);
  }
});

async function loadUsers() {
  try {
    // Use dedicated endpoint that handles permissions internally
    const response = await client("/api/posts/eligible-authors");
    availableUsers.value = response.data || [];
  } catch (error) {
    console.error("Failed to load eligible authors:", error);
    availableUsers.value = [];
  }
}

function populateForm() {
  if (!props.initialData) return;

  const data = props.initialData;
  form.title = data.title || "";
  form.slug = data.slug || "";
  form.excerpt = data.excerpt || "";
  form.content = data.content || "";
  form.status = data.status || "draft";
  form.visibility = data.visibility || "public";
  form.featured = data.featured || false;
  form.meta_title = data.meta_title || "";
  form.meta_description = data.meta_description || "";
  form.featured_image_caption = data.featured_image?.caption || "";

  if (data.published_at) {
    const date = new Date(data.published_at);
    form.published_at = date.toISOString().slice(0, 16);
  }

  if (data.tags && Array.isArray(data.tags)) {
    form.tags = data.tags.map((tag) => tag.name || tag);
  }

  if (data.authors && Array.isArray(data.authors)) {
    form.authors = data.authors.map((author) => ({
      user_id: author.id,
      order: author.order || 0,
    }));
  }
}

// Author management functions
function addAuthor() {
  form.authors.push({
    user_id: null,
    order: form.authors.length,
  });
}

function removeAuthor(index) {
  form.authors.splice(index, 1);
  // Update order after removal
  form.authors.forEach((author, idx) => {
    author.order = idx;
  });
}

function moveAuthorUp(index) {
  if (index === 0) return;
  const temp = form.authors[index];
  form.authors[index] = form.authors[index - 1];
  form.authors[index - 1] = temp;
  // Update order
  form.authors.forEach((author, idx) => {
    author.order = idx;
  });
}

function moveAuthorDown(index) {
  if (index === form.authors.length - 1) return;
  const temp = form.authors[index];
  form.authors[index] = form.authors[index + 1];
  form.authors[index + 1] = temp;
  // Update order
  form.authors.forEach((author, idx) => {
    author.order = idx;
  });
}

// Get available users for a specific author row (excludes already selected users)
function getAvailableUsersForRow(currentIndex) {
  const selectedUserIds = form.authors
    .map((author, idx) => (idx !== currentIndex ? author.user_id : null))
    .filter((id) => id !== null);

  return availableUsers.value.filter(
    (user) => !selectedUserIds.includes(user.id) || user.id === form.authors[currentIndex]?.user_id
  );
}

// Watch for slug changes to check availability
watch(
  () => form.slug,
  (newSlug) => {
    if (!newSlug || newSlug.trim() === "") {
      slugAvailable.value = null;
      slugChecking.value = false;
      clearTimeout(slugCheckTimeout);
      return;
    }

    // If editing and slug hasn't changed from initial, skip checking
    if (props.mode === "edit" && props.initialData?.slug === newSlug) {
      slugAvailable.value = null;
      slugChecking.value = false;
      clearTimeout(slugCheckTimeout);
      return;
    }

    // Debounce slug checking
    clearTimeout(slugCheckTimeout);
    slugChecking.value = true;
    slugAvailable.value = null;

    slugCheckTimeout = setTimeout(() => {
      checkSlugAvailability(newSlug);
    }, 500);
  }
);

// Autosave is now handled by useAutosave composable

async function checkSlugAvailability(slug) {
  try {
    slugChecking.value = true;

    // Use dedicated slug check endpoint with proper query params
    const params = new URLSearchParams({ slug });
    if (props.initialData?.id) {
      params.append("exclude_id", props.initialData.id);
    }

    const response = await client(`/api/posts/check-slug?${params.toString()}`);
    slugAvailable.value = response.available;
  } catch (error) {
    console.error("Failed to check slug availability:", error);
    // Fallback: assume available if check fails
    slugAvailable.value = null;
  } finally {
    slugChecking.value = false;
  }
}

// Check if autosave data is different from initial/published data
function hasAutosaveChanges(savedData) {
  if (!savedData) return false;

  const initialData = props.initialData;

  // For new posts, any saved data is a change
  if (!initialData) {
    return !!(savedData.title || savedData.content || savedData.excerpt);
  }

  // For existing posts, compare key fields
  const fieldsToCompare = ["title", "excerpt", "content", "status", "visibility", "featured"];
  for (const field of fieldsToCompare) {
    const savedValue = savedData[field];
    const initialValue = initialData[field];

    // Skip if saved value is undefined
    if (savedValue === undefined) continue;

    // Compare values (handle null/undefined/empty string cases)
    if ((savedValue || "") !== (initialValue || "")) {
      return true;
    }
  }

  // Check meta fields
  if (
    (savedData.meta_title || "") !== (initialData.meta_title || "") ||
    (savedData.meta_description || "") !== (initialData.meta_description || "")
  ) {
    return true;
  }

  // Check tags
  const savedTags = savedData.tags || [];
  const initialTags = (initialData.tags || []).map((t) => t.name || t);
  if (JSON.stringify(savedTags.sort()) !== JSON.stringify(initialTags.sort())) {
    return true;
  }

  return false;
}

// New autosave functions
async function checkAndRestoreAutosave() {
  try {
    const savedData = await autosave.retrieveAutosave();
    // Only show dialog if there are actual changes from initial data
    if (savedData && Object.keys(savedData).length > 0 && hasAutosaveChanges(savedData)) {
      pendingRestoreData.value = savedData;
      showRestoreDialog.value = true;
    } else {
      // No meaningful autosave found, discard it silently and enable autosave based on user preference
      if (savedData) {
        await autosave.discardAutosave();
      }
      autosaveEnabled.value = userAutosavePreference.value;
    }
  } catch (error) {
    console.error("Failed to check autosave:", error);
    // Enable autosave based on user preference even if there was an error
    autosaveEnabled.value = userAutosavePreference.value;
  }
}

async function handleRestoreChanges() {
  const savedData = pendingRestoreData.value;
  if (savedData) {
    // Restore form data from autosave
    if (savedData.title !== undefined) form.title = savedData.title;
    if (savedData.slug !== undefined) form.slug = savedData.slug;
    if (savedData.excerpt !== undefined) form.excerpt = savedData.excerpt;
    if (savedData.content !== undefined) form.content = savedData.content;
    if (savedData.status) form.status = savedData.status;
    if (savedData.visibility) form.visibility = savedData.visibility;
    if (savedData.meta_title !== undefined) form.meta_title = savedData.meta_title;
    if (savedData.meta_description !== undefined)
      form.meta_description = savedData.meta_description;
    if (savedData.featured_image_caption !== undefined)
      form.featured_image_caption = savedData.featured_image_caption;
    if (savedData.featured !== undefined) form.featured = savedData.featured;
    if (savedData.tags) form.tags = savedData.tags;
    if (savedData.authors) form.authors = savedData.authors;
    if (savedData.published_at) {
      const date = new Date(savedData.published_at);
      form.published_at = date.toISOString().slice(0, 16);
    }

    toast.success("Draft restored successfully");
  }
  showRestoreDialog.value = false;
  pendingRestoreData.value = null;

  // Enable autosave after restore
  autosaveEnabled.value = true;
}

async function handleDiscardRestore() {
  // User declined, clear autosave
  await autosave.discardAutosave();
  showRestoreDialog.value = false;
  pendingRestoreData.value = null;

  // Enable autosave after a short delay based on user preference
  // This ensures localStorage is fully cleared before autosave kicks in
  setTimeout(() => {
    autosaveEnabled.value = userAutosavePreference.value;
  }, 100);
}

async function discardAutosave() {
  const confirmed = confirm(
    "Are you sure you want to discard your draft? This action cannot be undone."
  );
  if (confirmed) {
    await autosave.discardAutosave();
    // Restore from initial data if in edit mode
    if (props.initialData) {
      populateForm();
    }
  }
}

// Helper to get featured image for preview
function getPreviewFeaturedImage() {
  if (imageFiles.value.featured_image?.[0]) {
    const imgValue = imageFiles.value.featured_image[0];
    if (typeof imgValue === "string") {
      return imgValue;
    } else if (imgValue instanceof File) {
      // Reuse existing URL if same file, otherwise create new
      if (!currentPreviewImageUrl.value) {
        currentPreviewImageUrl.value = URL.createObjectURL(imgValue);
      }
      return currentPreviewImageUrl.value;
    }
  } else if (props.initialData?.featured_image && !deleteFlags.value.featured_image) {
    return props.initialData.featured_image;
  }
  return null;
}

// Preview data computed from current form state
const previewFormData = computed(() => {
  return {
    title: form.title || "Untitled Post",
    excerpt: form.excerpt || "",
    content: form.content || "",
    status: form.status || "draft",
    visibility: form.visibility || "public",
    featured: form.featured || false,
    meta_title: form.meta_title || form.title || "",
    meta_description: form.meta_description || form.excerpt || "",
    featured_image: getPreviewFeaturedImage(),
    tags: form.tags || [],
    authors: form.authors || [],
    published_at: form.published_at || null,
  };
});

// Clean up preview image URL when image changes or preview closes
watch(
  () => imageFiles.value.featured_image?.[0],
  (newValue, oldValue) => {
    // If file changed, revoke old URL and reset
    if (oldValue instanceof File && currentPreviewImageUrl.value) {
      URL.revokeObjectURL(currentPreviewImageUrl.value);
      currentPreviewImageUrl.value = null;
    }
  }
);

// Cleanup object URLs when preview modal closes
watch(showPreviewModal, (isOpen) => {
  if (!isOpen && currentPreviewImageUrl.value) {
    URL.revokeObjectURL(currentPreviewImageUrl.value);
    currentPreviewImageUrl.value = null;
  }
});

function showPreview() {
  showPreviewModal.value = true;
}

function hasFilesUploading() {
  return [featuredImageInputRef, ogImageInputRef].some((ref) =>
    ref.value?.pond?.getFiles().some((file) => file.status === FILE_STATUS.PROCESSING)
  );
}

async function handleSubmit() {
  if (hasFilesUploading()) {
    toast.error("Please wait until all files are uploaded");
    return;
  }

  loading.value = true;
  errors.value = {};

  try {
    const payload = {
      title: form.title,
      slug: form.slug || null,
      excerpt: form.excerpt,
      content: form.content,
      content_format: "html",
      status: form.status,
      visibility: form.visibility,
      featured: form.featured,
      meta_title: form.meta_title || null,
      meta_description: form.meta_description || null,
      published_at: form.published_at ? new Date(form.published_at).toISOString() : null,
      tags: form.tags,
    };

    // Add authors if any
    if (form.authors.length > 0) {
      payload.authors = form.authors
        .filter((author) => author.user_id) // Only include authors with selected user
        .map((author, index) => ({
          user_id: author.user_id,
          order: index,
        }));
    }

    const featuredValue = imageFiles.value.featured_image?.[0];
    if (typeof featuredValue === "string" && featuredValue.startsWith("tmp-")) {
      payload.tmp_featured_image = featuredValue;
    } else if (deleteFlags.value.featured_image && !featuredValue) {
      payload.delete_featured_image = true;
    }

    // Add featured image caption
    if (form.featured_image_caption) {
      payload.featured_image_caption = form.featured_image_caption;
    }

    const ogImageValue = imageFiles.value.og_image?.[0];
    if (typeof ogImageValue === "string" && ogImageValue.startsWith("tmp-")) {
      payload.tmp_og_image = ogImageValue;
    } else if (deleteFlags.value.og_image && !ogImageValue) {
      payload.delete_og_image = true;
    }

    let response;
    if (props.mode === "edit" && props.postSlug) {
      response = await client(`/api/posts/${props.postSlug}`, {
        method: "PUT",
        body: payload,
      });
      toast.success("Post updated successfully!");
    } else {
      response = await client("/api/posts", {
        method: "POST",
        body: payload,
      });
      toast.success("Post created successfully!");
    }

    // Clear autosave after successful submission
    await autosave.discardAutosave();

    emit("success", response.data);
  } catch (error) {
    console.error("Failed to save post:", error);

    if (error?.data?.errors) {
      errors.value = error.data.errors;
    }

    toast.error(error?.data?.message || "Failed to save post. Please try again.");
  } finally {
    loading.value = false;
  }
}

const submitButtonText = computed(() => {
  if (loading.value) {
    return props.mode === "edit" ? "Updating..." : "Creating...";
  }
  return props.mode === "edit" ? "Update Post" : "Create Post";
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
