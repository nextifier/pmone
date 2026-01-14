<template>
  <header
    class="border-border/50 bg-background/95 supports-backdrop-filter:bg-background/90 sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) border-b text-sm backdrop-blur-sm lg:h-(--navbar-height-desktop)"
  >
    <div class="container-wider relative flex h-full items-center justify-between">
      <!-- Tabs Trigger - Desktop only (absolute to header for content-area centering) -->
      <div
        v-if="!isMobile"
        class="absolute top-1/2 left-1/2 z-50 hidden -translate-x-1/2 -translate-y-1/2 md:block"
      >
        <PostTabsTrigger />
      </div>

      <!-- Left Area -->
      <div class="flex h-full items-center gap-x-4">
        <!-- Back Button -->

        <BackButton v-slot="{ goBack }" destination="/posts" :force-destination="true">
          <button
            @click="goBack"
            class="text-primary bg-muted flex size-8 items-center justify-center rounded-lg"
          >
            <Icon name="lucide:arrow-left" class="size-4.5 shrink-0" />
          </button>
        </BackButton>

        <!-- Autosave Status -->
        <div class="hidden items-center gap-x-2 lg:flex">
          <Switch
            id="autosave-toggle"
            v-model="editor.autosaveEnabled.value"
            :disabled="editor.showRestoreDialog.value"
            class="scale-90"
          />
          <Label for="autosave-toggle" class="text-muted-foreground cursor-pointer text-xs">
            <PostAutosaveStatus
              v-if="editor.autosaveEnabled.value"
              :is-saving="editor.autosave.isSaving.value"
              :is-saved="editor.autosave.isSaved.value"
              :has-error="editor.autosave.hasError.value"
              :last-saved-at="editor.autosave.lastSavedAt.value"
              :error="editor.autosave.autosaveStatus.value.error"
            />
            <span v-else>Autosave off</span>
          </Label>
        </div>
      </div>

      <!-- Right Area -->
      <div class="flex h-full items-center gap-x-2">
        <!-- Action Buttons -->
        <div class="flex items-center gap-x-2">
          <!-- Save to Draft (Create mode only) -->
          <button
            v-if="editor.mode.value === 'create'"
            type="button"
            @click="editor.saveDraft"
            :disabled="editor.loading.value || !editor.form.title"
            class="border-input hover:bg-accent hover:text-accent-foreground hidden items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition disabled:opacity-50 lg:flex"
          >
            <Icon name="hugeicons:file-edit" class="size-3.5" />
            Save Draft
          </button>

          <!-- Publish Button -->
          <button
            v-if="editor.canPublish.value"
            type="button"
            @click="showPublishDialog = true"
            :disabled="editor.loading.value"
            class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-xs font-medium tracking-tight transition disabled:opacity-50"
          >
            <Spinner v-if="editor.loading.value" class="size-3.5" />
            <Icon v-else name="hugeicons:sent" class="size-3.5" />
            Publish
          </button>

          <!-- Unpublish Button (Edit mode, status=published) -->
          <button
            v-if="editor.canUnpublish.value"
            type="button"
            @click="editor.unpublish"
            :disabled="editor.loading.value"
            class="border-input hover:bg-accent hover:text-accent-foreground hidden items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition disabled:opacity-50 lg:flex"
          >
            <Icon name="hugeicons:file-withdraw" class="size-3.5" />
            Unpublish
          </button>

          <!-- Update Button (Edit mode) -->
          <button
            v-if="editor.canUpdate.value"
            type="button"
            @click="editor.handleSubmit"
            :disabled="editor.loading.value"
            class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-xs font-medium tracking-tight transition disabled:opacity-50"
          >
            <Spinner v-if="editor.loading.value" class="size-3.5" />
            <Icon v-else name="hugeicons:checkmark-circle-02" class="size-3.5" />
            Update
          </button>
        </div>

        <!-- Utilities -->
        <div class="flex h-full shrink-0 items-center gap-x-1">
          <Tippy>
            <ColorModeToggle />
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>Light / Dark Mode</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} D</kbd>
              </span>
            </template>
          </Tippy>

          <Tippy>
            <button
              data-sidebar="trigger"
              data-slot="sidebar-trigger"
              class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg"
              @click="toggleSidebar"
            >
              <Icon
                v-if="sidebarOpen && !isMobile"
                name="hugeicons:sidebar-right-01"
                class="text-primary size-5"
              />
              <Icon v-else name="hugeicons:sidebar-right" class="text-primary size-5" />
            </button>
            <template #content>
              <span class="inline-flex items-center gap-x-1.5 tracking-tight">
                <span>Toggle Sidebar</span>
                <kbd class="keyboard-symbol">{{ metaSymbol }} B</kbd>
              </span>
            </template>
          </Tippy>
        </div>
      </div>
    </div>
  </header>

  <!-- Publish Dialog (outside header to not affect flex layout) -->
  <PostPublishDialog v-model:open="showPublishDialog" @publish="handlePublish" />
</template>

<script setup lang="ts">
import { useSidebar } from "@/components/ui/sidebar/utils";
import { usePostEditor } from "@/composables/usePostEditor";

const editor = usePostEditor();
const { toggleSidebar, open: sidebarOpen, isMobile } = useSidebar();
const { metaSymbol } = useShortcuts();
const { isAuthenticated } = useSanctumAuth();

const showPublishDialog = ref(false);

function handlePublish(scheduledAt?: Date) {
  editor.publish(scheduledAt);
  showPublishDialog.value = false;
}
</script>
