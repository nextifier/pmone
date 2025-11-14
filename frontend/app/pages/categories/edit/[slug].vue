<template>
  <div class="container max-w-4xl mx-auto py-8 px-4">
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Edit Category</h1>
        <p class="text-muted-foreground mt-2">
          Update category information
        </p>
      </div>
      <Button variant="outline" @click="navigateTo('/categories')">
        <Icon name="lucide:arrow-left" class="mr-2 h-4 w-4" />
        Back to Categories
      </Button>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="h-8 w-8" />
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <Tabs default-value="general" class="w-full">
        <TabsList class="grid w-full grid-cols-2">
          <TabsTrigger value="general">General</TabsTrigger>
          <TabsTrigger value="settings">Settings</TabsTrigger>
        </TabsList>

        <!-- General Tab -->
        <TabsContent value="general" class="space-y-6 mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Category Information</CardTitle>
              <CardDescription>
                Basic information about the category
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <!-- Name -->
              <div class="space-y-2">
                <Label for="name">
                  Name <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="Enter category name..."
                  required
                />
              </div>

              <!-- Slug -->
              <div class="space-y-2">
                <Label for="slug">
                  Slug
                  <span class="text-sm text-muted-foreground ml-2">
                    (Auto-generated from name)
                  </span>
                </Label>
                <Input
                  id="slug"
                  v-model="form.slug"
                  placeholder="category-slug"
                />
              </div>

              <!-- Description -->
              <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea
                  id="description"
                  v-model="form.description"
                  placeholder="Brief description of the category..."
                  rows="3"
                />
              </div>

              <Separator />

              <!-- Parent Category -->
              <div class="space-y-2">
                <Label for="parent_id">Parent Category</Label>
                <Select v-model="form.parent_id">
                  <SelectTrigger id="parent_id">
                    <SelectValue placeholder="None (Root Category)" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="null">None (Root Category)</SelectItem>
                    <SelectItem
                      v-for="category in availableParents"
                      :key="category.id"
                      :value="category.id"
                    >
                      {{ category.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                  Select a parent category to create a nested structure
                </p>
              </div>

              <!-- Stats -->
              <div v-if="stats" class="space-y-4">
                <Separator />
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Posts Count</p>
                    <p class="text-2xl font-bold">{{ stats.posts_count }}</p>
                  </div>
                  <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Child Categories</p>
                    <p class="text-2xl font-bold">{{ stats.children_count || 0 }}</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Settings Tab -->
        <TabsContent value="settings" class="space-y-6 mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Category Settings</CardTitle>
              <CardDescription>
                Configure visibility and display options
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
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
                  </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                  Private categories won't be visible in public API
                </p>
              </div>

              <Separator />

              <!-- Order -->
              <div class="space-y-2">
                <Label for="order">Display Order</Label>
                <Input
                  id="order"
                  v-model.number="form.order"
                  type="number"
                  min="0"
                  placeholder="0"
                />
                <p class="text-xs text-muted-foreground">
                  Lower numbers appear first
                </p>
              </div>

              <Separator />

              <!-- SEO Meta Title -->
              <div class="space-y-2">
                <Label for="meta_title">
                  Meta Title
                  <span class="text-sm text-muted-foreground ml-2">
                    (Optional)
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

              <!-- SEO Meta Description -->
              <div class="space-y-2">
                <Label for="meta_description">
                  Meta Description
                  <span class="text-sm text-muted-foreground ml-2">
                    (Optional)
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
              :disabled="saving || !form.name"
            >
              <Icon
                v-if="saving"
                name="lucide:loader-2"
                class="mr-2 h-4 w-4 animate-spin"
              />
              {{ saving ? "Saving..." : "Update Category" }}
            </Button>
            <Button
              type="button"
              variant="outline"
              @click="navigateTo('/categories')"
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("categories");

const route = useRoute();
const { $api } = useNuxtApp();
const categorySlug = route.params.slug;

const form = reactive({
  name: "",
  slug: "",
  description: "",
  parent_id: null,
  visibility: "public",
  order: 0,
  meta_title: "",
  meta_description: "",
});

const loading = ref(true);
const saving = ref(false);
const availableParents = ref([]);
const stats = ref(null);

onMounted(async () => {
  await Promise.all([
    loadCategory(),
    loadParentCategories(),
  ]);
});

// Auto-generate slug from name
watch(
  () => form.name,
  (newName) => {
    if (!form.slug || form.slug === slugify(form.name)) {
      form.slug = slugify(newName);
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

async function loadCategory() {
  try {
    const response = await $api(`/categories/${categorySlug}`);
    const category = response.data;

    // Populate form
    form.name = category.name;
    form.slug = category.slug;
    form.description = category.description || "";
    form.parent_id = category.parent_id;
    form.visibility = category.visibility;
    form.order = category.order || 0;
    form.meta_title = category.meta_title || "";
    form.meta_description = category.meta_description || "";

    // Set stats
    stats.value = {
      posts_count: category.posts_count || 0,
      children_count: category.children_count || 0,
    };
  } catch (error) {
    console.error("Failed to load category:", error);
    alert("Failed to load category. Redirecting to categories list.");
    await navigateTo("/categories");
  } finally {
    loading.value = false;
  }
}

async function loadParentCategories() {
  try {
    const response = await $api("/categories?per_page=100");
    // Filter out current category and its descendants
    availableParents.value = response.data.filter(
      (cat) => cat.slug !== categorySlug
    );
  } catch (error) {
    console.error("Failed to load parent categories:", error);
  }
}

async function handleSubmit() {
  saving.value = true;

  try {
    const categoryData = {
      name: form.name,
      slug: form.slug,
      description: form.description,
      parent_id: form.parent_id,
      visibility: form.visibility,
      order: form.order,
      meta_title: form.meta_title,
      meta_description: form.meta_description,
    };

    await $api(`/categories/${categorySlug}`, {
      method: "PUT",
      body: categoryData,
    });

    // Success - navigate to categories list
    await navigateTo("/categories");
  } catch (error) {
    console.error("Failed to update category:", error);
    alert(error?.data?.message || "Failed to update category. Please try again.");
  } finally {
    saving.value = false;
  }
}
</script>
