<template>
  <div
    ref="rootEl"
    class="tiptap-editor cn-input flex h-auto w-full min-w-0 flex-col items-stretch p-0"
    :style="{ '--editor-min-h': minHeight }"
  >
    <!-- Toolbar -->
    <div v-if="editor" :class="['editor-toolbar', sticky ? 'editor-toolbar-sticky' : '']">
      <div class="toolbar-group">
        <button
          type="button"
          @click="editor.chain().focus().toggleBold().run()"
          :class="{ 'is-active': editor.isActive('bold') }"
          class="toolbar-button"
          title="Bold (Cmd+B)"
          v-tippy="'Bold (Cmd+B)'"
        >
          <Icon name="hugeicons:text-bold" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleItalic().run()"
          :class="{ 'is-active': editor.isActive('italic') }"
          class="toolbar-button"
          title="Italic (Cmd+I)"
          v-tippy="'Italic (Cmd+I)'"
        >
          <Icon name="hugeicons:text-italic" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleUnderline().run()"
          :class="{ 'is-active': editor.isActive('underline') }"
          class="toolbar-button"
          title="Underline (Cmd+U)"
          v-tippy="'Underline (Cmd+U)'"
        >
          <Icon name="hugeicons:text-underline" class="size-4.5" />
        </button>
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <button
          type="button"
          @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
          :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
          class="toolbar-button"
          title="Heading 2"
          v-tippy="'Heading 2'"
        >
          <Icon name="hugeicons:heading-02" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
          :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
          class="toolbar-button"
          title="Heading 3"
          v-tippy="'Heading 3'"
        >
          <Icon name="hugeicons:heading-03" class="size-4.5" />
        </button>
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <button
          type="button"
          @click="editor.chain().focus().toggleBulletList().run()"
          :class="{ 'is-active': editor.isActive('bulletList') }"
          class="toolbar-button"
          title="Bullet List"
          v-tippy="'Bullet List'"
        >
          <Icon name="hugeicons:left-to-right-list-bullet" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleOrderedList().run()"
          :class="{ 'is-active': editor.isActive('orderedList') }"
          class="toolbar-button"
          title="Ordered List"
          v-tippy="'Ordered List'"
        >
          <Icon name="hugeicons:left-to-right-list-number" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleBlockquote().run()"
          :class="{ 'is-active': editor.isActive('blockquote') }"
          class="toolbar-button"
          title="Blockquote"
          v-tippy="'Blockquote'"
        >
          <Icon name="hugeicons:quote-down" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleCodeBlock().run()"
          :class="{ 'is-active': editor.isActive('codeBlock') }"
          class="toolbar-button"
          title="Code Block"
          v-tippy="'Code Block'"
        >
          <Icon name="hugeicons:source-code" class="size-4.5" />
        </button>
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <Popover v-model:open="linkPopoverOpen">
          <PopoverTrigger as-child>
            <button
              type="button"
              @click="openLinkPopover"
              :class="{ 'is-active': editor.isActive('link') }"
              class="toolbar-button"
              title="Add Link"
              v-tippy="'Add Link'"
            >
              <Icon name="hugeicons:link-01" class="size-4.5" />
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-80 p-3" align="start" :side-offset="8">
            <form @submit.prevent="applyLink" class="flex flex-col gap-y-2">
              <label class="text-sm tracking-tight font-medium">URL</label>
              <input
                ref="linkInputRef"
                v-model="linkUrl"
                type="url"
                placeholder="https://example.com"
                class="cn-input w-full min-w-0 outline-none placeholder:text-muted-foreground"
                @keydown.escape="linkPopoverOpen = false"
              />
              <div class="flex items-center justify-between">
                <Button
                  v-if="linkHasExisting"
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="text-destructive hover:text-destructive/80"
                  @click="removeLink"
                >
                  Remove
                </Button>
                <div v-else />
                <div class="flex items-center gap-x-2">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="linkPopoverOpen = false"
                  >
                    Cancel
                  </Button>
                  <Button type="submit" size="sm"> Apply </Button>
                </div>
              </div>
            </form>
          </PopoverContent>
        </Popover>
        <button
          v-if="allowImages"
          type="button"
          @click="triggerImageUpload"
          class="toolbar-button"
          title="Upload Image"
          v-tippy="'Upload Image'"
        >
          <Icon name="hugeicons:image-01" class="size-4.5" />
        </button>
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <button
          type="button"
          @click="editor.chain().focus().setHorizontalRule().run()"
          class="toolbar-button"
          title="Horizontal Rule"
          v-tippy="'Horizontal Rule'"
        >
          <Icon name="hugeicons:minus-sign" class="size-4.5" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().setHardBreak().run()"
          class="toolbar-button"
          title="Line Break"
          v-tippy="'Line Break'"
        >
          <Icon name="hugeicons:text-wrap" class="size-4.5" />
        </button>
      </div>
    </div>

    <!-- Editor Content -->
    <div class="editor-content-wrapper relative">
      <EditorContent :editor="editor" class="editor-content" />
      <div
        v-if="isUploading"
        class="bg-background/80 absolute inset-0 flex items-center justify-center backdrop-blur-xs"
      >
        <div class="flex items-center gap-x-2 text-sm tracking-tight">
          <Spinner class="size-4" />
          <span>Uploading image...</span>
        </div>
      </div>
    </div>

    <!-- Hidden file input for image upload -->
    <input
      ref="imageInput"
      type="file"
      accept="image/*"
      style="display: none"
      @change="handleImageUpload"
    />

  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import ImageNodeView from "@/components/tiptap/ImageNodeView.vue";
