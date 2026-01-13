import type { Ref, ComputedRef } from "vue";

export interface PostForm {
  title: string;
  slug: string;
  excerpt: string;
  content: string;
  status: "draft" | "published" | "scheduled" | "archived";
  visibility: "public" | "private" | "members_only";
  published_at: string | null;
  featured: boolean;
  meta_title: string;
  meta_description: string;
  featured_image_caption: string;
  tags: string[];
  authors: Array<{ user_id: number | null; order: number }>;
}

export interface PostEditorContext {
  // Mode
  mode: Ref<"create" | "edit">;
  initialData: Ref<any | null>;

  // Form state
  form: PostForm;
  errors: Ref<Record<string, string[]>>;
  loading: Ref<boolean>;

  // Post identifiers
  postId: Ref<number | null>;
  postSlug: Ref<string | null>;

  // Image files
  imageFiles: Ref<{
    featured_image: any[];
    og_image: any[];
  }>;
  deleteFlags: Ref<{
    featured_image: boolean;
    og_image: boolean;
  }>;

  // Autosave
  autosaveEnabled: Ref<boolean>;
  autosave: {
    isSaving: Ref<boolean>;
    isSaved: Ref<boolean>;
    hasError: Ref<boolean>;
    lastSavedAt: Ref<Date | null>;
    autosaveStatus: Ref<{ status: string; error?: string }>;
    localBackup: Ref<any>;
    discardAutosave: () => Promise<void>;
  };

  // Slug
  slugManuallyEdited: Ref<boolean>;
  slugChecking: Ref<boolean>;
  slugAvailable: Ref<boolean | null>;

  // Available users for authors
  availableUsers: Ref<Array<{ id: number; name: string; email: string }>>;

  // UI state
  activeTab: Ref<"editor" | "preview">;
  showRestoreDialog: Ref<boolean>;

  // Actions
  handleSubmit: () => Promise<void>;
  saveDraft: () => Promise<void>;
  publish: (scheduledAt?: Date) => Promise<void>;
  unpublish: () => Promise<void>;
  deletePost: () => Promise<void>;

  // Helper functions
  getAvailableUsersForRow: (currentIndex: number) => Array<{ id: number; name: string; email: string }>;
  addAuthor: () => void;
  removeAuthor: (index: number) => void;
  moveAuthorUp: (index: number) => void;
  moveAuthorDown: (index: number) => void;

  // Computed
  canPublish: ComputedRef<boolean>;
  canUnpublish: ComputedRef<boolean>;
  canUpdate: ComputedRef<boolean>;
  canDelete: ComputedRef<boolean>;
  previewData: ComputedRef<any>;
}

const POST_EDITOR_KEY = Symbol("postEditor") as InjectionKey<PostEditorContext>;

export function providePostEditor(context: PostEditorContext) {
  provide(POST_EDITOR_KEY, context);
}

export function usePostEditor(): PostEditorContext {
  const context = inject(POST_EDITOR_KEY);
  if (!context) {
    throw new Error("usePostEditor must be used within a PostEditor component");
  }
  return context;
}

export function usePostEditorOptional(): PostEditorContext | undefined {
  return inject(POST_EDITOR_KEY);
}
