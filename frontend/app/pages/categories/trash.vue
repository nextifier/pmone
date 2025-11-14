<template>
  <div class="container max-w-7xl mx-auto py-8 px-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold">Category Trash</h1>
        <p class="text-muted-foreground mt-2">
          Manage deleted categories and restore or permanently delete them
        </p>
      </div>
      <Button variant="outline" @click="navigateTo('/categories')">
        <Icon name="lucide:arrow-left" class="mr-2 h-4 w-4" />
        Back to Categories
      </Button>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex flex-wrap gap-4">
          <div class="flex-1 min-w-[200px]">
            <Input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search deleted categories..."
            >
              <template #prefix>
                <Icon name="lucide:search" class="h-4 w-4 text-muted-foreground" />
              </template>
            </Input>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Trash Table -->
    <Card>
      <CardContent class="p-0">
        <div v-if="loading" class="flex items-center justify-center py-12">
          <Spinner class="h-8 w-8" />
        </div>

        <div v-else-if="categories.length === 0" class="p-8 text-center text-muted-foreground">
          No deleted categories found.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-muted/50 border-b">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Name
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Slug
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Deleted By
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Deleted At
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr
                v-for="category in categories"
                :key="category.id"
                class="hover:bg-muted/30 transition-colors"
              >
                <td class="px-6 py-4">
                  <div class="font-medium">{{ category.name }}</div>
                  <div v-if="category.description" class="text-sm text-muted-foreground line-clamp-1">
                    {{ category.description }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <code class="text-sm bg-muted px-2 py-1 rounded">
                    {{ category.slug }}
                  </code>
                </td>
                <td class="px-6 py-4">
                  <span v-if="category.deleter" class="text-sm text-muted-foreground">
                    {{ category.deleter.name }}
                  </span>
                  <span v-else class="text-sm text-muted-foreground italic">
                    Unknown
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-sm text-muted-foreground">
                    {{ formatDate(category.deleted_at) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="restoreCategory(category)"
                      title="Restore"
                    >
                      <Icon name="lucide:undo-2" class="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="permanentlyDelete(category)"
                      title="Permanently Delete"
                    >
                      <Icon name="lucide:trash-2" class="h-4 w-4 text-destructive" />
                    </Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="pagination.total > pagination.per_page"
          class="px-6 py-4 border-t flex items-center justify-between"
        >
          <div class="text-sm text-muted-foreground">
            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}
            to
            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
            of {{ pagination.total }} categories
          </div>
          <div class="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
            >
              Next
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("categories");

const { $api } = useNuxtApp();

const categories = ref([]);
const loading = ref(false);
const filters = reactive({
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
  fetchTrash();
});

async function fetchTrash() {
  loading.value = true;

  try {
    const params = new URLSearchParams();
    params.append("page", pagination.current_page);
    params.append("per_page", pagination.per_page);

    if (filters.search) params.append("filter_search", filters.search);

    const response = await $api(`/categories/trash?${params.toString()}`);

    categories.value = response.data;
    Object.assign(pagination, response.meta);
  } catch (error) {
    console.error("Failed to fetch trash:", error);
  } finally {
    loading.value = false;
  }
}

function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1;
    fetchTrash();
  }, 500);
}

function changePage(page) {
  pagination.current_page = page;
  fetchTrash();
}

async function restoreCategory(category) {
  if (!confirm(`Are you sure you want to restore "${category.name}"?`)) {
    return;
  }

  try {
    await $api(`/categories/${category.id}/restore`, {
      method: "POST",
    });

    fetchTrash();
  } catch (error) {
    console.error("Failed to restore category:", error);
    alert(error?.data?.message || "Failed to restore category. Please try again.");
  }
}

async function permanentlyDelete(category) {
  if (!confirm(`Are you sure you want to PERMANENTLY delete "${category.name}"? This action cannot be undone!`)) {
    return;
  }

  try {
    await $api(`/categories/${category.id}/force`, {
      method: "DELETE",
    });

    fetchTrash();
  } catch (error) {
    console.error("Failed to permanently delete category:", error);
    alert(error?.data?.message || "Failed to permanently delete category. Please try again.");
  }
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}
</script>