import CodeBlockView from "@/components/tiptap/CodeBlockView.vue";
import { useShiki } from "@/composables/useShiki";
import CodeBlock from "@tiptap/extension-code-block";
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import Placeholder from "@tiptap/extension-placeholder";
import { Plugin, PluginKey } from "@tiptap/pm/state";
import { Decoration, DecorationSet } from "@tiptap/pm/view";
import TextAlign from "@tiptap/extension-text-align";
import StarterKit from "@tiptap/starter-kit";
import { EditorContent, useEditor, VueNodeViewRenderer } from "@tiptap/vue-3";
import { toast } from "vue-sonner";

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  placeholder: {
    type: String,
    default: "Start writing your post...",
  },
  postId: {
    type: Number,
    default: null,
  },
  modelType: {
    type: String,
    default: "App\\Models\\Post",
  },
  collection: {
    type: String,
    default: "content_images",
  },
  sticky: {
    type: Boolean,
    default: true,
  },
  minHeight: {
    type: String,
    default: "350px",
  },
  allowImages: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["update:modelValue"]);

const imageInput = ref(null);
// Image upload uses the authenticated Sanctum client, which only exists in the
// admin app. In public/event contexts (allowImages is false there) the module
// is absent, so guard the auto-import to keep this component portable.
const client = typeof useSanctumClient === "function" ? useSanctumClient() : null;

// Shiki syntax highlighting
const { highlighter } = useShiki();
const shikiPluginKey = new PluginKey("shiki");

function getShikiDecorations(doc, shiki) {
  const decorations = [];

  doc.descendants((node, pos) => {
    if (node.type.name !== "codeBlock") return false;

    const language = node.attrs.language;
    const code = node.textContent;

    if (!code || !language) return false;

    try {
      const { tokens } = shiki.codeToTokens(code, {
        lang: language,
        themes: { light: "github-light", dark: "github-dark" },
      });

      let currentPos = pos + 1;

      for (let i = 0; i < tokens.length; i++) {
        for (const token of tokens[i]) {
          const to = currentPos + token.content.length;
          if (token.htmlStyle) {
            const style = Object.entries(token.htmlStyle)
              .map(([k, v]) => `${k}:${v}`)
              .join(";");
            decorations.push(Decoration.inline(currentPos, to, { style }));
          }
          currentPos = to;
        }
        if (i < tokens.length - 1) currentPos += 1;
      }
    } catch {
      // Language not supported
    }
  });

  return DecorationSet.create(doc, decorations);
}

const CodeBlockShiki = CodeBlock.extend({
  addNodeView() {
    return VueNodeViewRenderer(CodeBlockView);
  },
  addProseMirrorPlugins() {
    return [
      ...(this.parent?.() || []),
      new Plugin({
        key: shikiPluginKey,
        state: {
          init: (_, { doc }) => {
            if (highlighter.value) {
              return getShikiDecorations(doc, highlighter.value);
            }
            return DecorationSet.empty;
          },
          apply: (tr, set, _, newState) => {
            if (
              highlighter.value &&
              (tr.docChanged || tr.getMeta(shikiPluginKey))
            ) {
              return getShikiDecorations(newState.doc, highlighter.value);
            }
            return tr.docChanged ? DecorationSet.empty : set;
          },
        },
        props: {
          decorations(state) {
            return this.getState(state);
          },
        },
      }),
    ];
  },
});

// Track previous images to detect deletions
const previousImages = ref(new Set());

// Custom Image extension with data-caption support and inline caption editing
const CustomImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      "data-caption": {
        default: null,
        parseHTML: (element) => element.getAttribute("data-caption"),
        renderHTML: (attributes) => {
          if (!attributes["data-caption"]) {
            return {};
          }
          return {
            "data-caption": attributes["data-caption"],
          };
        },
      },
    };
  },
  addNodeView() {
    return VueNodeViewRenderer(ImageNodeView);
  },
});

