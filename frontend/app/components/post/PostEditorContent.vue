<template>
  <div class="post-editor-content">
    <!-- Editor Tab -->
    <TabsContent value="editor" class="mt-0" force-mount>
      <div class="mx-auto max-w-2xl space-y-8 py-6 pb-20">
        <!-- Title -->
        <div class="space-y-2">
          <DevOnly>
            <div v-if="editor.postId.value" class="text-foreground text-sm font-medium">
              ID: {{ editor.postId.value }}
            </div>
          </DevOnly>
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
    <TabsContent value="preview" class="mt-0" force-mount>
      <main class="mx-auto w-full max-w-[38rem] py-8">
        <div class="flex flex-col items-center text-center xl:items-center xl:text-center">
          <!-- First Tag Badge -->
          <span
            v-if="previewTags?.length > 0"
            class="text-primary border-border mb-3 flex items-center justify-center rounded-full border px-3 py-2 text-xs font-semibold tracking-tighter capitalize sm:text-sm"
          >
            {{ previewTags[0] }}
          </span>

          <!-- Title -->
          <h1
            class="text-primary text-[clamp(2rem,9vw,3rem)] !leading-[1.2] font-semibold tracking-tighter text-balance xl:-mx-12"
          >
            {{ editor.previewData.value.title || "Untitled Post" }}
          </h1>

          <!-- Authors -->
          <div v-if="previewAuthors?.length" class="mt-4">
            <div class="flex items-center gap-x-2 text-left">
              <div class="flex shrink-0 -space-x-4">
                <div
                  v-for="(author, index) in previewAuthors"
                  :key="index"
                  class="gradient-insta relative rounded-full bg-linear-to-tr p-0.5"
                  :style="`z-index: ${previewAuthors.length - index}`"
                >
                  <div
                    class="border-background bg-muted flex size-10 items-center justify-center overflow-hidden rounded-full border-2"
                  >
                    <NuxtImg
                      v-if="author.profile_image"
                      :src="
                        author.profile_image?.sm ||
                        author.profile_image?.original ||
                        author.profile_image
                      "
                      class="size-full object-cover"
                      width="56"
                      height="56"
                      sizes="120px"
                      loading="lazy"
                      format="webp"
                    />
                    <Icon v-else name="hugeicons:user" class="text-muted-foreground size-5" />
                  </div>
                </div>
              </div>

              <div class="flex flex-col gap-y-1">
                <div class="text-primary line-clamp-1 font-medium tracking-tight">
                  <span v-for="(author, index) in previewAuthors" :key="index">
                    {{ author.name }}<span v-if="index != previewAuthors.length - 1">, </span>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Date & Reading Time -->
          <div
            class="text-muted-foreground mt-4 flex w-full items-center justify-between gap-x-3 text-xs tracking-tight sm:text-sm"
          >
            <span v-if="editor.previewData.value.published_at" v-tippy="formattedPublishedAt">
              Posted {{ relativePublishedAt }}
            </span>
            <span v-else class="text-muted-foreground/50">Not published yet</span>

            <span v-if="readingTime" class="flex items-center gap-x-1.5">
              <Icon name="lucide:clock-fading" class="size-4 shrink-0" />
              <span>{{ readingTime }} min<span v-if="readingTime > 1">s</span> read</span>
            </span>
          </div>

          <!-- Excerpt -->
          <div
            v-if="editor.previewData.value.excerpt"
            class="text-primary mt-10 text-xl font-semibold tracking-tighter text-pretty sm:text-2xl"
          >
            {{ editor.previewData.value.excerpt }}
          </div>
        </div>

        <!-- Featured Image -->
        <div
          v-if="editor.previewData.value.featured_image"
          class="bg-muted mx-auto mt-10 block overflow-hidden"
        >
          <NuxtImg
            :src="getImageSrc(editor.previewData.value.featured_image)"
            :alt="editor.previewData.value.title || 'Featured image'"
            class="size-full rounded-xl object-cover"
            loading="lazy"
            sizes="100vw lg:1024px"
            width="1000"
            height="auto"
            format="webp"
          />
        </div>

        <!-- Content -->
        <div
          class="format-html prose-img:rounded-xl prose-headings:scroll-mt-[calc(var(--navbar-height-mobile)+var(--scroll-offset,2.5rem))] mx-auto mt-6 overflow-x-hidden [--scroll-offset:2.5rem] lg:mt-8"
        >
          <article v-if="sanitizedContent" v-html="sanitizedContent"></article>

          <!-- No Content Message -->
          <div v-else class="text-muted-foreground py-12 text-center">
            <Icon name="hugeicons:file-02" class="mx-auto mb-3 size-12 opacity-50" />
            <p>No content yet. Start writing in the Editor tab.</p>
          </div>

          <!-- Tags at Bottom -->
          <div v-if="previewTags?.length" class="mt-8 flex items-start gap-x-3 lg:mt-10">
            <Icon name="hugeicons:tag-01" class="mt-2.5 size-5 shrink-0" />
            <div class="flex flex-wrap gap-x-2 gap-y-3">
              <span
                v-for="(tag, index) in previewTags"
                :key="index"
                class="border-border rounded-full border px-3 py-2 text-sm capitalize"
              >
                {{ tag }}
              </span>
            </div>
          </div>
        </div>
      </main>
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
const client = useSanctumClient();
const { $dayjs } = useNuxtApp();

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

// Preview computed properties
const previewTags = computed(() => editor.previewData.value.tags || []);

const previewAuthors = computed(() => {
  const formAuthors = editor.previewData.value.authors || [];
  if (!formAuthors.length) return [];

  return formAuthors
    .filter((a: any) => a.user_id)
    .map((a: any) => {
      const user = editor.availableUsers.value.find((u: any) => u.id === a.user_id);
      return user || { id: a.user_id, name: "Unknown Author" };
    });
});

const readingTime = computed(() => {
  const content = editor.previewData.value.content || "";
  const text = content.replace(/<[^>]*>/g, ""); // Strip HTML tags
  const wordCount = text.split(/\s+/).filter((word: string) => word.length > 0).length;
  const wordsPerMinute = 200;
  return Math.max(1, Math.ceil(wordCount / wordsPerMinute));
});

const formattedPublishedAt = computed(() => {
  const publishedAt = editor.previewData.value.published_at;
  if (!publishedAt) return "";
  return $dayjs(publishedAt).format("MMM D, YYYY h:mm A");
});

const relativePublishedAt = computed(() => {
  const publishedAt = editor.previewData.value.published_at;
  if (!publishedAt) return "";
  return $dayjs(publishedAt).fromNow();
});

function getImageSrc(image: any): string {
  if (typeof image === "string") {
    return image;
  }
  return image?.lg || image?.md || image?.original || "";
}

async function clearFeaturedImage() {
  // If there's a temp upload, delete it from server
  const tempFolder = editor.imageFiles.value.featured_image?.[0];
  if (typeof tempFolder === "string" && tempFolder.startsWith("tmp-")) {
    try {
      await client("/api/tmp-upload", {
        method: "DELETE",
        body: tempFolder,
      });
    } catch (err) {
      console.warn("Failed to delete temp file:", err);
    }
  }

  editor.imageFiles.value.featured_image = [];
  editor.deleteFlags.value.featured_image = true;
}
</script>
