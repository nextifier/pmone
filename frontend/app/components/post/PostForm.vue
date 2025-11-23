<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Autosave Status & Preview -->
    <div class="flex items-center justify-between gap-4 rounded-lg border bg-muted/50 p-3">
      <PostAutosaveStatus
        :is-saving="autosave.isSaving.value"
        :is-saved="autosave.isSaved.value"
        :has-error="autosave.hasError.value"
        :last-saved-at="autosave.lastSavedAt.value"
        :error="autosave.autosaveStatus.value.error"
      />
      <button
        v-if="mode === 'edit' && postSlug"
        type="button"
        @click="showPreview"
        class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-background"
      >
        <IconEye class="size-4" />
        Preview Changes
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
          class="text-green-600 dark:text-green-400 flex items-center gap-2 text-xs tracking-tight"
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
                  <SelectItem v-for="user in availableUsers" :key="user.id" :value="user.id">
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
          class="border-input hover:bg-accent flex w-full items-center justify-center gap-2 rounded-lg border border-dashed py-3 text-sm font-medium transition"
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
        <IconTrash class="size-4" />
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

  <!-- Preview Changes Modal -->
  <PostPreviewChangesModal
    v-model:open="showPreviewModal"
    :post-slug="postSlug"
    :on-preview-load="loadPreviewData"
  />
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
let slugCheckTimeout = null;

// Autosave composable
const autosaveEnabled = ref(true);
const autosave = useAutosave(toRef(form), {
  postId: postId,
  enabled: autosaveEnabled,
  debounceTime: 2000,
  localStorageKey: computed(() =>
    postId.value ? `post-autosave-${postId.value}` : 'post-autosave-new'
  ),
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
});

async function loadUsers() {
  try {
    const response = await client("/api/users");
    availableUsers.value = response.data || [];
  } catch (error) {
    console.error("Failed to load users:", error);
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

    // Use client_only mode to get all posts for checking
    const response = await client(`/api/posts?client_only=true`);

    // Check if any post has this exact slug
    const posts = response.data || [];
    const existingPost = posts.find(
      (p) => p.slug === slug && (!props.initialData || p.id !== props.initialData.id)
    );

    slugAvailable.value = !existingPost;
  } catch (error) {
    console.error("Failed to check slug availability:", error);
    slugAvailable.value = null;
  } finally {
    slugChecking.value = false;
  }
}

// New autosave functions
async function checkAndRestoreAutosave() {
  try {
    const savedData = await autosave.retrieveAutosave();
    if (savedData && Object.keys(savedData).length > 0) {
      const shouldRestore = confirm(
        'You have unsaved changes from a previous session. Do you want to restore them?'
      );
      if (shouldRestore) {
        // Restore form data from autosave
        if (savedData.title) form.title = savedData.title;
        if (savedData.excerpt) form.excerpt = savedData.excerpt;
        if (savedData.content) form.content = savedData.content;
        if (savedData.status) form.status = savedData.status;
        if (savedData.visibility) form.visibility = savedData.visibility;
        if (savedData.meta_title) form.meta_title = savedData.meta_title;
        if (savedData.meta_description) form.meta_description = savedData.meta_description;
        if (savedData.featured !== undefined) form.featured = savedData.featured;
        if (savedData.tags) form.tags = savedData.tags;
        if (savedData.authors) form.authors = savedData.authors;
        if (savedData.published_at) {
          const date = new Date(savedData.published_at);
          form.published_at = date.toISOString().slice(0, 16);
        }

        toast.success('Draft restored successfully');
      } else {
        // User declined, clear autosave
        await autosave.discardAutosave();
      }
    }
  } catch (error) {
    console.error('Failed to check autosave:', error);
  }
}

async function discardAutosave() {
  const confirmed = confirm(
    'Are you sure you want to discard your draft? This action cannot be undone.'
  );
  if (confirmed) {
    await autosave.discardAutosave();
    // Restore from initial data if in edit mode
    if (props.initialData) {
      populateForm();
    }
  }
}

function showPreview() {
  showPreviewModal.value = true;
}

async function loadPreviewData(slug) {
  return await autosave.previewChanges(slug);
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
    if (featuredValue && featuredValue.startsWith("tmp-")) {
      payload.tmp_featured_image = featuredValue;
    } else if (deleteFlags.value.featured_image && !featuredValue) {
      payload.delete_featured_image = true;
    }

    // Add featured image caption
    if (form.featured_image_caption) {
      payload.featured_image_caption = form.featured_image_caption;
    }

    const ogImageValue = imageFiles.value.og_image?.[0];
    if (ogImageValue && ogImageValue.startsWith("tmp-")) {
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
