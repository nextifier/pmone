<template>
  <SidebarProvider variant="sidebar" :defaultOpen="defaultOpen" id="layout-app">
    <AppSidebar
      v-if="!['posts-create', 'posts-slug-edit'].includes(route.name)"
      class="select-none"
    />

    <SidebarInset class="mx-auto min-h-screen max-w-[1920px]">
      <AppHeader />
      <main class="grow overflow-x-hidden px-4">
        <slot />
      </main>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const defaultOpen = useCookie("sidebar_state");
const route = useRoute();
</script>
