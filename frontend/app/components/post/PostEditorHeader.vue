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
          <Button variant="ghost" size="icon" @click="goBack">
            <Icon name="lucide:arrow-left" class="size-5 shrink-0" />
          </Button>
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
          <Button
            v-if="editor.mode.value === 'create'"
            variant="outline"
            size="sm"
            :disabled="editor.loading.value || !editor.form.title"
            @click="editor.saveDraft"
          >
            <Icon name="hugeicons:file-edit" class="size-4 shrink-0" />
            Save Draft
            <KbdGroup class="ml-1">
              <Kbd>{{ metaSymbol }}</Kbd>
              <Kbd>S</Kbd>
            </KbdGroup>
          </Button>

          <!-- Publish Button -->
          <Button
            v-if="editor.canPublish.value"
            size="sm"
            :disabled="editor.loading.value"
            @click="showPublishDialog = true"
          >
            <Spinner v-if="editor.loading.value" class="size-4 shrink-0" />
            <Icon v-else name="hugeicons:sent" class="size-4 shrink-0" />
            Publish
            <KbdGroup class="ml-1">
              <Kbd>{{ metaSymbol }}</Kbd>
              <Kbd>S</Kbd>
            </KbdGroup>
          </Button>

          <!-- Unpublish Button (Edit mode, status=published) -->
          <Button
            v-if="editor.canUnpublish.value"
            variant="outline"
            size="sm"
            :disabled="editor.loading.value"
            @click="editor.unpublish"
          >
            <Icon name="hugeicons:archive-02" class="size-4 shrink-0" />
            Unpublish
          </Button>

          <!-- Update Button (Edit mode) -->
          <Button
            v-if="editor.canUpdate.value"
            size="sm"
            :disabled="editor.loading.value"
            @click="editor.handleSubmit"
          >
            <Spinner v-if="editor.loading.value" class="size-4 shrink-0" />
            <Icon v-else name="hugeicons:checkmark-circle-01" class="size-4 shrink-0" />
            Update
            <KbdGroup class="ml-1">
              <Kbd>{{ metaSymbol }}</Kbd>
              <Kbd>S</Kbd>
            </KbdGroup>
          </Button>
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
            <Button
              variant="ghost"
              size="icon"
              data-sidebar="trigger"
              data-slot="sidebar-trigger"
              @click="toggleSidebar"
            >
              <Icon
                v-if="sidebarOpen && !isMobile"
                name="hugeicons:sidebar-right-01"
                class="size-5"
              />
              <Icon v-else name="hugeicons:sidebar-right" class="size-5" />
            </Button>
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
