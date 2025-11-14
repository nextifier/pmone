<template>
  <div class="container max-w-7xl mx-auto py-8 px-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold">Posts</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          Manage your blog posts
        </p>
      </div>
      <button
        @click="navigateTo('/posts/create')"
        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
      >
        <Icon name="lucide:plus" />
        New Post
      </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-4">
      <select
        v-model="filters.status"
        @change="fetchPosts"
        class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800"
      >
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="scheduled">Scheduled</option>
        <option value="archived">Archived</option>
      </select>

      <select
        v-model="filters.visibility"
        @change="fetchPosts"
        class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800"
      >
        <option value="">All Visibility</option>
        <option value="public">Public</option>
        <option value="private">Private</option>
        <option value="members_only">Members Only</option>
      </select>

      <input
        v-model="filters.search"
        @input="debounceSearch"
        type="text"
        placeholder="Search posts..."
        class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg dark:bg-gray-800 flex-1 min-w-[200px]"
      />
    </div>

    <!-- Posts Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">
        Loading posts...
      </div>

      <div v-else-if="posts.length === 0" class="p-8 text-center text-gray-500">
        No posts found.
        <button
          @click="navigateTo('/posts/create')"
          class="text-blue-600 hover:underline ml-2"
        >
          Create your first post
        </button>
      </div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Title
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Status
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Visibility
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Views
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Date
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr
            v-for="post in posts"
            :key="post.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-900/50"
          >
            <td class="px-6 py-4">
              <div class="flex items-start gap-3">
                <img
                  v-if="post.featured_image"
                  :src="post.featured_image.conversions?.sm || post.featured_image.url"
                  :alt="post.title"
                  class="w-16 h-16 object-cover rounded"
                />
                <div class="flex-1">
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ post.title }}
                  </div>
                  <div v-if="post.excerpt" class="text-sm text-gray-500 dark:text-gray-400 line-clamp-1">
                    {{ post.excerpt }}
                  </div>
                  <div v-if="post.featured" class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 rounded text-xs">
                    <Icon name="lucide:star" class="w-3 h-3" />
                    Featured
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <span
                :class="{
                  'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300': post.status === 'draft',
                  'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400': post.status === 'published',
                  'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400': post.status === 'scheduled',
                  'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-400': post.status === 'archived',
                }"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
              >
                {{ post.status }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span class="text-sm text-gray-600 dark:text-gray-400 capitalize">
                {{ post.visibility.replace('_', ' ') }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ post.view_count || 0 }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ formatDate(post.published_at || post.created_at) }}
              </div>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  @click="navigateTo(`/posts/${post.slug}`)"
                  class="p-2 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                  title="View"
                >
                  <Icon name="lucide:eye" class="w-4 h-4" />
                </button>
                <button
                  @click="navigateTo(`/posts/edit/${post.slug}`)"
                  class="p-2 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                  title="Edit"
                >
                  <Icon name="lucide:edit" class="w-4 h-4" />
                </button>
                <button
                  @click="deletePost(post)"
                  class="p-2 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                  title="Delete"
                >
                  <Icon name="lucide:trash-2" class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div
        v-if="pagination.total > pagination.per_page"
        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between"
      >
        <div class="text-sm text-gray-600 dark:text-gray-400">
          Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}
          to
          {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
          of {{ pagination.total }} posts
        </div>
        <div class="flex gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");

const { $api } = useNuxtApp();

const posts = ref([]);
const loading = ref(false);
const filters = reactive({
  status: "",
  visibility: "",
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

    if (filters.status) params.append("filter_status", filters.status);
    if (filters.visibility) params.append("filter_visibility", filters.visibility);
    if (filters.search) params.append("filter_search", filters.search);

    const response = await $api(`/posts?${params.toString()}`);

    posts.value = response.data;
    Object.assign(pagination, response.meta);
  } catch (error) {
    console.error("Failed to fetch posts:", error);
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

    fetchPosts();
  } catch (error) {
    console.error("Failed to delete post:", error);
    alert("Failed to delete post. Please try again.");
  }
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocalizedString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}
</script>
