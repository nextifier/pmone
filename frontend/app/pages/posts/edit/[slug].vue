<template>
  <div class="container max-w-6xl mx-auto py-8 px-4">
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Edit Post</h1>
        <p class="text-muted-foreground mt-2">
          Update your blog post
        </p>
      </div>
      <Button variant="outline" @click="navigateTo('/posts')">
        <Icon name="lucide:arrow-left" class="mr-2 h-4 w-4" />
        Back to Posts
      </Button>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="h-8 w-8" />
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <Tabs default-value="content" class="w-full">
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="content">Content</TabsTrigger>
          <TabsTrigger value="settings">Settings</TabsTrigger>
          <TabsTrigger value="seo">SEO & Meta</TabsTrigger>
        </TabsList>

        <!-- Content Tab -->
        <TabsContent value="content" class="space-y-6 mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Post Content</CardTitle>
              <CardDescription>
                Write and edit your post content
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <!-- Title -->
              <div class="space-y-2">
                <Label for="title">
                  Title <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="title"
                  v-model="form.title"
                  placeholder="Enter post title..."
                  required
                />
              </div>

              <!-- Slug -->
              <div class="space-y-2">
                <Label for="slug">
                  Slug
                  <span class="text-sm text-muted-foreground ml-2">
                    (Auto-generated from title)
                  </span>
                </Label>
                <Input
                  id="slug"
                  v-model="form.slug"
                  placeholder="post-slug"
                />
              </div>

              <!-- Excerpt -->
              <div class="space-y-2">
                <Label for="excerpt">Excerpt</Label>
                <Textarea
                  id="excerpt"
                  v-model="form.excerpt"
                  placeholder="Brief description of the post..."
                  rows="3"
                />
              </div>

              <!-- Featured Image -->
              <div class="space-y-2">
                <Label>Featured Image</Label>
                <InputFileImage
                  ref="featuredImageRef"
                  v-model="form.featured_image"
                  :initial-image="initialFeaturedImage"
                  :delete-flag="featuredImageDeleted"
                  @update:delete-flag="featuredImageDeleted = $event"
                  container-class="relative isolate aspect-video w-full max-w-2xl"
                />
              </div>

              <Separator />

              <!-- Content Editor -->
              <div class="space-y-2">
                <Label>
                  Content <span class="text-destructive">*</span>
                </Label>
                <PostTipTapEditor
                  v-model="form.content"
                  :post-id="postId"
                  placeholder="Start writing your post content..."
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Settings Tab -->
        <TabsContent value="settings" class="space-y-6 mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Post Settings</CardTitle>
              <CardDescription>
                Configure post status, visibility, and publishing options
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Status -->
                <div class="space-y-2">
                  <Label for="status">Status</Label>
                  <Select v-model="form.status">
                    <SelectTrigger id="status">
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="draft">Draft</SelectItem>
                      <SelectItem value="published">Published</SelectItem>
                      <SelectItem value="scheduled">Scheduled</SelectItem>
                      <SelectItem value="archived">Archived</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <!-- Visibility -->
                <div class="space-y-2">
                  <Label for="visibility">Visibility</Label>
                  <Select v-model="form.visibility">
                    <SelectTrigger id="visibility">
                      <SelectValue placeholder="Select visibility" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="public">Public</SelectItem>
                      <SelectItem value="private">Private</SelectItem>
                      <SelectItem value="members_only">Members Only</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <!-- Published At (for scheduled posts) -->
              <div v-if="form.status === 'scheduled'" class="space-y-2">
                <Label for="published_at">Publish Date & Time</Label>
                <Input
                  id="published_at"
                  v-model="form.published_at"
                  type="datetime-local"
                />
              </div>

              <Separator />

              <!-- Featured Toggle -->
              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label>Featured Post</Label>
                  <p class="text-sm text-muted-foreground">
                    Mark this post as featured
                  </p>
                </div>
                <Switch
                  :checked="form.featured"
                  @update:checked="form.featured = $event"
                />
              </div>
            </CardContent>
          </Card>

          <!-- Authors & Categories -->
          <Card>
            <CardHeader>
              <CardTitle>Authors & Categories</CardTitle>
              <CardDescription>
                Manage post authors and assign categories
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <!-- Authors -->
              <PostAuthorsManager
                v-model="form.authors"
                :available-users="availableUsers"
              />

              <Separator />

              <!-- Categories -->
              <div class="space-y-2">
                <Label>Categories</Label>
                <div class="flex flex-wrap gap-2">
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
                      class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <label
                      :for="`category-${category.id}`"
                      class="ml-2 text-sm"
                    >
                      {{ category.name }}
                    </label>
                  </div>
                </div>
                <p v-if="availableCategories.length === 0" class="text-sm text-muted-foreground italic">
                  No categories available.
                  <Button
                    variant="link"
                    size="sm"
                    @click="navigateTo('/categories/create')"
                    class="p-0 h-auto"
                  >
                    Create one
                  </Button>
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- SEO Tab -->
        <TabsContent value="seo" class="space-y-6 mt-6">
          <Card>
            <CardHeader>
              <CardTitle>SEO & Meta Tags</CardTitle>
              <CardDescription>
                Optimize your post for search engines
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <!-- Meta Title -->
              <div class="space-y-2">
                <Label for="meta_title">
                  Meta Title
                  <span class="text-sm text-muted-foreground ml-2">
                    (Max 60 characters)
                  </span>
                </Label>
                <Input
                  id="meta_title"
                  v-model="form.meta_title"
                  placeholder="SEO-optimized title..."
                  maxlength="60"
                />
                <p class="text-xs text-muted-foreground">
                  {{ form.meta_title?.length || 0 }}/60 characters
                </p>
              </div>

              <!-- Meta Description -->
              <div class="space-y-2">
                <Label for="meta_description">
                  Meta Description
                  <span class="text-sm text-muted-foreground ml-2">
                    (Max 160 characters)
                  </span>
                </Label>
                <Textarea
                  id="meta_description"
                  v-model="form.meta_description"
                  placeholder="Brief SEO description..."
                  rows="3"
                  maxlength="160"
                />
                <p class="text-xs text-muted-foreground">
                  {{ form.meta_description?.length || 0 }}/160 characters
                </p>
              </div>

              <Separator />

              <!-- OG Image URL -->
              <div class="space-y-2">
                <Label for="og_image">Open Graph Image URL</Label>
                <Input
                  id="og_image"
                  v-model="form.og_image"
                  placeholder="https://example.com/image.jpg"
                  type="url"
                />
                <p class="text-xs text-muted-foreground">
                  URL for social media sharing preview
                </p>
              </div>

              <!-- OG Type -->
              <div class="space-y-2">
                <Label for="og_type">Open Graph Type</Label>
                <Select v-model="form.og_type">
                  <SelectTrigger id="og_type">
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="article">Article</SelectItem>
                    <SelectItem value="website">Website</SelectItem>
                    <SelectItem value="blog">Blog</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <!-- Actions -->
      <Card>
        <CardContent class="pt-6">
          <div class="flex items-center gap-4">
            <Button
              type="submit"
              :disabled="saving || !form.title || !form.content"
            >
              <Icon
                v-if="saving"
                name="lucide:loader-2"
                class="mr-2 h-4 w-4 animate-spin"
              />
              {{ saving ? "Saving..." : "Update Post" }}
            </Button>
            <Button
              type="button"
              variant="outline"
              @click="navigateTo('/posts')"
            >
              Cancel
            </Button>
          </div>
        </CardContent>
      </Card>
    </form>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");

