<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/50" />
      <DialogContent
        class="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[95vw] max-w-5xl -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-lg border bg-background shadow-lg"
      >
        <div class="flex h-full max-h-[90vh] flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between border-b p-6 pb-4">
            <DialogTitle class="text-2xl font-semibold">Post Preview</DialogTitle>
            <DialogClose
              class="rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100"
            >
              <IconX class="size-5" />
              <span class="sr-only">Close</span>
            </DialogClose>
          </div>

          <!-- Tabs -->
          <Tabs default-value="preview" class="flex flex-1 flex-col overflow-hidden">
            <TabsList class="mx-6 mt-4 grid w-auto grid-cols-2">
              <TabsTrigger value="preview">Preview</TabsTrigger>
              <TabsTrigger v-if="mode === 'edit' && publishedPost" value="comparison">
                Compare with Published
              </TabsTrigger>
            </TabsList>

            <!-- Preview Tab -->
            <TabsContent value="preview" class="flex-1 overflow-y-auto p-6 pt-4">
              <article class="mx-auto max-w-3xl">
                <!-- Featured Image -->
                <div
                  v-if="previewData.featured_image"
                  class="mb-8 aspect-video w-full overflow-hidden rounded-lg"
                >
                  <img
                    :src="
                      typeof previewData.featured_image === 'string'
                        ? previewData.featured_image
                        : previewData.featured_image?.lg || previewData.featured_image?.original
                    "
                    :alt="previewData.title"
                    class="size-full object-cover"
                  />
                </div>

                <!-- Post Header -->
                <header class="mb-8 space-y-4">
                  <!-- Title -->
                  <h1 class="text-4xl font-bold tracking-tight">
                    {{ previewData.title || 'Untitled Post' }}
                  </h1>

                  <!-- Excerpt -->
                  <p v-if="previewData.excerpt" class="text-muted-foreground text-lg">
                    {{ previewData.excerpt }}
                  </p>

                  <!-- Meta -->
                  <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <!-- Authors -->
                    <div v-if="previewData.authors && previewData.authors.length > 0" class="flex items-center gap-2">
                      <Icon name="lucide:user" class="size-4" />
                      <span>
                        {{
                          previewData.authors.map((a) => a.name || `User ${a.user_id}`).join(', ')
                        }}
                      </span>
                    </div>

                    <!-- Status Badge -->
                    <span
                      class="rounded-full px-3 py-1 text-xs font-medium"
                      :class="getStatusClass(previewData.status)"
                    >
                      {{ previewData.status || 'draft' }}
                    </span>

                    <!-- Visibility Badge -->
                    <span
                      class="rounded-full px-3 py-1 text-xs font-medium"
                      :class="getVisibilityClass(previewData.visibility)"
                    >
                      {{ previewData.visibility || 'public' }}
                    </span>

                    <!-- Featured Badge -->
                    <span
                      v-if="previewData.featured"
                      class="rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                    >
                      Featured
                    </span>
                  </div>

                  <!-- Tags -->
                  <div v-if="previewData.tags && previewData.tags.length > 0" class="flex flex-wrap gap-2">
                    <span
                      v-for="tag in previewData.tags"
                      :key="tag"
                      class="inline-flex items-center gap-1 rounded-md bg-muted px-2.5 py-1 text-xs font-medium"
                    >
                      <Icon name="lucide:tag" class="size-3" />
                      {{ tag }}
                    </span>
                  </div>
                </header>

                <!-- Post Content -->
                <div
                  class="prose prose-lg dark:prose-invert max-w-none"
                  v-html="previewData.content || '<p class=\'text-muted-foreground\'>No content yet...</p>'"
                ></div>

                <!-- SEO Preview -->
                <div class="mt-12 space-y-4 rounded-lg border bg-muted/50 p-6">
                  <h3 class="text-lg font-semibold">SEO Preview</h3>
                  <div class="space-y-2">
                    <div class="text-sm">
                      <span class="font-medium">Meta Title:</span>
                      <span class="text-muted-foreground ml-2">
                        {{ previewData.meta_title || previewData.title || 'Untitled Post' }}
                      </span>
                    </div>
                    <div class="text-sm">
                      <span class="font-medium">Meta Description:</span>
                      <p class="text-muted-foreground mt-1">
                        {{ previewData.meta_description || previewData.excerpt || 'No description' }}
                      </p>
                    </div>
                  </div>
                </div>
              </article>
            </TabsContent>

            <!-- Comparison Tab (only for edit mode) -->
            <TabsContent
              v-if="mode === 'edit' && publishedPost"
              value="comparison"
              class="flex-1 overflow-y-auto p-6 pt-4"
            >
              <div class="grid gap-6 md:grid-cols-2">
                <!-- Published Version -->
                <div class="space-y-3">
                  <h3 class="text-lg font-semibold text-green-600 dark:text-green-500">
                    📝 Published Version
                  </h3>
                  <div class="space-y-4 rounded-lg border bg-muted/50 p-4">
                    <div>
                      <p class="text-xs font-medium uppercase text-muted-foreground">Title</p>
                      <p class="mt-1 text-sm font-medium">{{ publishedPost.title }}</p>
                    </div>

                    <div v-if="publishedPost.excerpt">
                      <p class="text-xs font-medium uppercase text-muted-foreground">Excerpt</p>
                      <p class="mt-1 text-sm">{{ publishedPost.excerpt }}</p>
                    </div>

                    <div>
                      <p class="text-xs font-medium uppercase text-muted-foreground">Content</p>
                      <div
                        class="prose prose-sm dark:prose-invert mt-1 max-h-96 overflow-y-auto"
                        v-html="publishedPost.content"
                      ></div>
                    </div>
                  </div>
                </div>

                <!-- Draft Version -->
                <div class="space-y-3">
                  <h3 class="text-lg font-semibold text-blue-600 dark:text-blue-500">
                    ✏️ Your Draft
                  </h3>
                  <div class="space-y-4 rounded-lg border bg-muted/50 p-4">
                    <div>
                      <p class="text-xs font-medium uppercase text-muted-foreground">Title</p>
                      <p
                        class="mt-1 text-sm font-medium"
                        :class="
                          publishedPost.title !== previewData.title ? 'bg-blue-100 dark:bg-blue-950' : ''
                        "
                      >
                        {{ previewData.title || 'Untitled' }}
                      </p>
                    </div>

                    <div v-if="previewData.excerpt">
                      <p class="text-xs font-medium uppercase text-muted-foreground">Excerpt</p>
                      <p
                        class="mt-1 text-sm"
                        :class="
                          publishedPost.excerpt !== previewData.excerpt
                            ? 'bg-blue-100 dark:bg-blue-950'
                            : ''
                        "
                      >
                        {{ previewData.excerpt }}
                      </p>
                    </div>

                    <div>
                      <p class="text-xs font-medium uppercase text-muted-foreground">Content</p>
                      <div
                        class="prose prose-sm dark:prose-invert mt-1 max-h-96 overflow-y-auto"
                        :class="
                          publishedPost.content !== previewData.content
                            ? 'bg-blue-100 dark:bg-blue-950'
                            : ''
                        "
                        v-html="previewData.content || '<p>No content</p>'"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>
          </Tabs>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  previewData: {
    type: Object,
    required: true,
  },
  publishedPost: {
    type: Object,
    default: null,
  },
  mode: {
    type: String,
    default: 'create', // 'create' or 'edit'
  },
})

const emit = defineEmits(['update:open'])

const isOpen = ref(props.open)

watch(
  () => props.open,
  (newValue) => {
    isOpen.value = newValue
  }
)

watch(isOpen, (newValue) => {
  emit('update:open', newValue)
})

function getStatusClass(status) {
  const classes = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    published: 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200',
    scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200',
    archived: 'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-200',
  }
  return classes[status] || classes.draft
}

function getVisibilityClass(visibility) {
  const classes = {
    public: 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200',
    private: 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200',
    members_only: 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200',
  }
  return classes[visibility] || classes.public
}
</script>
