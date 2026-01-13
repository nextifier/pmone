<template>
  <header
    class="border-border/50 bg-background/95 supports-backdrop-filter:bg-background/90 sticky inset-x-0 top-0 z-50 flex h-(--navbar-height-mobile) items-center justify-between border-b px-4 text-sm backdrop-blur-sm lg:h-(--navbar-height-desktop)"
  >
    <!-- Left Area -->
    <div class="flex h-full items-center gap-x-6">
      <!-- Back Button -->
      <BackButton destination="/posts" :force-destination="true" />

      <!-- Autosave Status -->
      <div class="hidden items-center gap-2 lg:flex">
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

      <!-- Tabs Trigger -->
      <div class="hidden lg:block">
        <TabsList
          class="bg-muted text-body relative isolate inline-flex items-center justify-center rounded-full border p-0.5"
        >
          <TabsIndicator
            class="absolute inset-y-0.5 left-0 z-0 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-full transition-all duration-300 ease-in-out"
          >
            <div class="bg-primary size-full rounded-full" />
          </TabsIndicator>
          <TabsTrigger
            value="editor"
            class="data-[state=active]:text-primary-foreground relative z-10 inline-flex h-7 items-center justify-center rounded-full px-2.5 text-xs font-medium tracking-tight whitespace-nowrap transition-all"
          >
            Editor
          </TabsTrigger>
          <TabsTrigger
            value="preview"
            class="data-[state=active]:text-primary-foreground relative z-10 inline-flex h-7 items-center justify-center rounded-full px-2.5 text-xs font-medium tracking-tight whitespace-nowrap transition-all"
          >
            Preview
          </TabsTrigger>
        </TabsList>
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
  </header>

  <!-- Publish Dialog (outside header to not affect flex layout) -->
  <PostPublishDialog v-model:open="showPublishDialog" @publish="handlePublish" />
</template>

<script setup lang="ts">
import { useSidebar } from "@/components/ui/sidebar/utils";
import { usePostEditor } from "@/composables/usePostEditor";
import { TabsIndicator, TabsList, TabsTrigger } from "reka-ui";

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
