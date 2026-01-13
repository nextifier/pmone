<template>
  <SidebarProvider :defaultOpen="defaultOpen" id="layout-post-editor">
    <PostEditorSidebar side="right" variant="sidebar" class="select-none" />

    <div class="flex w-full flex-col">
      <PostEditorHeader />

      <main class="grow overflow-x-hidden px-4">
        <slot />
      </main>
    </div>
  </SidebarProvider>
</template>

<script setup>
const defaultOpen = useCookie("sidebar_state");
const route = useRoute();
</script>
