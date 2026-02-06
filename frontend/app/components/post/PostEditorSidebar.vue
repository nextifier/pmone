<template>
  <Sidebar v-bind="props">
    <SidebarContent class="relative p-0">
      <ScrollArea class="h-screen">
        <div class="space-y-6 px-4 py-14">
          <!-- Post URL Section -->
          <div class="space-y-2">
            <!-- Slug Input -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label for="slug" class="text-xs">Post URL</Label>
                <!-- <span
                  v-if="!editor.slugManuallyEdited.value"
                  class="text-muted-foreground flex items-center gap-1 text-xs"
                >
                  <Icon name="hugeicons:link-01" class="size-3" />
                  Auto-sync
                </span>
                <button
                  v-else
                  type="button"
                  @click="resetSlugSync"
                  class="text-primary text-xs hover:underline"
                >
                  Reset sync
                </button> -->
              </div>
              <Input
                id="slug"
                v-model="editor.form.slug"
                type="text"
                placeholder="post-url-slug"
                class="text-sm"
              />
              <div
                v-if="editor.slugChecking.value"
                class="text-muted-foreground flex items-center gap-1.5 text-xs"
              >
                <Spinner class="size-3" />
                Checking...
              </div>
              <div
                v-else-if="editor.slugAvailable.value === false"
                class="text-destructive flex items-center gap-1.5 text-xs"
              >
                <Icon name="lucide:x-circle" class="size-3" />
                Slug is taken
              </div>
              <div
                v-else-if="editor.slugAvailable.value === true"
                class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400"
              >
                <Icon name="lucide:check-circle" class="size-3" />
                Available
              </div>
            </div>

            <!-- URL Preview -->
            <div
              v-if="postUrl"
              class="bg-muted/50 relative flex h-9 items-center gap-2 rounded-lg border px-3 py-1"
            >
              <div class="text-muted-foreground min-w-0 grow truncate text-xs">
                {{ postUrl }}
              </div>
              <ButtonCopy :text="postUrl" />
            </div>
          </div>

          <!-- Publish Date (only if published_at exists) -->
          <div v-if="editor.form.published_at" class="space-y-2">
            <Label class="text-xs">Publish Date</Label>
            <DateTimePicker
              v-model="publishDateTime"
              :disabled="editor.form.status === 'draft'"
              placeholder="Not scheduled"
            />
            <p class="text-muted-foreground text-xs">
              {{
                editor.form.status === "draft" ? "Set when publishing" : "When this post goes live"
              }}
            </p>
          </div>

          <!-- Tags -->
          <div class="space-y-2">
            <Label class="text-xs">Tags</Label>
            <TagsInput v-model="editor.form.tags" class="text-sm">
              <TagsInputItem v-for="tag in editor.form.tags" :key="tag" :value="tag">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="Add tag..." />
            </TagsInput>
            <p class="text-muted-foreground text-xs">Press Enter to add a tag</p>
          </div>

          <!-- Excerpt -->
          <div class="space-y-2">
            <Label class="text-xs">Excerpt</Label>
            <Textarea
              v-model="editor.form.excerpt"
              placeholder="Brief description of the post..."
              maxlength="500"
              class="min-h-[80px] resize-none text-sm"
            />
            <p class="text-muted-foreground text-xs">Max 500 characters</p>
          </div>

          <!-- Feature Toggle -->
          <div class="flex items-start justify-between">
            <div class="space-y-1">
              <Label class="text-sm">Feature this post</Label>
              <p class="text-muted-foreground text-xs">Show in featured section</p>
            </div>
            <Switch v-model="editor.form.featured" />
          </div>

          <!-- Admin-only Settings -->
          <template v-if="isAdminOrMaster">
            <!-- Visibility -->
            <div class="space-y-2">
              <Label class="text-xs">Post Access</Label>
              <Select v-model="editor.form.visibility">
                <SelectTrigger class="w-full text-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="public">
                    <div class="flex items-center gap-2">
                      <Icon name="hugeicons:global" class="size-4" />
                      Public
                    </div>
                  </SelectItem>
                  <SelectItem value="private">
                    <div class="flex items-center gap-2">
                      <Icon name="hugeicons:lock" class="size-4" />
                      Private
                    </div>
                  </SelectItem>
                  <SelectItem value="members_only">
                    <div class="flex items-center gap-2">
                      <Icon name="hugeicons:user-group" class="size-4" />
                      Members Only
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Authors -->
            <div class="space-y-2">
              <Label class="text-xs">Authors</Label>

              <!-- Authors List -->
              <div v-if="editor.form.authors.length > 0" class="space-y-2">
                <div
                  v-for="(author, index) in editor.form.authors"
                  :key="index"
                  class="border-border flex items-center gap-2 rounded-lg border p-2"
                >
                  <Select v-model="author.user_id" class="grow">
                    <SelectTrigger class="h-8 w-full text-xs">
                      <SelectValue placeholder="Select author..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="user in editor.getAvailableUsersForRow(index)"
                        :key="user.id"
                        :value="user.id"
                      >
                        {{ user.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <div class="flex shrink-0 gap-0.5">
                    <button
                      type="button"
                      @click="editor.moveAuthorUp(index)"
                      :disabled="index === 0"
                      class="hover:bg-muted rounded p-1 transition disabled:opacity-30"
                    >
                      <Icon name="lucide:chevron-up" class="size-3" />
                    </button>
                    <button
                      type="button"
                      @click="editor.moveAuthorDown(index)"
                      :disabled="index === editor.form.authors.length - 1"
                      class="hover:bg-muted rounded p-1 transition disabled:opacity-30"
                    >
                      <Icon name="lucide:chevron-down" class="size-3" />
                    </button>
                    <button
                      type="button"
                      @click="editor.removeAuthor(index)"
                      class="hover:bg-destructive/10 hover:text-destructive rounded p-1 transition"
                    >
                      <Icon name="lucide:x" class="size-3" />
                    </button>
                  </div>
                </div>
              </div>

              <button
                type="button"
                @click="editor.addAuthor"
                class="border-input hover:bg-muted flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed py-2 text-xs font-medium transition"
              >
                <Icon name="lucide:plus" class="size-3" />
                Add Author
              </button>
            </div>
          </template>

          <Separator />

          <!-- SEO Section -->
          <!-- <div class="space-y-4">
            <SidebarGroupLabel
              class="text-muted-foreground px-0 text-xs font-medium tracking-tight"
            >
              SEO Settings
            </SidebarGroupLabel>

            <div class="space-y-6">
              <div class="space-y-2">
                <Label for="meta_title" class="text-xs">Meta Title</Label>
                <Input
                  id="meta_title"
                  v-model="editor.form.meta_title"
                  type="text"
                  placeholder="Auto-generated from title"
                  class="text-sm"
                />
              </div>

              <div class="space-y-2">
                <Label for="meta_description" class="text-xs">Meta Description</Label>
                <Textarea
                  id="meta_description"
                  v-model="editor.form.meta_description"
                  placeholder="Auto-generated from excerpt"
                  class="min-h-[60px] resize-none text-sm"
                />
              </div>

              <div class="space-y-2">
                <Label class="text-xs">OG Image</Label>
                <InputFileImage
                  ref="ogImageInput"
                  v-model="editor.imageFiles.value.og_image"
                  :initial-image="editor.initialData.value?.og_image"
                  v-model:delete-flag="editor.deleteFlags.value.og_image"
                  container-class="relative isolate aspect-video w-full"
                />
                <p class="text-muted-foreground -mt-1 text-xs">
                  Image for social sharing. Recommended: 1200x630px
                </p>
              </div>
            </div>
          </div> -->

          <!-- Delete Section (Edit mode only) -->
          <template v-if="editor.mode.value === 'edit'">
            <Separator />

            <div>
              <SidebarGroupLabel class="text-destructive px-0 text-xs font-medium tracking-tight">
                Danger Zone
              </SidebarGroupLabel>
              <button
                type="button"
                @click="editor.deletePost"
                class="border-destructive text-destructive hover:bg-destructive flex w-full items-center justify-center gap-1 rounded-lg border py-2 text-sm font-medium tracking-tight transition hover:text-white"
              >
                <Icon name="hugeicons:delete-01" class="size-4" />
                Delete Post
              </button>
            </div>
          </template>
        </div>
      </ScrollArea>

      <button
        type="button"
        @click="
          setOpen(false);
          setOpenMobile(false);
        "
        class="text-primary bg-muted hover:bg-border absolute top-3 right-4 flex size-8 items-center justify-center rounded-lg transition active:scale-98"
      >
        <Icon name="hugeicons:cancel-01" class="size-4" />
      </button>
    </SidebarContent>

    <SidebarRail />
  </Sidebar>
</template>

<script setup lang="ts">
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useSidebar } from "@/components/ui/sidebar/utils";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Textarea } from "@/components/ui/textarea";
import { usePostEditorOptional } from "@/composables/usePostEditor";

