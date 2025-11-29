<template>
  <footer class="relative isolate overflow-hidden pt-10 pb-6">
    <div class="relative z-20 container">
      <div class="flex flex-col items-center justify-center gap-4 pb-4">
        <nuxt-link
          to="/"
          @click="$scrollToTopIfCurrentPageIs('index')"
          v-ripple
          aria-label="Brand Logo"
        >
          <Logo class="h-12" />
        </nuxt-link>

        <SocialMedia class="gap-x-6" iconClasses="size-6" />

        <div class="flex items-center gap-x-2 text-xs sm:text-sm">
          <nuxt-link
            to="/terms"
            class="hover:bg-muted hover:text-primary rounded-full px-3 py-2 transition"
            active-class="bg-muted text-primary"
            >Terms</nuxt-link
          >
          <span class="h-1 w-px bg-gray-300 dark:bg-gray-700"></span>
          <nuxt-link
            to="/privacy"
            class="hover:bg-muted hover:text-primary rounded-full px-3 py-2 transition"
            active-class="bg-muted text-primary"
            >Privacy</nuxt-link
          >
        </div>

        <div class="text-muted-foreground flex items-center text-xs">
          <span
            ><span class="hidden sm:inline">Copyright</span> ©
            {{ new Date().getFullYear() }} {{ useAppConfig().app.name }}. All
            rights reserved.</span
          >
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup></script>
