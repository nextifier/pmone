<template>
  <div class="container max-w-7xl mx-auto py-8 px-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold">Categories</h1>
        <p class="text-muted-foreground mt-2">
          Manage your blog categories and organize your content
        </p>
      </div>
      <Button @click="navigateTo('/categories/create')">
        <Icon name="lucide:plus" class="mr-2 h-4 w-4" />
        New Category
      </Button>
    </div>

    <!-- Filters & Actions -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex flex-wrap gap-4">
          <div class="flex-1 min-w-[200px]">
            <Input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search categories..."
            >
              <template #prefix>
                <Icon name="lucide:search" class="h-4 w-4 text-muted-foreground" />
              </template>
            </Input>
          </div>

          <Select v-model="filters.visibility">
            <SelectTrigger class="w-[180px]">
              <SelectValue placeholder="All Visibility" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All Visibility</SelectItem>
              <SelectItem value="public">Public</SelectItem>
              <SelectItem value="private">Private</SelectItem>
            </SelectContent>
          </Select>

          <Select v-model="filters.parent">
            <SelectTrigger class="w-[180px]">
              <SelectValue placeholder="All Levels" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All Levels</SelectItem>
              <SelectItem value="root">Root Only</SelectItem>
              <SelectItem value="children">Children Only</SelectItem>
            </SelectContent>
          </Select>

          <Button
            variant="outline"
            @click="navigateTo('/categories/trash')"
          >
            <Icon name="lucide:trash-2" class="mr-2 h-4 w-4" />
            Trash
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Categories Table -->
    <Card>
      <CardContent class="p-0">
        <div v-if="loading" class="flex items-center justify-center py-12">
          <Spinner class="h-8 w-8" />
        </div>

        <div v-else-if="categories.length === 0" class="p-8 text-center text-muted-foreground">
          No categories found.
          <Button
            variant="link"
            @click="navigateTo('/categories/create')"
            class="ml-2"
          >
            Create your first category
          </Button>
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
                  Parent
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Visibility
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                  Posts
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
                  <div class="flex items-center gap-2">
                    <div
                      v-if="category.parent"
                      class="w-4 text-muted-foreground"
                    >
                      <Icon name="lucide:corner-down-right" class="h-4 w-4" />
                    </div>
                    <div>
                      <div class="font-medium">{{ category.name }}</div>
                      <div v-if="category.description" class="text-sm text-muted-foreground line-clamp-1">
                        {{ category.description }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <code class="text-sm bg-muted px-2 py-1 rounded">
                    {{ category.slug }}
                  </code>
                </td>
                <td class="px-6 py-4">
                  <span v-if="category.parent" class="text-sm text-muted-foreground">
                    {{ category.parent.name }}
                  </span>
                  <span v-else class="text-sm text-muted-foreground italic">
                    Root
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <Icon
                      :name="category.visibility === 'public' ? 'lucide:eye' : 'lucide:eye-off'"
                      class="h-4 w-4 text-muted-foreground"
                    />
                    <span class="text-sm capitalize">
                      {{ category.visibility }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="text-sm text-muted-foreground">
                    {{ category.posts_count || 0 }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="navigateTo(`/categories/edit/${category.id}`)"
                      title="Edit"
                    >
                      <Icon name="lucide:edit" class="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      @click="deleteCategory(category)"
                      title="Delete"
                    >
                      <Icon name="lucide:trash-2" class="h-4 w-4" />
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
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
  visibility: "",
  parent: "",
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

let searchTimeout = null;

onMounted(() => {
  fetchCategories();
});

// Watch filters
watch(
  () => [filters.visibility, filters.parent],
  () => {
    pagination.current_page = 1;
    fetchCategories();
  }
);

async function fetchCategories() {
  loading.value = true;

  try {
    const params = new URLSearchParams();
    params.append("page", pagination.current_page);
    params.append("per_page", pagination.per_page);

    if (filters.search) params.append("filter_search", filters.search);
    if (filters.visibility) params.append("filter_visibility", filters.visibility);

    if (filters.parent === "root") {
      params.append("filter_root", "1");
    } else if (filters.parent === "children") {
      params.append("filter_has_parent", "1");
    }

    const response = await $api(`/categories?${params.toString()}`);

    categories.value = response.data;
    Object.assign(pagination, response.meta);
  } catch (error) {
    console.error("Failed to fetch categories:", error);
  } finally {
    loading.value = false;
  }
}

function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1;
    fetchCategories();
  }, 500);
}

function changePage(page) {
  pagination.current_page = page;
  fetchCategories();
}

async function deleteCategory(category) {
  if (!confirm(`Are you sure you want to delete "${category.name}"?`)) {
    return;
  }

  try {
    await $api(`/categories/${category.id}`, {
      method: "DELETE",
    });

    fetchCategories();
  } catch (error) {
    console.error("Failed to delete category:", error);
    alert(error?.data?.message || "Failed to delete category. Please try again.");
  }
}
</script>
