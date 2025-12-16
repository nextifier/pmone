<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/50" />
      <DialogContent
        class="bg-background fixed top-1/2 left-1/2 z-50 max-h-[90vh] w-[95vw] max-w-6xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border p-6 shadow-lg"
      >
        <div class="space-y-6">
          <!-- Header -->
          <div class="flex items-center justify-between">
            <DialogTitle class="text-2xl font-semibold">Preview Changes</DialogTitle>
            <DialogClose
              class="ring-offset-background rounded-sm opacity-70 transition-opacity hover:opacity-100"
            >
              <Icon name="hugeicons:cancel-01" class="size-5" />
              <span class="sr-only">Close</span>
            </DialogClose>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="py-12 text-center">
            <Spinner class="mx-auto" />
            <p class="text-muted-foreground mt-4">Loading preview...</p>
          </div>

          <!-- Preview Content -->
          <div v-else-if="previewData" class="space-y-6">
            <!-- Has Changes Indicator -->
            <div
              v-if="previewData.has_changes"
              class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950"
            >
              <div class="flex items-center gap-2">
                <Icon
                  name="hugeicons:alert-circle"
                  class="size-5 text-yellow-600 dark:text-yellow-500"
                />
                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                  You have unsaved changes
                </p>
              </div>
            </div>

            <div
              v-else
              class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950"
            >
              <div class="flex items-center gap-2">
                <IconCheck class="size-5 text-green-600 dark:text-green-500" />
                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                  No changes detected
                </p>
              </div>
            </div>

            <!-- Side-by-side Comparison -->
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Published Version -->
              <div class="space-y-3">
                <h3 class="text-lg font-semibold text-green-600 dark:text-green-500">
                  📝 Published Version
                </h3>
                <div class="bg-muted/50 space-y-4 rounded-lg border p-4">
                  <div>
                    <p class="text-muted-foreground text-xs font-medium uppercase">Title</p>
                    <p class="mt-1 text-sm font-medium">{{ previewData.published.title }}</p>
                  </div>

                  <div v-if="previewData.published.excerpt">
                    <p class="text-muted-foreground text-xs font-medium uppercase">Excerpt</p>
                    <p class="mt-1 text-sm">{{ previewData.published.excerpt }}</p>
                  </div>

                  <div>
                    <p class="text-muted-foreground text-xs font-medium uppercase">Content</p>
                    <div
                      class="prose prose-sm dark:prose-invert mt-1 max-h-96 overflow-y-auto"
                      v-html="previewData.published.content"
                    ></div>
                  </div>

                  <div class="text-muted-foreground flex gap-4 text-xs">
                    <div>
                      <span class="font-medium">Status:</span>
                      <span
                        class="ml-1 rounded-full px-2 py-0.5"
                        :class="getStatusColor(previewData.published.status)"
                      >
                        {{ previewData.published.status }}
                      </span>
                    </div>
                    <div>
                      <span class="font-medium">Visibility:</span>
                      {{ previewData.published.visibility }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Autosave Version -->
              <div class="space-y-3">
                <h3 class="text-lg font-semibold text-blue-600 dark:text-blue-500">
                  ✏️ Your Draft
                </h3>
                <div class="bg-muted/50 space-y-4 rounded-lg border p-4">
                  <div>
                    <p class="text-muted-foreground text-xs font-medium uppercase">Title</p>
                    <p
                      class="mt-1 text-sm font-medium"
                      :class="
                        previewData.published.title !== previewData.autosave.title
                          ? 'bg-blue-100 dark:bg-blue-950'
                          : ''
                      "
                    >
                      {{ previewData.autosave.title }}
                    </p>
                  </div>

                  <div v-if="previewData.autosave.excerpt">
                    <p class="text-muted-foreground text-xs font-medium uppercase">Excerpt</p>
                    <p
                      class="mt-1 text-sm"
                      :class="
                        previewData.published.excerpt !== previewData.autosave.excerpt
                          ? 'bg-blue-100 dark:bg-blue-950'
                          : ''
                      "
                    >
                      {{ previewData.autosave.excerpt }}
                    </p>
                  </div>

                  <div>
                    <p class="text-muted-foreground text-xs font-medium uppercase">Content</p>
                    <div
                      class="prose prose-sm dark:prose-invert mt-1 max-h-96 overflow-y-auto"
                      :class="
                        previewData.published.content !== previewData.autosave.content
                          ? 'bg-blue-100 dark:bg-blue-950'
                          : ''
                      "
                      v-html="previewData.autosave.content"
                    ></div>
                  </div>

                  <div class="text-muted-foreground flex gap-4 text-xs">
                    <div>
                      <span class="font-medium">Status:</span>
                      <span
                        class="ml-1 rounded-full px-2 py-0.5"
                        :class="getStatusColor(previewData.autosave.status)"
                      >
                        {{ previewData.autosave.status }}
                      </span>
                    </div>
                    <div>
                      <span class="font-medium">Visibility:</span>
                      {{ previewData.autosave.visibility }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 border-t pt-4">
            <button
              @click="close"
              class="hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  postSlug: {
    type: String,
    required: true,
  },
  onPreviewLoad: {
    type: Function,
    default: null,
  },
});

const emit = defineEmits(["update:open", "close"]);

const isOpen = ref(props.open);
const loading = ref(false);
const previewData = ref(null);

watch(
  () => props.open,
  (newValue) => {
    isOpen.value = newValue;
    if (newValue) {
      loadPreview();
    }
  }
);

watch(isOpen, (newValue) => {
  emit("update:open", newValue);
  if (!newValue) {
    emit("close");
  }
});

async function loadPreview() {
  if (!props.postSlug) return;

  loading.value = true;
  try {
    if (props.onPreviewLoad) {
      previewData.value = await props.onPreviewLoad(props.postSlug);
    }
  } catch (error) {
    console.error("Failed to load preview:", error);
  } finally {
    loading.value = false;
  }
}

function close() {
  isOpen.value = false;
}

function getStatusColor(status) {
  const colors = {
    draft: "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200",
    published: "bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200",
    scheduled: "bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200",
    archived: "bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-200",
  };
  return colors[status] || colors.draft;
}
</script>
