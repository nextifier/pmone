<template>
  <TabsRoot v-model="activeTab" class="relative contents">
    <PostEditorSidebar
      side="right"
      variant="sidebar"
      class="select-none"
      :style="{
        '--sidebar-width': '400px',
        '--sidebar-width-mobile': '320px',
      }"
    />

    <SidebarInset class="relative mx-auto min-h-screen max-w-480">
      <PostEditorHeader />
      <main class="grow overflow-x-hidden px-4">
        <slot />
      </main>
    </SidebarInset>

    <!-- Tabs Trigger - Mobile only (fixed to viewport) -->
    <div
      v-if="isMobile"
      class="fixed bottom-8 left-1/2 z-50 -translate-x-1/2"
    >
      <PostTabsTrigger />
    </div>
  </TabsRoot>

  <!-- Restore Draft Dialog -->
  <DialogResponsive v-model:open="showRestoreDialog" dialog-max-width="450px">
    <div class="px-4 pb-8 md:pt-8">
      <div class="flex items-start gap-x-3">
        <div class="bg-info/15 flex size-10 shrink-0 items-center justify-center rounded-xl">
          <Icon name="hugeicons:unarchive-03" class="text-info-foreground size-5" />
        </div>
        <div class="grow">
          <h3 class="text-primary text-lg font-semibold tracking-tighter">Restore Draft?</h3>
          <p
            class="text-muted-foreground mt-1.5 text-sm leading-relaxed tracking-tight text-pretty"
          >
            You have unsaved changes from a previous session. Would you like to restore them or
            start fresh?
          </p>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button
          type="button"
          @click="handleDiscardRestore"
          class="bg-muted hover:bg-border rounded-lg px-3 py-2 text-sm font-medium tracking-tight transition"
        >
          Discard
        </button>
        <button
          type="button"
          @click="handleRestoreChanges"
          class="bg-info hover:bg-info/90 flex items-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-medium tracking-tight text-white transition disabled:opacity-50"
        >
          Restore Draft
        </button>
      </div>
    </div>
  </DialogResponsive>

  <!-- Delete Confirmation Dialog -->
  <DialogResponsive v-model:open="showDeleteDialog" dialog-max-width="450px">
    <div class="px-4 pb-8 md:pt-8">
      <div class="flex items-start gap-x-3">
        <div class="bg-destructive/15 flex size-10 shrink-0 items-center justify-center rounded-xl">
          <Icon name="hugeicons:delete-01" class="text-destructive-foreground size-5" />
        </div>
        <div class="grow">
          <h3 class="text-primary text-lg font-semibold tracking-tighter">Delete Post?</h3>
          <p
            class="text-muted-foreground mt-1.5 text-sm leading-relaxed tracking-tight text-pretty"
          >
            Are you sure you want to delete this post? This action can't be undone.
          </p>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button
          type="button"
          @click="showDeleteDialog = false"
          class="bg-muted hover:bg-border rounded-lg px-3 py-2 text-sm font-medium tracking-tight transition"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="confirmDelete"
          :disabled="deleteLoading"
          class="bg-destructive hover:bg-destructive/90 flex items-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-medium tracking-tight text-white transition disabled:opacity-50"
        >
          <Spinner v-if="deleteLoading" class="size-4" />
          Delete Post
        </button>
      </div>
    </div>
  </DialogResponsive>
</template>

<script setup lang="ts">
import { useSidebar } from "@/components/ui/sidebar/utils";
import { providePostEditor, type PostForm } from "@/composables/usePostEditor";
import { TabsRoot } from "reka-ui";
import { toast } from "vue-sonner";

const { isMobile } = useSidebar();

const props = defineProps<{
  mode: "create" | "edit";
  initialData?: any;
  postSlug?: string;
}>();

const emit = defineEmits<{
  cancel: [];
  success: [data: any];
}>();

const FILE_STATUS = {
  PROCESSING: 3,
};

const client = useSanctumClient();

// Refs for file inputs (will be set by PostEditorContent)
const featuredImageInputRef = ref<any>(null);
const ogImageInputRef = ref<any>(null);

// Core state
const mode = ref(props.mode);
const initialData = ref(props.initialData || null);
const postId = ref<number | null>(props.initialData?.id || null);
const postSlug = ref<string | null>(props.postSlug || null);

