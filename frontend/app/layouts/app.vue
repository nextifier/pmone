<template>
  <SidebarProvider variant="sidebar" :defaultOpen="defaultOpen" id="layout-app">
    <PostFormSidebar v-if="['posts-create', 'posts-slug-edit'].includes(route.name)" />
    <AppSidebar v-else class="select-none" />

    <SidebarInset class="mx-auto min-h-screen max-w-[1920px]">
      <AppHeader />
      <main class="grow overflow-x-hidden px-4">
        <slot />
      </main>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
import PostFormSidebar from "~/components/post/PostFormSidebar.vue";

const defaultOpen = useCookie("sidebar_state");
const route = useRoute();
</script>