const route = useRoute();
const { $api } = useNuxtApp();
const slug = route.params.slug;

const loading = ref(true);
const saving = ref(false);
const postId = ref(null);
const featuredImageRef = ref(null);
const featuredImageDeleted = ref(false);
const initialFeaturedImage = ref(null);

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
  og_image: "",
  og_type: "article",
  featured_image: [],
  authors: [],
  category_ids: [],
});

const availableUsers = ref([]);
const availableCategories = ref([]);

onMounted(async () => {
  await Promise.all([
    loadPost(),
    loadUsers(),
    loadCategories(),
  ]);
});

async function loadPost() {
  try {
    const response = await $api(`/posts/${slug}`);
    const post = response.data;

    postId.value = post.id;

    // Populate form
    form.title = post.title;
    form.slug = post.slug;
    form.excerpt = post.excerpt || "";
    form.content = post.content;
    form.status = post.status;
    form.visibility = post.visibility;
    form.featured = post.featured;
    form.meta_title = post.meta_title || "";
    form.meta_description = post.meta_description || "";
    form.og_image = post.og_image || "";
    form.og_type = post.og_type || "article";

    // Format published_at for datetime-local input
    if (post.published_at) {
      const date = new Date(post.published_at);
      form.published_at = date.toISOString().slice(0, 16);
    }

    // Set initial featured image
    if (post.featured_image) {
      initialFeaturedImage.value = post.featured_image.conversions || {
        sm: post.featured_image.url,
      };
    }

    // Populate authors
    if (post.authors && Array.isArray(post.authors)) {
      form.authors = post.authors.map(author => ({
        user_id: author.id,
        role: author.pivot?.role || "co_author",
        order: author.pivot?.order || 0,
      }));
    }

    // Populate categories
    if (post.categories && Array.isArray(post.categories)) {
      form.category_ids = post.categories.map(category => category.id);
    }
  } catch (error) {
    console.error("Failed to load post:", error);
    alert("Failed to load post. Redirecting to posts list.");
    await navigateTo("/posts");
  } finally {
    loading.value = false;
  }
}

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
  saving.value = true;

  try {
    // Update post data
    const postData = {
      title: form.title,
      slug: form.slug,
      excerpt: form.excerpt,
      content: form.content,
      content_format: "html",
      status: form.status,
      visibility: form.visibility,
      featured: form.featured,
      meta_title: form.meta_title,
      meta_description: form.meta_description,
      og_image: form.og_image,
      og_type: form.og_type,
      published_at:
        form.status === "scheduled" && form.published_at
          ? new Date(form.published_at).toISOString()
          : null,
      authors: form.authors,
      category_ids: form.category_ids,
    };

    await $api(`/posts/${slug}`, {
      method: "PUT",
      body: postData,
    });

    // Handle featured image upload/delete
    if (featuredImageDeleted.value && initialFeaturedImage.value) {
      // Delete existing featured image
      // (API will handle this via media collection clear)
    }

    if (form.featured_image.length > 0 && featuredImageRef.value?.pond) {
      const pond = featuredImageRef.value.pond;
      const formData = new FormData();
      const files = pond.getFiles();

      if (files.length > 0) {
        formData.append("file", files[0].file);
        formData.append("model_type", "App\\Models\\Post");
        formData.append("model_id", postId.value);
        formData.append("collection", "featured_image");

        await $api("/media/upload", {
          method: "POST",
          body: formData,
        });
      }
    }

    // Success
    await navigateTo("/posts");
  } catch (error) {
    console.error("Failed to update post:", error);
    alert(error?.data?.message || "Failed to update post. Please try again.");
  } finally {
    saving.value = false;
  }
}
</script>