/**
 * Strip every colour-related declaration (text colour, background, border/outline
 * colour, -webkit-text-fill-color, Word's mso-* colours, …) and the legacy
 * color/bgcolor attributes from pasted HTML. Keeps content theme-driven so pastes
 * from Word / Google Docs never bake in colours that break dark mode.
 */
function stripPastedColors(html) {
  if (typeof window === "undefined" || !html) {
    return html;
  }

  const doc = new DOMParser().parseFromString(html, "text/html");

  doc.querySelectorAll("[style]").forEach((el) => {
    const cleaned = (el.getAttribute("style") || "")
      .split(";")
      .filter((declaration) => {
        const property = declaration.split(":")[0]?.trim().toLowerCase();
        return property && !property.includes("color") && property !== "background";
      })
      .join(";")
      .trim();

    if (cleaned) {
      el.setAttribute("style", cleaned);
    } else {
      el.removeAttribute("style");
    }
  });

  doc.querySelectorAll("[color], [bgcolor]").forEach((el) => {
    el.removeAttribute("color");
    el.removeAttribute("bgcolor");
  });

  return doc.body.innerHTML;
}

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      link: false,
      codeBlock: false,
    }),
    CodeBlockShiki,
    CustomImage.configure({
      HTMLAttributes: {
        class: "post-content-image",
      },
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: "post-content-link",
      },
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
    TextAlign.configure({
      types: ["heading", "paragraph"],
    }),
  ],
  editorProps: {
    attributes: {
      class: "prose prose-base focus:outline-none",
    },
    transformPastedHTML: (html) => stripPastedColors(html),
    handlePaste: (view, event) => {
      const items = event.clipboardData?.items;
      if (!items) return false;

      for (const item of items) {
        if (item.type.startsWith("image/")) {
          event.preventDefault();
          const file = item.getAsFile();
          if (file) uploadImageFile(file);
          return true;
        }
      }
      return false;
    },
    handleDrop: (view, event) => {
      const files = event.dataTransfer?.files;
      if (!files?.length) return false;

      const imageFile = Array.from(files).find((f) => f.type.startsWith("image/"));
      if (imageFile) {
        event.preventDefault();
        uploadImageFile(imageFile);
        return true;
      }
      return false;
    },
  },
  onUpdate: ({ editor }) => {
    const newContent = editor.getHTML();
    // Check for deleted temp images and clean them up
    handleImageDeletion(newContent);
    emit("update:modelValue", newContent);
  },
  onCreate: ({ editor }) => {
    // Initialize tracking of existing temp images
    previousImages.value = extractTempImageFolders(editor.getHTML());
  },
});

// Trigger Shiki re-decoration when highlighter finishes loading
watch(highlighter, (h) => {
  if (h && editor.value) {
    const { tr } = editor.value.state;
    tr.setMeta(shikiPluginKey, true);
    editor.value.view.dispatch(tr);
  }
});

// Watch for external changes to modelValue
watch(
  () => props.modelValue,
  (newValue) => {
    const isSame = editor.value?.getHTML() === newValue;
    if (!isSame && editor.value) {
      editor.value.commands.setContent(newValue, false);
    }
  }
);

onBeforeUnmount(() => {
  editor.value?.destroy();
});

// The wrapper's cn-input surface is a semi-transparent tint that differs per
// appearance style, so the sticky toolbar can't hardcode one solid color.
// Read the wrapper's computed tint and layer it over --background so the
// toolbar composites to exactly the same solid color as the editor body.
const rootEl = ref(null);
let surfaceObserver = null;

function syncToolbarSurfaceTint() {
  if (!rootEl.value) return;
  const tint = getComputedStyle(rootEl.value).backgroundColor;
  rootEl.value.style.setProperty("--editor-surface-tint", tint || "transparent");
}

onMounted(() => {
  syncToolbarSurfaceTint();
  // Dark mode and appearance styles are toggled via classes on <html>
  surfaceObserver = new MutationObserver(() => nextTick(syncToolbarSurfaceTint));
  surfaceObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["class"],
  });
});

onBeforeUnmount(() => {
  surfaceObserver?.disconnect();
});

// Helper function to extract temp image folders from content
function extractTempImageFolders(content) {
  const folders = new Set();
  // Match temp media URLs: /api/tmp-media/tmp-media-xxxxx
  const pattern = /\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)/g;
  let match;
  while ((match = pattern.exec(content)) !== null) {
    folders.add(match[1]);
  }
  return folders;
}

// Delete temp image from server
async function deleteTempImage(folder) {
  try {
    await client(`/api/tmp-media/${folder}`, {
      method: "DELETE",
    });
  } catch (err) {
    console.warn("Failed to delete temp content image:", err);
  }
}

