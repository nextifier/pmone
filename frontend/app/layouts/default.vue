<template>
  <SidebarProvider>
    <SidebarInset>
      <Header />
      <main class="flex-auto">
        <slot />
      </main>
    </SidebarInset>
  </SidebarProvider>
</template>
