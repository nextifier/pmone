<template>
  <div class="tiptap-editor" :style="{ '--editor-min-h': minHeight }">
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
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <button
          type="button"
          @click="setLink"
          :class="{ 'is-active': editor.isActive('link') }"
          class="toolbar-button"
          title="Add Link"
          v-tippy="'Add Link'"
        >
          <Icon name="hugeicons:link-01" class="size-4.5" />
        </button>
        <button
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
    <div class="editor-content-wrapper">
      <EditorContent :editor="editor" class="editor-content" />
    </div>

    <!-- Hidden file input for image upload -->
    <input
      ref="imageInput"
      type="file"
      accept="image/*"
      style="display: none"
      @change="handleImageUpload"
    />

    <!-- Caption Modal -->
    <Dialog v-model:open="captionModal.show">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Add Image Caption</DialogTitle>
          <DialogDescription>
            Add an optional caption for this image (max 500 characters)
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="image-caption">Caption (Optional)</Label>
            <Textarea
              id="image-caption"
              v-model="captionModal.caption"
              maxlength="500"
              placeholder="Enter image caption..."
              rows="3"
            />
            <p class="text-muted-foreground text-xs">
              {{ captionModal.caption.length }}/500 characters
            </p>
          </div>
        </div>

        <DialogFooter>
          <button
            type="button"
            @click="skipCaption"
            class="border-input hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium"
          >
            Skip
          </button>
          <button
            type="button"
            @click="saveCaptionAndClose"
            class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium"
          >
            Save Caption
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import Placeholder from "@tiptap/extension-placeholder";
import TextAlign from "@tiptap/extension-text-align";
import StarterKit from "@tiptap/starter-kit";
import { EditorContent, useEditor } from "@tiptap/vue-3";
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
});

const emit = defineEmits(["update:modelValue"]);

const imageInput = ref(null);
const client = useSanctumClient();

// Track previous images to detect deletions
const previousImages = ref(new Set());

// Caption modal state
const captionModal = ref({
  show: false,
  caption: "",
  imageUrl: "",
  imagePos: null,
});

// Custom Image extension with data-caption support
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
});

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      link: false, // Disable link from StarterKit to use custom config
    }),
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
const setLink = () => {
  if (!editor.value) return;

  const previousUrl = editor.value.getAttributes("link").href;
  const url = window.prompt("URL", previousUrl);

  if (url === null) {
    return;
  }

  if (url === "") {
    editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
    return;
  }

  editor.value.chain().focus().extendMarkRange("link").setLink({ href: url }).run();
};

// Image upload handling
const triggerImageUpload = () => {
  imageInput.value?.click();
};

const handleImageUpload = async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  // Validate file size (max 10MB)
  const maxSize = 10 * 1024 * 1024;
  if (file.size > maxSize) {
    toast.error("Image size must be less than 10MB");
    if (imageInput.value) {
      imageInput.value.value = "";
    }
    return;
  }

  try {
    // Create FormData for upload - ALWAYS use temporary storage
    // Even for existing posts, we upload to temp first, then process on save
    // This ensures consistent behavior and proper cleanup
    const formData = new FormData();
    formData.append("file", file);
    formData.append("model_type", props.modelType);
    formData.append("collection", props.collection);
    // Always send model_id: 0 to use temporary storage
    formData.append("model_id", "0");

    // Upload image using API
    const response = await client("/api/media/upload", {
      method: "POST",
      body: formData,
    });

    // Insert image into editor first
    if (response.media?.url) {
      editor.value?.chain().focus().setImage({ src: response.media.url }).run();

      // Show caption modal
      showCaptionModal(response.media.url);
    }

    // Clear input
    if (imageInput.value) {
      imageInput.value.value = "";
    }
  } catch (error) {
    console.error("Image upload failed:", error);
    toast.error("Failed to upload image. Please try again.");
  }
};

// Show caption modal
const showCaptionModal = (imageUrl) => {
  captionModal.value = {
    show: true,
    caption: "",
    imageUrl: imageUrl,
    imagePos: null,
  };
};

// Skip caption (close modal without saving)
const skipCaption = () => {
  captionModal.value = {
    show: false,
    caption: "",
    imageUrl: "",
    imagePos: null,
  };
};

// Save caption and close modal
const saveCaptionAndClose = () => {
  if (!editor.value || !captionModal.value.imageUrl) return;

  // Find the image node and update with data-caption attribute
  const { state } = editor.value;
  const { doc } = state;

  doc.descendants((node, pos) => {
    if (node.type.name === "image" && node.attrs.src === captionModal.value.imageUrl) {
      // Update the image node with caption
      editor.value
        .chain()
        .setNodeSelection(pos)
        .updateAttributes("image", {
          "data-caption": captionModal.value.caption || null,
        })
        .run();

      return false; // Stop searching
    }
  });

  // Close modal
  captionModal.value = {
    show: false,
    caption: "",
    imageUrl: "",
    imagePos: null,
  };
};
</script>

<style scoped>
@reference "../../assets/css/main.css";

.tiptap-editor {
  @apply border-border rounded-lg border;
}

.editor-toolbar {
  @apply border-border bg-background z-10 flex flex-wrap items-center gap-1 rounded-t-lg border-b p-2;
}

.editor-toolbar-sticky {
  @apply sticky top-(--navbar-height-mobile) lg:top-(--navbar-height-desktop);
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
  @apply text-foreground outline-none;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  @apply text-foreground/50;
  content: attr(data-placeholder);
  float: left;
  pointer-events: none;
  height: 0;
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

:deep(.post-content-image) {
  @apply my-4 h-auto max-w-full rounded-lg;
}

:deep(.post-content-link) {
  @apply text-foreground cursor-pointer font-semibold tracking-tight underline;
}
</style>
