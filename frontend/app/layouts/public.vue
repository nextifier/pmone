<template>
  <div class="min-h-screen flex flex-col bg-background">
    <header class="border-b">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <NuxtLink to="/" class="flex items-center gap-2">
          <Icon name="hugeicons:building-01" class="text-primary size-6" />
          <span class="text-base font-semibold tracking-tight">PM One</span>
        </NuxtLink>
        <nav class="flex items-center gap-3 sm:gap-5 text-sm tracking-tight">
          <NuxtLink to="/accommodation" class="hover:text-primary">Accommodation</NuxtLink>
          <NuxtLink to="/terms" class="hover:text-primary">Terms</NuxtLink>
          <NuxtLink to="/privacy" class="hover:text-primary">Privacy</NuxtLink>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t mt-auto">
      <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-xs sm:text-sm tracking-tight text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
        <p>&copy; {{ new Date().getFullYear() }} PM One. All rights reserved.</p>
        <p>Need help? <a href="mailto:support@pmone.id" class="text-primary hover:underline">support@pmone.id</a></p>
      </div>
    </footer>
  </div>
</template>

<script setup>
</script>