// Form state
const form = reactive<PostForm>({
  title: "",
  slug: "",
  excerpt: "",
  content: "",
  status: "draft",
  visibility: "public",
  published_at: null,
  featured: false,
  meta_title: "",
  meta_description: "",
  featured_image_caption: "",
  tags: [],
  authors: [],
});

const imageFiles = ref({
  featured_image: [] as any[],
  og_image: [] as any[],
});

const deleteFlags = ref({
  featured_image: false,
  og_image: false,
});

const loading = ref(false);
const deleteLoading = ref(false);
const errors = ref<Record<string, string[]>>({});
const availableUsers = ref<Array<{ id: number; name: string; email: string }>>([]);
const slugChecking = ref(false);
const slugAvailable = ref<boolean | null>(null);
const slugManuallyEdited = ref(false);
const showRestoreDialog = ref(false);
const showDeleteDialog = ref(false);
const pendingRestoreData = ref<any>(null);
const currentPreviewImageUrl = ref<string | null>(null);
const activeTab = ref<"editor" | "preview">("editor");

let slugCheckTimeout: ReturnType<typeof setTimeout> | null = null;

// Autosave preference storage key
const AUTOSAVE_PREF_KEY = "post-autosave-preference";

function loadAutosavePreference(): boolean {
  if (import.meta.client) {
    const stored = localStorage.getItem(AUTOSAVE_PREF_KEY);
    return stored === null ? true : stored === "true";
  }
  return true;
}

function saveAutosavePreference(enabled: boolean) {
  if (import.meta.client) {
    localStorage.setItem(AUTOSAVE_PREF_KEY, String(enabled));
  }
}

const autosaveEnabled = ref(false);
const userAutosavePreference = ref(loadAutosavePreference());

const autosave = useAutosave(toRef(form), {
  postId: postId,
  enabled: autosaveEnabled,
  debounceTime: 2000,
  localStorageKey: computed(() =>
    postId.value ? `post-autosave-${postId.value}` : "post-autosave-new"
  ),
});

watch(autosaveEnabled, (newValue) => {
  if (!showRestoreDialog.value) {
    saveAutosavePreference(newValue);
    userAutosavePreference.value = newValue;
  }
});

// Slug auto-sync with title
watch(
  () => form.title,
  (newTitle) => {
    if (!slugManuallyEdited.value && newTitle) {
      form.slug = slugify(newTitle);
    }
  }
);

// Watch slug for manual edits
watch(
  () => form.slug,
  (newSlug, oldSlug) => {
    // If slug is edited and doesn't match slugified title, mark as manually edited
    if (oldSlug !== undefined && newSlug !== slugify(form.title)) {
      slugManuallyEdited.value = true;
    }

    // Slug availability check
    if (!newSlug || newSlug.trim() === "") {
      slugAvailable.value = null;
      slugChecking.value = false;
      if (slugCheckTimeout) clearTimeout(slugCheckTimeout);
      return;
    }

    if (props.mode === "edit" && props.initialData?.slug === newSlug) {
      slugAvailable.value = null;
      slugChecking.value = false;
      if (slugCheckTimeout) clearTimeout(slugCheckTimeout);
      return;
    }

    if (slugCheckTimeout) clearTimeout(slugCheckTimeout);
    slugChecking.value = true;
    slugAvailable.value = null;

    slugCheckTimeout = setTimeout(() => {
      checkSlugAvailability(newSlug);
    }, 500);
  }
);

