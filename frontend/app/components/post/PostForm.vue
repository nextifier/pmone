<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Featured Image -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Featured Image</div>
      </div>
      <div class="frame-panel">
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
        </div>
      </div>
    </div>

    <!-- Post Content -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Post Content</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="title">Title</Label>
            <Input id="title" v-model="form.title" type="text" required />
            <InputErrorMessage :errors="errors.title" />
          </div>

          <div class="space-y-2">
            <Label for="excerpt">Excerpt</Label>
            <Textarea id="excerpt" v-model="form.excerpt" maxlength="500" />
            <p class="text-muted-foreground text-xs tracking-tight">
              Brief description of the post (max 500 characters)
            </p>
            <InputErrorMessage :errors="errors.excerpt" />
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
        </div>
      </div>
    </div>

    <!-- Tags -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Tags</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-2">
          <Label for="tags">Tags</Label>
          <TagsInputComponent v-model="form.tags" placeholder="Add tags..." />
          <p class="text-muted-foreground text-xs tracking-tight">Press Enter to add a tag</p>
          <InputErrorMessage :errors="errors.tags" />
        </div>
      </div>
    </div>

    <!-- Authors -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Authors</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-4">
          <div class="space-y-2">
            <Label>Post Authors</Label>
            <p class="text-muted-foreground text-xs tracking-tight">
              Add authors and assign their roles for this post
            </p>
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

              <!-- Role Input -->
              <div class="w-48">
                <Input
                  v-model="author.role"
                  type="text"
                  placeholder="Role (e.g., primary_author)"
                  maxlength="50"
                />
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
      </div>
    </div>

    <!-- Post Settings -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Post Settings</div>
      </div>
      <div class="frame-panel">
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

          <div class="flex items-center gap-2">
            <input
              id="featured"
              v-model="form.featured"
              type="checkbox"
              class="border-input h-4 w-4 rounded"
            />
            <Label for="featured" class="cursor-pointer font-normal"> Mark as featured post </Label>
          </div>
        </div>
      </div>
    </div>

    <!-- SEO Meta -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">SEO & Meta</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="meta_title">Meta Title</Label>
            <Input id="meta_title" v-model="form.meta_title" type="text" maxlength="60" />
            <p class="text-muted-foreground text-xs tracking-tight">
              Max 60 characters (Leave empty to auto-generate from title)
            </p>
            <InputErrorMessage :errors="errors.meta_title" />
          </div>

          <div class="space-y-2">
            <Label for="meta_description">Meta Description</Label>
            <Textarea id="meta_description" v-model="form.meta_description" maxlength="160" />
            <p class="text-muted-foreground text-xs tracking-tight">
              Max 160 characters (Leave empty to auto-generate from excerpt)
            </p>
            <InputErrorMessage :errors="errors.meta_description" />
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-3">
      <button
        type="button"
        @click="emit('cancel')"
        class="border-input hover:bg-accent hover:text-accent-foreground rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition"
      >
        Cancel
      </button>
      <button
        type="submit"
        :disabled="loading || !form.title || !form.content || hasFilesUploading()"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:opacity-50"
      >
        <Spinner v-if="loading" />
        {{ submitButtonText }}
      </button>
    </div>
  </form>
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

const deleteFlags = ref({
  featured_image: false,
});

const imageFiles = ref({
  featured_image: [],
});

const form = reactive({
  title: "",
  excerpt: "",
  content: "",
  status: "draft",
  visibility: "public",
  published_at: null,
  featured: false,
  meta_title: "",
  meta_description: "",
  tags: [],
  authors: [],
});

const loading = ref(false);
const autoSaving = ref(false);
const errors = ref({});
const postId = ref(props.initialData?.id || null);
const postSlug = ref(props.postSlug || null);
const availableUsers = ref([]);
let autoSaveTimeout = null;

onMounted(async () => {
  await loadUsers();
  if (props.initialData) {
    populateForm();
  }
});

onBeforeUnmount(() => {
  clearTimeout(autoSaveTimeout);
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
  form.excerpt = data.excerpt || "";
  form.content = data.content || "";
  form.status = data.status || "draft";
  form.visibility = data.visibility || "public";
  form.featured = data.featured || false;
  form.meta_title = data.meta_title || "";
  form.meta_description = data.meta_description || "";

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
      role: author.role || "co_author",
      order: author.order || 0,
    }));
  }
}

// Author management functions
function addAuthor() {
  form.authors.push({
    user_id: null,
    role: "co_author",
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

// Auto-save disabled
// watch(
//   () => [form.title, form.content, form.excerpt],
//   () => {
//     if (form.title || form.content) {
//       debouncedAutoSave();
//     }
//   }
// );

function debouncedAutoSave() {
  clearTimeout(autoSaveTimeout);
  autoSaveTimeout = setTimeout(() => {
    autoSavePost();
  }, 2000);
}

async function autoSavePost() {
  // Don't auto-save if both title and content are empty
  if (!form.title && !form.content) return;

  // Don't auto-save if only title is filled (content is required)
  if (form.title && !form.content && !postId.value) return;

  if (loading.value) return;

  autoSaving.value = true;

  try {
    const payload = {
      title: form.title || "Untitled Post",
      content: form.content || "",
      content_format: "html",
      excerpt: form.excerpt,
      status: props.mode === "edit" ? form.status : "draft",
      visibility: form.visibility,
      featured: form.featured,
      meta_title: form.meta_title || null,
      meta_description: form.meta_description || null,
      tags: form.tags,
    };

    if (props.mode === "edit" && props.postSlug) {
      await client(`/api/posts/${props.postSlug}`, {
        method: "PUT",
        body: payload,
      });
    } else if (postSlug.value) {
      await client(`/api/posts/${postSlug.value}`, {
        method: "PUT",
        body: payload,
      });
    } else {
      const response = await client("/api/posts", {
        method: "POST",
        body: payload,
      });
      postId.value = response.data.id;
      postSlug.value = response.data.slug;
      toast.success("Draft created automatically");
    }
  } catch (error) {
    console.error("Auto-save failed:", error);
  } finally {
    autoSaving.value = false;
  }
}

function hasFilesUploading() {
  return [featuredImageInputRef].some((ref) =>
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
      excerpt: form.excerpt,
      content: form.content,
      content_format: "html",
      status: form.status,
      visibility: form.visibility,
      featured: form.featured,
      meta_title: form.meta_title || null,
      meta_description: form.meta_description || null,
      published_at:
        form.status === "scheduled" && form.published_at
          ? new Date(form.published_at).toISOString()
          : null,
      tags: form.tags,
    };

    // Add authors if any
    if (form.authors.length > 0) {
      payload.authors = form.authors
        .filter((author) => author.user_id) // Only include authors with selected user
        .map((author, index) => ({
          user_id: author.user_id,
          role: author.role || "co_author",
          order: index,
        }));
    }

    const featuredValue = imageFiles.value.featured_image?.[0];
    if (featuredValue && featuredValue.startsWith("tmp-")) {
      payload.tmp_featured_image = featuredValue;
    } else if (deleteFlags.value.featured_image && !featuredValue) {
      payload.delete_featured_image = true;
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
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
