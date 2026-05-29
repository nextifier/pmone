<template>
  <SidebarProvider :style="{ '--sidebar-width': '18rem' }">
    <DocsSidebar :grouped-docs="groupedDocs" :current-slug="currentSlug" :pending="listPending" />
    <SidebarInset>
      <DocsHeader />
      <div class="min-h-screen-offset">
        <div class="container-wider">
          <slot />
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const { currentSlug, listPending, groupedDocs } = useDocsNav();
</script>
