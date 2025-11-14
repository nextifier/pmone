<template>
  <div class="container max-w-5xl mx-auto py-8 px-4">
    <div class="mb-8">
      <h1 class="text-3xl font-bold">Create New Post</h1>
      <p class="text-gray-600 dark:text-gray-400 mt-2">
        Write and publish a new blog post
      </p>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Title -->
      <div>
        <label for="title" class="block text-sm font-medium mb-2">
          Title <span class="text-red-500">*</span>
        </label>
        <input
          id="title"
          v-model="form.title"
          type="text"
          required
          placeholder="Enter post title..."
          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
        />
      </div>

      <!-- Slug -->
      <div>
        <label for="slug" class="block text-sm font-medium mb-2">
          Slug
          <span class="text-sm text-gray-500">(Auto-generated from title)</span>
        </label>
        <input
          id="slug"
          v-model="form.slug"
          type="text"
          placeholder="post-slug"
          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
        />
      </div>

      <!-- Excerpt -->
      <div>
        <label for="excerpt" class="block text-sm font-medium mb-2">
          Excerpt
        </label>
        <textarea
          id="excerpt"
          v-model="form.excerpt"
          rows="3"
          placeholder="Brief description of the post..."
          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
        ></textarea>
      </div>

      <!-- Featured Image -->
      <div>
        <label class="block text-sm font-medium mb-2">Featured Image</label>
        <InputFileImage
          ref="featuredImageRef"
          v-model="form.featured_image"
          :initial-image="null"
          :delete-flag="false"
          container-class="relative isolate aspect-video w-full max-w-2xl"
        />
      </div>

      <!-- Content Editor -->
      <div>
        <label class="block text-sm font-medium mb-2">
          Content <span class="text-red-500">*</span>
        </label>
        <PostTipTapEditor
          v-model="form.content"
          :post-id="savedPostId"
          placeholder="Start writing your post content..."
        />
      </div>

      <!-- Status & Visibility -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label for="status" class="block text-sm font-medium mb-2">
            Status
          </label>
          <select
            id="status"
            v-model="form.status"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="scheduled">Scheduled</option>
          </select>
        </div>

        <div>
          <label for="visibility" class="block text-sm font-medium mb-2">
            Visibility
          </label>
          <select
            id="visibility"
            v-model="form.visibility"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
          >
            <option value="public">Public</option>
            <option value="private">Private</option>
            <option value="members_only">Members Only</option>
          </select>
        </div>
      </div>

      <!-- Published At (for scheduled posts) -->
      <div v-if="form.status === 'scheduled'">
        <label for="published_at" class="block text-sm font-medium mb-2">
          Publish Date & Time
        </label>
        <input
          id="published_at"
          v-model="form.published_at"
          type="datetime-local"
          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-800"
        />
      </div>

      <!-- Featured Toggle -->
      <div class="flex items-center gap-2">
        <input
          id="featured"
          v-model="form.featured"
          type="checkbox"
          class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
        />
        <label for="featured" class="text-sm font-medium">
          Mark as featured post
        </label>
      </div>

      <!-- Authors & Categories -->
      <div class="space-y-6 pt-6 border-t">
        <h3 class="text-lg font-semibold">Authors & Categories</h3>

        <!-- Authors -->
        <PostAuthorsManager
          v-model="form.authors"
          :available-users="availableUsers"
        />

        <!-- Categories -->
        <div class="space-y-2">
          <label class="block text-sm font-medium">Categories</label>
          <div class="flex flex-wrap gap-4">
            <div
              v-for="category in availableCategories"
              :key="category.id"
              class="flex items-center"
            >
              <input
                :id="`category-${category.id}`"
                v-model="form.category_ids"
                type="checkbox"
                :value="category.id"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
              />
              <label
                :for="`category-${category.id}`"
                class="ml-2 text-sm"
              >
                {{ category.name }}
              </label>
            </div>
          </div>
          <p v-if="availableCategories.length === 0" class="text-sm text-gray-500 italic">
            No categories available.
            <button
              type="button"
              @click="navigateTo('/categories/create')"
              class="text-blue-600 hover:underline"
            >
              Create one
            </button>
          </p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-4 pt-6 border-t">
        <button
          type="submit"
          :disabled="loading || !form.title || !form.content"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? "Saving..." : "Create Post" }}
        </button>
        <button
          type="button"
          @click="navigateTo('/posts')"
          class="px-6 py-2 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800"
        >
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");

const { $api } = useNuxtApp();
const featuredImageRef = ref(null);

const form = reactive({
  title: "",
  slug: "",
  excerpt: "",
  content: "",
  status: "draft",
  visibility: "public",
  published_at: null,
  featured: false,
  featured_image: [],
  authors: [],
  category_ids: [],
});

const loading = ref(false);
const savedPostId = ref(null);
const availableUsers = ref([]);
const availableCategories = ref([]);

onMounted(async () => {
  await Promise.all([
    loadUsers(),
    loadCategories(),
  ]);
});

async function loadUsers() {
  try {
    const response = await $api("/users?per_page=100");
    availableUsers.value = response.data;
  } catch (error) {
    console.error("Failed to load users:", error);
  }
}

async function loadCategories() {
  try {
    const response = await $api("/categories?per_page=100");
    availableCategories.value = response.data;
  } catch (error) {
    console.error("Failed to load categories:", error);
  }
}

// Auto-generate slug from title
watch(
  () => form.title,
  (newTitle) => {
    if (!form.slug || form.slug === slugify(form.title)) {
      form.slug = slugify(newTitle);
    }
  }
);

function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/\s+/g, "-")
    .replace(/[^\w\-]+/g, "")
    .replace(/\-\-+/g, "-");
}

async function handleSubmit() {
  loading.value = true;

  try {
    // Step 1: Create the post first
    const postData = {
      title: form.title,
      slug: form.slug,
      excerpt: form.excerpt,
      content: form.content,
      content_format: "html",
      status: form.status,
      visibility: form.visibility,
      featured: form.featured,
      published_at:
        form.status === "scheduled" && form.published_at
          ? new Date(form.published_at).toISOString()
          : null,
      authors: form.authors,
      category_ids: form.category_ids,
    };

    const response = await $api("/posts", {
      method: "POST",
      body: postData,
    });

    savedPostId.value = response.data.id;

    // Step 2: Upload featured image if exists
    if (form.featured_image.length > 0 && featuredImageRef.value?.pond) {
      const pond = featuredImageRef.value.pond;
      const formData = new FormData();

      // Get actual file from FilePond
      const files = pond.getFiles();
      if (files.length > 0) {
        formData.append("file", files[0].file);
        formData.append("model_type", "App\\Models\\Post");
        formData.append("model_id", savedPostId.value);
        formData.append("collection", "featured_image");

        await $api("/media/upload", {
          method: "POST",
          body: formData,
        });
      }
    }

    // Success - navigate to posts list
    await navigateTo("/posts");
  } catch (error) {
    console.error("Failed to create post:", error);
    alert(error?.data?.message || "Failed to create post. Please try again.");
  } finally {
    loading.value = false;
  }
}
</script>
