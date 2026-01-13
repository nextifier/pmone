<template>
  <div class="post-editor-content">
    <!-- Editor Tab -->
    <TabsContent value="editor" class="mt-0">
      <div class="mx-auto max-w-2xl space-y-8 py-6 pb-20">
        <!-- Title -->
        <div class="space-y-2">
          <Label for="title" class="sr-only">Title</Label>
          <Textarea
            id="title"
            v-model="editor.form.title"
            required
            rows="1"
            placeholder="Post title..."
            class="placeholder:text-muted-foreground/50 leading-tighter w-full resize-none border-0 bg-transparent px-0 text-3xl leading-tight! font-semibold tracking-tighter shadow-none outline-none focus-visible:ring-0 lg:text-4xl"
          />
          <InputErrorMessage :errors="editor.errors.value.title" />
        </div>

        <!-- Featured Image -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <Label class="text-muted-foreground text-sm font-medium">Featured Image</Label>
            <button
              v-if="editor.imageFiles.value.featured_image?.length > 0"
              type="button"
              @click="clearFeaturedImage"
              class="text-muted-foreground hover:text-destructive text-xs transition"
            >
              Remove
            </button>
          </div>
          <InputFileImage
            ref="featuredImageInput"
            v-model="editor.imageFiles.value.featured_image"
            :initial-image="editor.initialData.value?.featured_image"
            v-model:delete-flag="editor.deleteFlags.value.featured_image"
            container-class="relative isolate aspect-video w-full"
          />
          <InputErrorMessage :errors="editor.errors.value.tmp_featured_image" />

          <!-- Featured Image Caption -->
          <div v-if="showCaptionInput" class="space-y-2">
            <Label for="featured_image_caption" class="text-muted-foreground text-xs">
              Image Caption (Optional)
            </Label>
            <Input
              id="featured_image_caption"
              v-model="editor.form.featured_image_caption"
              type="text"
              maxlength="500"
              placeholder="Add a caption for the featured image..."
              class="text-sm"
            />
            <InputErrorMessage :errors="editor.errors.value.featured_image_caption" />
          </div>
        </div>

        <!-- Content Body -->
        <div class="space-y-2">
          <Label class="sr-only">Content</Label>
          <PostTipTapEditor
            v-model="editor.form.content"
            :post-id="editor.postId.value"
            placeholder="Start writing your post content..."
          />
          <InputErrorMessage :errors="editor.errors.value.content" />
        </div>
      </div>
    </TabsContent>

    <!-- Preview Tab -->
    <TabsContent value="preview" class="mt-0">
      <div class="mx-auto max-w-2xl py-6 pb-20">
        <article>
          <!-- Featured Image Preview -->
          <div
            v-if="editor.previewData.value.featured_image"
            class="mb-8 aspect-video w-full overflow-hidden rounded-lg"
          >
            <img
              :src="getImageSrc(editor.previewData.value.featured_image)"
              :alt="editor.previewData.value.title"
              class="size-full object-cover"
            />
            <p
              v-if="editor.previewData.value.featured_image_caption"
              class="text-muted-foreground mt-2 text-center text-sm italic"
            >
              {{ editor.previewData.value.featured_image_caption }}
            </p>
          </div>

          <!-- Post Header -->
          <header class="mb-8 space-y-4">
            <!-- Title -->
            <h1 class="text-3xl font-bold tracking-tight lg:text-4xl">
              {{ editor.previewData.value.title || "Untitled Post" }}
            </h1>

            <!-- Meta -->
            <div class="text-muted-foreground flex flex-wrap items-center gap-3 text-sm">
              <!-- Status Badge -->
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getStatusClass(editor.previewData.value.status)"
              >
                {{ editor.previewData.value.status || "draft" }}
              </span>

              <!-- Visibility Badge -->
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="getVisibilityClass(editor.previewData.value.visibility)"
              >
                {{ editor.previewData.value.visibility || "public" }}
              </span>

              <!-- Featured Badge -->
              <span
                v-if="editor.previewData.value.featured"
                class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200"
              >
                Featured
              </span>
            </div>

            <!-- Tags -->
            <div v-if="editor.previewData.value.tags?.length > 0" class="flex flex-wrap gap-2">
              <span
                v-for="tag in editor.previewData.value.tags"
                :key="tag"
                class="bg-muted inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium"
              >
                <Icon name="lucide:tag" class="size-3" />
                {{ tag }}
              </span>
            </div>
          </header>

          <!-- Post Content -->
          <div class="prose prose-lg dark:prose-invert max-w-none" v-html="sanitizedContent" />

          <!-- No Content Message -->
          <div
            v-if="!editor.previewData.value.content"
            class="text-muted-foreground py-12 text-center"
          >
            <Icon name="hugeicons:file-02" class="mx-auto mb-3 size-12 opacity-50" />
            <p>No content yet. Start writing in the Editor tab.</p>
          </div>
        </article>
      </div>
    </TabsContent>
  </div>
</template>

<script setup lang="ts">
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { TabsContent } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { usePostEditor } from "@/composables/usePostEditor";

const editor = usePostEditor();
const { sanitizeHtml } = useSanitize();

const featuredImageInput = ref<any>(null);

// Register image ref with parent
const parent = getCurrentInstance()?.parent;
onMounted(() => {
  if (parent?.exposed?.setFeaturedImageRef) {
    parent.exposed.setFeaturedImageRef(featuredImageInput.value);
  }
});

const showCaptionInput = computed(() => {
  return (
    editor.imageFiles.value.featured_image?.length > 0 || editor.initialData.value?.featured_image
  );
});

const sanitizedContent = computed(() => {
  return sanitizeHtml(editor.previewData.value.content) || "";
});

function getImageSrc(image: any): string {
  if (typeof image === "string") {
    return image;
  }
  return image?.lg || image?.original || "";
}

function clearFeaturedImage() {
  editor.imageFiles.value.featured_image = [];
  editor.deleteFlags.value.featured_image = true;
}

function getStatusClass(status: string) {
  const classes: Record<string, string> = {
    draft: "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200",
    published: "bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200",
    scheduled: "bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200",
    archived: "bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-200",
  };
  return classes[status] || classes.draft;
}

function getVisibilityClass(visibility: string) {
  const classes: Record<string, string> = {
    public: "bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200",
    private: "bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200",
    members_only: "bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200",
  };
  return classes[visibility] || classes.public;
}
</script>
