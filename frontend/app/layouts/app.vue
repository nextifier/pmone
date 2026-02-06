<template>
  <SidebarProvider variant="sidebar" :defaultOpen="defaultOpen" id="layout-app">
    <AppSidebar v-if="!isExcluded" class="select-none" />

    <SidebarInset
      :class="isExcluded ? 'contents' : 'mx-auto min-h-screen max-w-[1920px]'"
    >
      <AppHeader v-if="!isExcluded" />
      <div
        :class="isExcluded ? 'contents' : 'grow overflow-x-hidden px-4'"
      >
        <slot />
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const defaultOpen = useCookie("sidebar_state");
const route = useRoute();

const isExcluded = computed(() => ["posts-create", "posts-slug-edit"].includes(route.name));
</script>
