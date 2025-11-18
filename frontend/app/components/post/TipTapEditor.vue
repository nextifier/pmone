<template>
  <div class="tiptap-editor">
    <!-- Toolbar -->
    <div v-if="editor" class="editor-toolbar">
      <div class="toolbar-group">
        <button
          type="button"
          @click="editor.chain().focus().toggleBold().run()"
          :class="{ 'is-active': editor.isActive('bold') }"
          class="toolbar-button"
          title="Bold (Cmd+B)"
          v-tippy="'Bold (Cmd+B)'"
        >
          <Icon name="lucide:bold" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleItalic().run()"
          :class="{ 'is-active': editor.isActive('italic') }"
          class="toolbar-button"
          title="Italic (Cmd+I)"
          v-tippy="'Italic (Cmd+I)'"
        >
          <Icon name="lucide:italic" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleUnderline().run()"
          :class="{ 'is-active': editor.isActive('underline') }"
          class="toolbar-button"
          title="Underline (Cmd+U)"
          v-tippy="'Underline (Cmd+U)'"
        >
          <Icon name="lucide:underline" />
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
          <Icon name="lucide:heading-2" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
          :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
          class="toolbar-button"
          title="Heading 3"
          v-tippy="'Heading 3'"
        >
          <Icon name="lucide:heading-3" />
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
          <Icon name="lucide:list" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleOrderedList().run()"
          :class="{ 'is-active': editor.isActive('orderedList') }"
          class="toolbar-button"
          title="Ordered List"
          v-tippy="'Ordered List'"
        >
          <Icon name="lucide:list-ordered" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().toggleBlockquote().run()"
          :class="{ 'is-active': editor.isActive('blockquote') }"
          class="toolbar-button"
          title="Blockquote"
          v-tippy="'Blockquote'"
        >
          <Icon name="lucide:quote" />
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
          <Icon name="lucide:link" />
        </button>
        <button
          type="button"
          @click="triggerImageUpload"
          class="toolbar-button"
          title="Upload Image"
          v-tippy="'Upload Image'"
        >
          <Icon name="lucide:image" />
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
          <Icon name="lucide:minus" />
        </button>
        <button
          type="button"
          @click="editor.chain().focus().setHardBreak().run()"
          class="toolbar-button"
          title="Line Break"
          v-tippy="'Line Break'"
        >
          <Icon name="lucide:wrap-text" />
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
  </div>
</template>

<script setup>
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import Placeholder from "@tiptap/extension-placeholder";
import TextAlign from "@tiptap/extension-text-align";
import StarterKit from "@tiptap/starter-kit";
import { EditorContent, useEditor } from "@tiptap/vue-3";

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
});

const emit = defineEmits(["update:modelValue"]);

const imageInput = ref(null);
const client = useSanctumClient();

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      link: false, // Disable link from StarterKit to use custom config
    }),
    Image.configure({
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
    emit("update:modelValue", editor.getHTML());
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

  try {
    // Create FormData for upload
    const formData = new FormData();
    formData.append("file", file);
    formData.append("model_type", "App\\Models\\Post");
    formData.append("model_id", props.postId || "0"); // Use 0 for new posts
    formData.append("collection", "content_images");

    // Upload image using API
    const response = await client("/api/media/upload", {
      method: "POST",
      body: formData,
    });

    // Insert image into editor
    if (response.media?.url) {
      editor.value?.chain().focus().setImage({ src: response.media.url }).run();
    }

    // Clear input
    if (imageInput.value) {
      imageInput.value.value = "";
    }
  } catch (error) {
    console.error("Image upload failed:", error);
    // You can add toast notification here
    alert("Failed to upload image. Please try again.");
  }
};
</script>

<style scoped>
@reference "../../assets/css/main.css";

.tiptap-editor {
  @apply overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700;
}

.editor-toolbar {
  @apply flex flex-wrap items-center gap-1 border-b border-gray-200 p-2 dark:border-gray-700;
}

.toolbar-group {
  @apply flex items-center gap-1;
}

.toolbar-divider {
  @apply bg-border mx-1 h-5 w-px;
}

.toolbar-button {
  @apply rounded p-2 text-gray-700 transition-colors hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700;
}

.toolbar-button.is-active {
  @apply bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-white;
}

.editor-content-wrapper {
  @apply min-h-[400px] p-4;
}

/* TipTap prose styling */
:deep(.ProseMirror) {
  @apply min-h-[350px] text-gray-900 outline-none dark:text-gray-100;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  @apply text-gray-400 dark:text-gray-500;
  content: attr(data-placeholder);
  float: left;
  pointer-events: none;
  height: 0;
}

/* Dark mode text colors for all content */
:deep(.ProseMirror) {
  @apply prose-headings:text-gray-900 dark:prose-headings:text-gray-100;
  @apply prose-p:text-gray-900 dark:prose-p:text-gray-100;
  @apply prose-li:text-gray-900 dark:prose-li:text-gray-100;
  @apply prose-strong:text-gray-900 dark:prose-strong:text-gray-100;
  @apply prose-code:text-gray-900 dark:prose-code:text-gray-100;
  @apply prose-blockquote:text-gray-700 dark:prose-blockquote:text-gray-300;
}

:deep(.post-content-image) {
  @apply my-4 h-auto max-w-full rounded-lg;
}

:deep(.post-content-link) {
  @apply text-blue-600 underline hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300;
}
</style>
