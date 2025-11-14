<template>
  <div class="container max-w-5xl mx-auto py-8 px-4">
    <div class="mb-8">
      <h1 class="text-3xl font-bold">Create New Post</h1>
      <p class="text-muted-foreground mt-2">
        Write and publish a new blog post
      </p>
    </div>

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
              :initial-image="null"
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
              <Label for="title">Title <span class="text-destructive">*</span></Label>
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
                :post-id="savedPostId"
                placeholder="Start writing your post content..."
              />
              <InputErrorMessage :errors="errors.content" />
            </div>
          </div>
        </div>
      </div>

      <!-- Authors & Tags -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Authors & Tags</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-6">
            <div class="space-y-2">
              <Label>Authors</Label>
              <UserMultiSelect
                :users="availableUsers"
                v-model="selectedAuthors"
                v-model:query="authorQuery"
                placeholder="Search authors..."
                :hide-clear-all-button="true"
              />
              <p class="text-muted-foreground text-xs tracking-tight">
                Select authors for this post
              </p>
              <InputErrorMessage :errors="errors.author_ids" />
            </div>

            <div class="space-y-2">
              <Label for="tags">Tags</Label>
              <TagsInputComponent v-model="form.tags" placeholder="Add tags..." />
              <p class="text-muted-foreground text-xs tracking-tight">
                Press Enter to add a tag
              </p>
              <InputErrorMessage :errors="errors.tags" />
            </div>
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
              <Input
                id="published_at"
                v-model="form.published_at"
                type="datetime-local"
              />
              <InputErrorMessage :errors="errors.published_at" />
            </div>

            <div class="flex items-center gap-2">
              <input
                id="featured"
                v-model="form.featured"
                type="checkbox"
                class="h-4 w-4 rounded border-input"
              />
              <Label for="featured" class="font-normal cursor-pointer">
                Mark as featured post
              </Label>
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
          @click="navigateTo('/posts')"
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
          {{ loading ? "Creating..." : "Create Post" }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");

const FILE_STATUS = {
  PROCESSING: 3,
};

const { $api } = useNuxtApp();
const featuredImageInputRef = ref(null);
const authorQuery = ref("");
const selectedAuthors = ref([]);

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
  author_ids: [],
});

const loading = ref(false);
const errors = ref({});
const savedPostId = ref(null);
const availableUsers = ref([]);

onMounted(async () => {
  await loadUsers();
});

async function loadUsers() {
  try {
    const response = await $api("/users?per_page=100");
    availableUsers.value = response.data;
  } catch (error) {
    console.error("Failed to load users:", error);
  }
}

// Watch selectedAuthors and sync with form.author_ids
watch(
  selectedAuthors,
  (newValue) => {
    form.author_ids = newValue.map((user) => user.id);
  },
  { deep: true }
);

// Check if any files are currently uploading
function hasFilesUploading() {
  return [featuredImageInputRef].some((ref) =>
    ref.value?.pond?.getFiles().some((file) => file.status === FILE_STATUS.PROCESSING)
  );
}

async function handleSubmit() {
  // Check if any files are still uploading
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
      author_ids: form.author_ids,
      tags: form.tags,
    };

    // Handle featured image
    const featuredValue = imageFiles.value.featured_image?.[0];
    if (featuredValue && featuredValue.startsWith("tmp-")) {
      payload.tmp_featured_image = featuredValue;
    } else if (deleteFlags.value.featured_image && !featuredValue) {
      payload.delete_featured_image = true;
    }

    const response = await $api("/posts", {
      method: "POST",
      body: payload,
    });

    savedPostId.value = response.data.id;

    toast.success("Post created successfully!");
    await navigateTo("/posts");
  } catch (error) {
    console.error("Failed to create post:", error);

    if (error?.data?.errors) {
      errors.value = error.data.errors;
    }

    toast.error(error?.data?.message || "Failed to create post. Please try again.");
  } finally {
    loading.value = false;
  }
}

defineShortcuts({
  meta_s: {
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
