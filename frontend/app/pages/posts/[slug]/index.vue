<template>
  <div class="container mx-auto max-w-4xl px-4 py-8">
    <!-- Loading State -->
    <LoadingState v-if="loading" label="Loading post.." />

    <!-- Post Content -->
    <article v-else-if="post" class="space-y-8">
      <!-- Header -->
      <div class="space-y-4">
        <div class="flex items-center gap-2">
          <button
            @click="navigateTo('/posts')"
            class="hover:bg-accent hover:text-accent-foreground flex size-8 items-center justify-center rounded-lg transition"
            title="Back to posts"
          >
            <Icon name="lucide:arrow-left" class="h-4 w-4" />
          </button>
          <div class="flex flex-1 items-center gap-2">
            <span
              class="border-border inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize"
            >
              {{ post.status }}
            </span>
            <span
              v-if="post.featured"
              class="border-border inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
            >
              <Icon name="lucide:star" class="h-3 w-3" />
              Featured
            </span>
          </div>
          <div v-if="isAuthenticated" class="flex gap-2">
            <button
              @click="navigateTo(`/posts/${post.slug}/analytics`)"
              class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition"
            >
              <Icon name="lucide:bar-chart" class="h-4 w-4" />
              Analytics
            </button>
            <button
              @click="navigateTo(`/posts/${post.slug}/edit`)"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter transition"
            >
              <Icon name="lucide:edit" class="h-4 w-4" />
              Edit Post
            </button>
          </div>
        </div>

        <h1 class="text-4xl font-bold">{{ post.title }}</h1>

        <div v-if="post.excerpt" class="text-muted-foreground text-lg">
          {{ post.excerpt }}
        </div>

        <!-- Post Meta -->
        <div class="flex flex-wrap items-center gap-4 text-sm">
          <div v-if="post.authors && post.authors.length > 0" class="flex items-center gap-2">
            <Icon name="lucide:user" class="text-muted-foreground h-4 w-4" />
            <span class="text-muted-foreground">
              {{ post.authors.map((a) => a.name).join(", ") }}
            </span>
          </div>
          <div v-if="post.published_at || post.created_at" class="flex items-center gap-2">
            <Icon name="lucide:calendar" class="text-muted-foreground h-4 w-4" />
            <span class="text-muted-foreground">
              {{ formatDate(post.published_at || post.created_at) }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <Icon name="lucide:eye" class="text-muted-foreground h-4 w-4" />
            <span class="text-muted-foreground">{{ post.visits_count || 0 }} views</span>
          </div>
          <div class="flex items-center gap-2">
            <Icon name="lucide:globe" class="text-muted-foreground h-4 w-4" />
            <span class="text-muted-foreground capitalize">
              {{ post.visibility.replace("_", " ") }}
            </span>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="post.tags && post.tags.length > 0" class="flex flex-wrap gap-2">
          <span
            v-for="tag in post.tags"
            :key="tag"
            class="bg-muted border-border inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium"
          >
            {{ tag }}
          </span>
        </div>
      </div>

      <!-- Featured Image -->
      <div v-if="post.featured_image" class="space-y-2">
        <div class="overflow-hidden rounded-lg">
          <img
            :src="post.featured_image.lg || post.featured_image.original"
            :alt="post.featured_image.alt || post.title"
            class="h-auto w-full object-cover"
          />
        </div>
        <p
          v-if="post.featured_image.caption"
          class="text-muted-foreground text-center text-sm italic"
        >
          {{ post.featured_image.caption }}
        </p>
      </div>

      <!-- Post Content -->
      <div
        class="prose prose-sm sm:prose lg:prose-lg dark:prose-invert max-w-none"
        v-html="post.content"
      ></div>

      <!-- Footer Actions -->
      <div class="border-border flex items-center justify-between border-t pt-8">
        <button
          @click="navigateTo('/posts')"
          class="border-input hover:bg-accent hover:text-accent-foreground flex items-center gap-x-1.5 rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition"
        >
          <Icon name="lucide:arrow-left" class="h-4 w-4" />
          Back to Posts
        </button>
        <button
          v-if="isAuthenticated"
          @click="deletePost"
          class="border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground flex items-center gap-x-1.5 rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition"
        >
          <Icon name="lucide:trash-2" class="h-4 w-4" />
          Delete Post
        </button>
      </div>
    </article>

    <!-- Post Not Found -->
    <div v-else class="py-12 text-center">
      <Icon name="lucide:file-x" class="text-muted-foreground mx-auto h-16 w-16" />
      <p class="text-muted-foreground mt-4 text-lg">Post not found</p>
      <button
        @click="navigateTo('/posts')"
        class="text-primary hover:text-primary/80 mt-4 underline"
      >
        Back to Posts
      </button>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["posts.read"],
  layout: "app",
});

const route = useRoute();
const postSlug = route.params.slug;
const config = useRuntimeConfig();
const { user } = useSanctumAuth();

// Check if user is authenticated
const isAuthenticated = computed(() => !!user.value);

const {
  data: postResponse,
  pending: loading,
  error: fetchError,
  refresh: loadPost,
} = await useLazySanctumFetch(() => `/api/posts/${postSlug}`, {
  key: `post-${postSlug}`,
});

const post = computed(() => postResponse.value?.data || null);

async function deletePost() {
  if (!isAuthenticated.value) {
    toast.error("You must be signed in to delete posts");
    return;
  }

  if (!confirm(`Are you sure you want to delete "${post.value.title}"?`)) {
    return;
  }

  try {
    const client = useSanctumClient();
    await client(`/api/posts/${postSlug}`, {
      method: "DELETE",
    });

    toast.success("Post deleted successfully");
    await navigateTo("/posts");
  } catch (error) {
    console.error("Failed to delete post:", error);
    toast.error("Failed to delete post. Please try again.");
  }
}

const { formatDateTime: formatDate } = useFormatters();
</script>