const props = defineProps({
  collapsible: {
    type: String as PropType<"offcanvas" | "icon" | "none">,
    default: "offcanvas",
  },
});

// Try to get editor context, but don't fail if not available
const editorContext = usePostEditorOptional();

// Provide a fallback for when context is not available
const editor = computed(() => {
  if (editorContext) {
    return editorContext;
  }
  // Return a minimal mock for SSR/initial render
  return {
    form: reactive({
      slug: "",
      tags: [],
      excerpt: "",
      featured: false,
      visibility: "public",
      authors: [],
      meta_title: "",
      meta_description: "",
      status: "draft",
      published_at: null,
    }),
    mode: ref("create"),
    initialData: ref(null),
    imageFiles: ref({ featured_image: [], og_image: [] }),
    deleteFlags: ref({ featured_image: false, og_image: false }),
    slugManuallyEdited: ref(false),
    slugChecking: ref(false),
    slugAvailable: ref(null),
    getAvailableUsersForRow: () => [],
    addAuthor: () => {},
    removeAuthor: () => {},
    moveAuthorUp: () => {},
    moveAuthorDown: () => {},
    deletePost: () => {},
  };
});

const { hasRole } = usePermission();
const { setOpen, setOpenMobile } = useSidebar();

const ogImageInput = ref<any>(null);

// Register OG image ref with parent
const parent = getCurrentInstance()?.parent;
onMounted(() => {
  if (parent?.exposed?.setOgImageRef) {
    parent.exposed.setOgImageRef(ogImageInput.value);
  }
});

const isAdminOrMaster = computed(() => hasRole("admin") || hasRole("master"));

const postUrl = computed(() => {
  if (!editor.value.form.slug) return "";
  const { siteUrl } = useRuntimeConfig().public;
  return `${siteUrl}/news/${editor.value.form.slug}`;
});

const publishDateTime = computed({
  get: () => {
    if (!editor.value.form.published_at) return null;
    return new Date(editor.value.form.published_at);
  },
  set: (value: Date | null) => {
    editor.value.form.published_at = value ? value.toISOString().slice(0, 16) : null;
  },
});

function resetSlugSync() {
  if (editorContext) {
    editorContext.slugManuallyEdited.value = false;
  }
}
</script>
