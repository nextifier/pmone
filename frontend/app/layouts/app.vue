<template>
  <SidebarProvider variant="sidebar" :defaultOpen="defaultOpen">
    <AppSidebar class="select-none" />

    <SidebarInset class="mx-auto min-h-screen max-w-[1920px]">
      <AppHeader />
      <main class="grow overflow-x-hidden px-4 pt-6 pb-16">
        <slot />
      </main>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const defaultOpen = useCookie("sidebar_state");
</script>