// Check for deleted temp images and clean them up
function handleImageDeletion(newContent) {
  const currentFolders = extractTempImageFolders(newContent || "");

  // Find folders that were in previous but not in current (deleted)
  for (const folder of previousImages.value) {
    if (!currentFolders.has(folder)) {
      // Image was deleted, clean up from server
      deleteTempImage(folder);
    }
  }

  // Update previous images for next comparison
  previousImages.value = currentFolders;
}

// Link handling
const linkPopoverOpen = ref(false);
const linkUrl = ref("");
const linkInputRef = ref(null);
const linkHasExisting = ref(false);

const openLinkPopover = () => {
  if (!editor.value) return;
  const previousUrl = editor.value.getAttributes("link").href;
  linkUrl.value = previousUrl || "";
  linkHasExisting.value = !!previousUrl;
};

watch(linkPopoverOpen, (open) => {
  if (open) {
    nextTick(() => linkInputRef.value?.focus());
  }
});

const applyLink = () => {
  if (!editor.value) return;

  if (!linkUrl.value) {
    editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
  } else {
    editor.value.chain().focus().extendMarkRange("link").setLink({ href: linkUrl.value }).run();
  }
  linkPopoverOpen.value = false;
};

const removeLink = () => {
  if (!editor.value) return;
  editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
  linkPopoverOpen.value = false;
};

// Image upload handling
const isUploading = ref(false);

const uploadImageFile = async (file) => {
  const maxSize = 10 * 1024 * 1024;
  if (file.size > maxSize) {
    toast.error("Image size must be less than 10MB");
    return;
  }

  isUploading.value = true;
  try {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("model_type", props.modelType);
    formData.append("collection", props.collection);
    formData.append("model_id", "0");

    const response = await client("/api/media/upload", {
      method: "POST",
      body: formData,
    });

    if (response.media?.url) {
      editor.value?.chain().focus().setImage({ src: response.media.url }).run();
    }
  } catch (error) {
    console.error("Image upload failed:", error);
    toast.error("Failed to upload image. Please try again.");
  } finally {
    isUploading.value = false;
  }
};

const triggerImageUpload = () => {
  imageInput.value?.click();
};

const handleImageUpload = async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  await uploadImageFile(file);

  if (imageInput.value) {
    imageInput.value.value = "";
  }
};

</script>

<style scoped>
@reference "../../../assets/css/main.css";

.editor-toolbar {
  @apply border-border z-10 flex flex-wrap items-center gap-1 rounded-t-[inherit] border-b bg-transparent p-2;
}

/* Solid two-layer background: page --background below plus the wrapper's
   cn-input tint on top (set as --editor-surface-tint from JS), so the stuck
   toolbar is opaque yet identical in color to the editor body. */
.editor-toolbar-sticky {
  @apply sticky top-(--navbar-height-mobile) lg:top-(--navbar-height-desktop);
  background-color: var(--background);
  background-image: linear-gradient(
    var(--editor-surface-tint, transparent),
    var(--editor-surface-tint, transparent)
  );
}

.toolbar-group {
  @apply flex items-center gap-1;
}

.toolbar-divider {
  @apply bg-border mx-1 h-5 w-px;
}

.toolbar-button {
  @apply text-muted-foreground hover:bg-muted flex size-8 items-center justify-center rounded-lg border border-transparent transition-colors;
}

.toolbar-button.is-active {
  @apply bg-muted text-foreground border-border;
}

.editor-content-wrapper {
  min-height: var(--editor-min-h);
  @apply p-4;
}

/* TipTap prose styling */
:deep(.ProseMirror) {
  min-height: var(--editor-min-h);
  @apply text-foreground leading-relaxed outline-none;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  @apply text-foreground/50;
  content: attr(data-placeholder);
  float: left;
  pointer-events: none;
  height: 0;
}

:deep(.ProseMirror p) {
  @apply my-3;
}

:deep(.ProseMirror .image-node-view img) {
  @apply my-0;
}

:deep(.ProseMirror .code-block-wrapper pre code p) {
  @apply my-0;
}

/* Dark mode text colors for all content */
:deep(.ProseMirror) {
  @apply prose-headings:text-foreground prose-headings:font-semibold prose-headings:tracking-tighter;
  @apply prose-p:text-foreground;
  @apply prose-li:text-foreground;
  @apply prose-strong:text-foreground prose-strong:font-semibold prose-strong:tracking-tighter;
  @apply prose-code:text-foreground;
  @apply prose-blockquote:text-muted-foreground;
}


:deep(.ProseMirror :not(pre) > code) {
  @apply bg-muted rounded px-1.5 py-0.5 font-mono text-sm;
}

:deep(.post-content-link) {
  @apply text-foreground cursor-pointer font-semibold tracking-tight underline;
}
</style>

<style>
/* Shiki dark mode - needs non-scoped style because .dark is on <html> ancestor */
.dark .tiptap-editor span[style*="--shiki-dark"] {
  color: var(--shiki-dark) !important;
}
</style>