function slugify(text: string): string {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, "")
    .replace(/[\s_-]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

onMounted(async () => {
  await loadUsers();
  if (props.initialData) {
    populateForm();
  }
  await checkAndRestoreAutosave();
});

onBeforeUnmount(() => {
  if (slugCheckTimeout) clearTimeout(slugCheckTimeout);
  if (currentPreviewImageUrl.value) {
    URL.revokeObjectURL(currentPreviewImageUrl.value);
  }
});

async function loadUsers() {
  try {
    const response = await client("/api/posts/eligible-authors");
    availableUsers.value = response.data || [];
  } catch (error) {
    console.error("Failed to load eligible authors:", error);
    availableUsers.value = [];
  }
}

function populateForm() {
  if (!props.initialData) return;

  const data = props.initialData;
  form.title = data.title || "";
  form.slug = data.slug || "";
  form.excerpt = data.excerpt || "";
  form.content = data.content || "";
  form.status = data.status || "draft";
  form.visibility = data.visibility || "public";
  form.featured = data.featured || false;
  form.meta_title = data.meta_title || "";
  form.meta_description = data.meta_description || "";
  form.featured_image_caption = data.featured_image?.caption || "";

  if (data.published_at) {
    const date = new Date(data.published_at);
    form.published_at = date.toISOString().slice(0, 16);
  }

  if (data.tags && Array.isArray(data.tags)) {
    form.tags = data.tags.map((tag: any) => tag.name || tag);
  }

  if (data.authors && Array.isArray(data.authors)) {
    form.authors = data.authors.map((author: any) => ({
      user_id: author.id,
      order: author.order || 0,
    }));
  }

  // Mark slug as manually edited since it was loaded from server
  if (data.slug) {
    slugManuallyEdited.value = true;
  }
}

// Author management
function addAuthor() {
  form.authors.push({
    user_id: null,
    order: form.authors.length,
  });
}

function removeAuthor(index: number) {
  form.authors.splice(index, 1);
  form.authors.forEach((author, idx) => {
    author.order = idx;
  });
}

function moveAuthorUp(index: number) {
  if (index === 0) return;
  const temp = form.authors[index];
  form.authors[index] = form.authors[index - 1];
  form.authors[index - 1] = temp;
  form.authors.forEach((author, idx) => {
    author.order = idx;
  });
}

function moveAuthorDown(index: number) {
  if (index === form.authors.length - 1) return;
  const temp = form.authors[index];
  form.authors[index] = form.authors[index + 1];
  form.authors[index + 1] = temp;
  form.authors.forEach((author, idx) => {
    author.order = idx;
  });
}

function getAvailableUsersForRow(currentIndex: number) {
  const selectedUserIds = form.authors
    .map((author, idx) => (idx !== currentIndex ? author.user_id : null))
    .filter((id) => id !== null);

  return availableUsers.value.filter(
    (user) => !selectedUserIds.includes(user.id) || user.id === form.authors[currentIndex]?.user_id
  );
}

async function checkSlugAvailability(slug: string) {
  try {
    slugChecking.value = true;
    const params = new URLSearchParams({ slug });
    if (props.initialData?.id) {
      params.append("exclude_id", props.initialData.id);
    }
    const response = await client(`/api/posts/check-slug?${params.toString()}`);
    slugAvailable.value = response.available;
  } catch (error) {
    console.error("Failed to check slug availability:", error);
    slugAvailable.value = null;
  } finally {
    slugChecking.value = false;
  }
}

function hasAutosaveChanges(savedData: any): boolean {
  if (!savedData) return false;
  const data = props.initialData;

  if (!data) {
    return !!(savedData.title || savedData.content || savedData.excerpt);
  }

  const fieldsToCompare = ["title", "excerpt", "content", "status", "visibility", "featured"];
  for (const field of fieldsToCompare) {
    const savedValue = savedData[field];
    const initialValue = data[field];
    if (savedValue === undefined) continue;
    if ((savedValue || "") !== (initialValue || "")) {
      return true;
    }
  }

  if (
    (savedData.meta_title || "") !== (data.meta_title || "") ||
    (savedData.meta_description || "") !== (data.meta_description || "")
  ) {
    return true;
  }

  const savedTags = savedData.tags || [];
  const initialTags = (data.tags || []).map((t: any) => t.name || t);
  if (JSON.stringify(savedTags.sort()) !== JSON.stringify(initialTags.sort())) {
    return true;
  }

  return false;
}

async function checkAndRestoreAutosave() {
  try {
    const savedData = await autosave.retrieveAutosave();
    if (savedData && Object.keys(savedData).length > 0 && hasAutosaveChanges(savedData)) {
      pendingRestoreData.value = savedData;
      showRestoreDialog.value = true;
    } else {
      if (savedData) {
        await autosave.discardAutosave();
      }
      autosaveEnabled.value = userAutosavePreference.value;
    }
  } catch (error) {
    console.error("Failed to check autosave:", error);
    autosaveEnabled.value = userAutosavePreference.value;
  }
}

async function handleRestoreChanges() {
  const savedData = pendingRestoreData.value;
  if (savedData) {
    if (savedData.title !== undefined) form.title = savedData.title;
    if (savedData.slug !== undefined) form.slug = savedData.slug;
    if (savedData.excerpt !== undefined) form.excerpt = savedData.excerpt;
    if (savedData.content !== undefined) form.content = savedData.content;
    if (savedData.status) form.status = savedData.status;
    if (savedData.visibility) form.visibility = savedData.visibility;
    if (savedData.meta_title !== undefined) form.meta_title = savedData.meta_title;
    if (savedData.meta_description !== undefined)
      form.meta_description = savedData.meta_description;
    if (savedData.featured_image_caption !== undefined)
      form.featured_image_caption = savedData.featured_image_caption;
    if (savedData.featured !== undefined) form.featured = savedData.featured;
    if (savedData.tags) form.tags = savedData.tags;
    if (savedData.authors) form.authors = savedData.authors;
    if (savedData.published_at) {
      const date = new Date(savedData.published_at);
      form.published_at = date.toISOString().slice(0, 16);
    }
    toast.success("Draft restored successfully");
  }
  showRestoreDialog.value = false;
  pendingRestoreData.value = null;
  autosaveEnabled.value = true;
}

async function handleDiscardRestore() {
  await autosave.discardAutosave();
  showRestoreDialog.value = false;
  pendingRestoreData.value = null;
  setTimeout(() => {
    autosaveEnabled.value = userAutosavePreference.value;
  }, 100);
}

function getPreviewFeaturedImage() {
  if (imageFiles.value.featured_image?.[0]) {
    const imgValue = imageFiles.value.featured_image[0];
    if (typeof imgValue === "string") {
      return imgValue;
    } else if (imgValue instanceof File) {
      if (!currentPreviewImageUrl.value) {
        currentPreviewImageUrl.value = URL.createObjectURL(imgValue);
      }
      return currentPreviewImageUrl.value;
    }
  } else if (props.initialData?.featured_image && !deleteFlags.value.featured_image) {
    return props.initialData.featured_image;
  }
  return null;
}

const previewData = computed(() => ({
  title: form.title || "Untitled Post",
  excerpt: form.excerpt || "",
  content: form.content || "",
  status: form.status || "draft",
  visibility: form.visibility || "public",
  featured: form.featured || false,
  meta_title: form.meta_title || form.title || "",
  meta_description: form.meta_description || form.excerpt || "",
  featured_image: getPreviewFeaturedImage(),
  featured_image_caption: form.featured_image_caption || "",
  tags: form.tags || [],
  authors: form.authors || [],
  published_at: form.published_at || null,
}));

watch(
  () => imageFiles.value.featured_image?.[0],
  (newValue, oldValue) => {
    if (oldValue instanceof File && currentPreviewImageUrl.value) {
      URL.revokeObjectURL(currentPreviewImageUrl.value);
      currentPreviewImageUrl.value = null;
    }
  }
);

function hasFilesUploading(): boolean {
  return [featuredImageInputRef, ogImageInputRef].some((ref) =>
    ref.value?.pond?.getFiles().some((file: any) => file.status === FILE_STATUS.PROCESSING)
  );
}

async function handleSubmit() {
  if (hasFilesUploading()) {
    toast.error("Please wait until all files are uploaded");
    return;
  }

  loading.value = true;
  errors.value = {};

  try {
    const payload = buildPayload();

    let response;
    if (props.mode === "edit" && props.postSlug) {
      response = await client(`/api/posts/${props.postSlug}`, {
        method: "PUT",
        body: payload,
      });
      toast.success("Post updated successfully!");
    } else {
      response = await client("/api/posts", {
        method: "POST",
        body: payload,
      });
      toast.success("Post created successfully!");
    }

    await autosave.discardAutosave();
    emit("success", response.data);
  } catch (error: any) {
    console.error("Failed to save post:", error);
    if (error?.data?.errors) {
      errors.value = error.data.errors;
    }
    toast.error(error?.data?.message || "Failed to save post. Please try again.");
  } finally {
    loading.value = false;
  }
}

async function saveDraft() {
  form.status = "draft";
  await handleSubmit();
}

async function publish(scheduledAt?: Date) {
  if (scheduledAt) {
    form.status = "scheduled";
    form.published_at = scheduledAt.toISOString().slice(0, 16);
  } else {
    form.status = "published";
    form.published_at = new Date().toISOString().slice(0, 16);
  }
  await handleSubmit();
}

async function unpublish() {
  form.status = "draft";
  form.published_at = null;
  await handleSubmit();
}

async function deletePost() {
  showDeleteDialog.value = true;
}

async function confirmDelete() {
  if (!props.postSlug) return;

  deleteLoading.value = true;
  try {
    await client(`/api/posts/${props.postSlug}`, {
      method: "DELETE",
    });
    toast.success("Post deleted successfully!");
    await autosave.discardAutosave();
    showDeleteDialog.value = false;
    emit("success", null);
  } catch (error: any) {
    console.error("Failed to delete post:", error);
    toast.error(error?.data?.message || "Failed to delete post. Please try again.");
  } finally {
    deleteLoading.value = false;
  }
}

function buildPayload() {
  const payload: any = {
    title: form.title,
    slug: form.slug || null,
    excerpt: form.excerpt,
    content: form.content,
    content_format: "html",
    status: form.status,
    visibility: form.visibility,
    featured: form.featured,
    meta_title: form.meta_title || null,
    meta_description: form.meta_description || null,
    published_at: form.published_at ? new Date(form.published_at).toISOString() : null,
    tags: form.tags,
  };

  if (form.authors.length > 0) {
    payload.authors = form.authors
      .filter((author) => author.user_id)
      .map((author, index) => ({
        user_id: author.user_id,
        order: index,
      }));
  }

  const featuredValue = imageFiles.value.featured_image?.[0];
  if (typeof featuredValue === "string" && featuredValue.startsWith("tmp-")) {
    payload.tmp_featured_image = featuredValue;
  } else if (deleteFlags.value.featured_image && !featuredValue) {
    payload.delete_featured_image = true;
  }

  if (form.featured_image_caption) {
    payload.featured_image_caption = form.featured_image_caption;
  }

  const ogImageValue = imageFiles.value.og_image?.[0];
  if (typeof ogImageValue === "string" && ogImageValue.startsWith("tmp-")) {
    payload.tmp_og_image = ogImageValue;
  } else if (deleteFlags.value.og_image && !ogImageValue) {
    payload.delete_og_image = true;
  }

  return payload;
}

// Computed for action visibility
const canPublish = computed(() => {
  return (
    form.title &&
    form.content &&
    (props.mode === "create" || (props.mode === "edit" && form.status === "draft"))
  );
});

const canUnpublish = computed(() => {
  return props.mode === "edit" && form.status === "published";
});

const canUpdate = computed(() => {
  return props.mode === "edit" && form.title && form.content;
});

const canDelete = computed(() => {
  return props.mode === "edit";
});

// Provide context
providePostEditor({
  mode,
  initialData,
  form,
  errors,
  loading,
  postId,
  postSlug,
  imageFiles,
  deleteFlags,
  autosaveEnabled,
  autosave: {
    isSaving: autosave.isSaving,
    isSaved: autosave.isSaved,
    hasError: autosave.hasError,
    lastSavedAt: autosave.lastSavedAt,
    autosaveStatus: autosave.autosaveStatus,
    localBackup: autosave.localBackup,
    discardAutosave: autosave.discardAutosave,
  },
  slugManuallyEdited,
  slugChecking,
  slugAvailable,
  availableUsers,
  activeTab,
  showRestoreDialog,
  handleSubmit,
  saveDraft,
  publish,
  unpublish,
  deletePost,
  getAvailableUsersForRow,
  addAuthor,
  removeAuthor,
  moveAuthorUp,
  moveAuthorDown,
  canPublish,
  canUnpublish,
  canUpdate,
  canDelete,
  previewData,
});

// Expose refs for child components to set
defineExpose({
  featuredImageInputRef,
  ogImageInputRef,
  setFeaturedImageRef: (ref: any) => {
    featuredImageInputRef.value = ref;
  },
  setOgImageRef: (ref: any) => {
    ogImageInputRef.value = ref;
  },
});

// Keyboard shortcuts
defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
