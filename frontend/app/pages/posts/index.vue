<template>
  <div class="container mx-auto max-w-7xl px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-semibold">Posts</h1>
        <p class="text-muted-foreground mt-2">Manage your blog posts</p>
      </div>
      <button
        @click="navigateTo('/posts/create')"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter transition"
      >
        <Icon name="lucide:plus" class="h-4 w-4" />
        New Post
      </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-3">
      <Select v-model="filters.status" @update:model-value="fetchPosts">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="published">Published</SelectItem>
          <SelectItem value="scheduled">Scheduled</SelectItem>
          <SelectItem value="archived">Archived</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="filters.visibility" @update:model-value="fetchPosts">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Visibility" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Visibility</SelectItem>
          <SelectItem value="public">Public</SelectItem>
          <SelectItem value="private">Private</SelectItem>
          <SelectItem value="members_only">Members Only</SelectItem>
        </SelectContent>
      </Select>

      <Input
        v-model="filters.search"
        @input="debounceSearch"
        type="text"
        placeholder="Search posts..."
        class="min-w-[200px] flex-1"
      />
    </div>

    <!-- Posts Table -->
    <div class="border-border overflow-hidden rounded-lg border">
      <div v-if="loading" class="text-muted-foreground p-8 text-center">
        <Spinner class="mx-auto" />
        <p class="mt-2">Loading posts...</p>
      </div>

      <div v-else-if="posts.length === 0" class="text-muted-foreground p-8 text-center">
        <p>No posts found.</p>
        <button
          @click="navigateTo('/posts/create')"
          class="text-primary hover:text-primary/80 mt-2 underline"
        >
          Create your first post
        </button>
      </div>

      <table v-else class="w-full">
        <thead class="bg-muted/50 border-border border-b">
          <tr>
            <th
              class="text-muted-foreground px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
            >
              Title
            </th>
            <th
              class="text-muted-foreground px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
            >
              Status
            </th>
            <th
              class="text-muted-foreground px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
            >
              Visibility
            </th>
            <th
              class="text-muted-foreground px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
            >
              Views
            </th>
            <th
              class="text-muted-foreground px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
            >
              Date
            </th>
            <th
              class="text-muted-foreground px-6 py-3 text-right text-xs font-medium tracking-wider uppercase"
            >
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-border divide-y">
          <tr v-for="post in posts" :key="post.id" class="hover:bg-muted/30">
            <td class="px-6 py-4">
              <div class="flex items-start gap-3">
                <img
                  v-if="post.featured_image"
                  :src="post.featured_image.conversions?.sm || post.featured_image.url"
                  :alt="post.title"
                  class="border-border h-16 w-16 rounded border object-cover"
                />
                <div
                  v-else
                  class="bg-muted border-border flex size-16 shrink-0 items-center justify-center rounded border"
                >
                  <Icon name="lucide:image" class="text-muted-foreground h-6 w-6" />
                </div>
                <div class="flex-1">
                  <div class="font-medium">
                    {{ post.title }}
                  </div>
                  <div v-if="post.excerpt" class="text-muted-foreground line-clamp-1 text-sm">
                    {{ post.excerpt }}
                  </div>
                  <div
                    v-if="post.featured"
                    class="border-border mt-1 inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs"
                  >
                    <Icon name="lucide:star" class="h-3 w-3" />
                    Featured
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <span
                class="border-border inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize"
              >
                {{ post.status }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span class="text-muted-foreground text-sm capitalize">
                {{ post.visibility.replace("_", " ") }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span class="text-muted-foreground text-sm">
                {{ post.view_count || 0 }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="text-muted-foreground text-sm">
                {{ formatDate(post.published_at || post.created_at) }}
              </div>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="navigateTo(`/posts/${post.slug}`)"
                  class="hover:bg-accent hover:text-accent-foreground flex size-8 items-center justify-center rounded-lg transition"
                  title="View"
                >
                  <Icon name="lucide:eye" class="h-4 w-4" />
                </button>
                <button
                  @click="navigateTo(`/posts/edit/${post.slug}`)"
                  class="hover:bg-accent hover:text-accent-foreground flex size-8 items-center justify-center rounded-lg transition"
                  title="Edit"
                >
                  <Icon name="lucide:edit" class="h-4 w-4" />
                </button>
                <button
                  @click="deletePost(post)"
                  class="hover:bg-destructive hover:text-destructive-foreground flex size-8 items-center justify-center rounded-lg transition"
                  title="Delete"
                >
                  <Icon name="lucide:trash-2" class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="pagination.total > pagination.per_page"
        class="border-border flex items-center justify-between border-t px-6 py-4"
      >
        <div class="text-muted-foreground text-sm">
          Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}
          to
          {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
          of {{ pagination.total }} posts
        </div>
        <div class="flex gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="border-input hover:bg-accent hover:text-accent-foreground rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:cursor-not-allowed disabled:opacity-50"
          >
            Previous
          </button>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="border-input hover:bg-accent hover:text-accent-foreground rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:cursor-not-allowed disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
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

const { $api } = useNuxtApp();

const posts = ref([]);
const loading = ref(false);
const filters = reactive({
  status: "all",
  visibility: "all",
  search: "",
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

let searchTimeout = null;

onMounted(() => {
  fetchPosts();
});

async function fetchPosts() {
  loading.value = true;

  try {
    const params = new URLSearchParams();
    params.append("page", pagination.current_page);
    params.append("per_page", pagination.per_page);

    if (filters.status && filters.status !== "all") params.append("filter_status", filters.status);
    if (filters.visibility && filters.visibility !== "all")
      params.append("filter_visibility", filters.visibility);
    if (filters.search) params.append("filter_search", filters.search);

    const response = await $api(`/posts?${params.toString()}`);

    posts.value = response.data;
    Object.assign(pagination, response.meta);
  } catch (error) {
    console.error("Failed to fetch posts:", error);
    toast.error("Failed to load posts");
  } finally {
    loading.value = false;
  }
}

function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1;
    fetchPosts();
  }, 500);
}

function changePage(page) {
  pagination.current_page = page;
  fetchPosts();
}

async function deletePost(post) {
  if (!confirm(`Are you sure you want to delete "${post.title}"?`)) {
    return;
  }

  try {
    await $api(`/posts/${post.slug}`, {
      method: "DELETE",
    });

    toast.success("Post deleted successfully");
    fetchPosts();
  } catch (error) {
    console.error("Failed to delete post:", error);
    toast.error("Failed to delete post. Please try again.");
  }
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}
</script>
