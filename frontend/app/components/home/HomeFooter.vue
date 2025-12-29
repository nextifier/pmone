<template>
  <footer class="bg-background border-border border-t py-16">
    <div class="container">
      <div class="grid gap-10 lg:grid-cols-6">
        <!-- Logo & Social -->
        <div class="lg:col-span-2">
          <NuxtLink to="/">
            <Logo class="text-primary h-6" />
          </NuxtLink>
          <p class="text-muted-foreground mt-4 max-w-xs text-sm leading-relaxed">
            The modern event management platform for people who'd rather be doing actual work than
            fighting with their tools.
          </p>
          <div class="mt-6 flex items-center gap-3">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-muted-foreground hover:text-foreground transition"
              :aria-label="social.label"
            >
              <Icon :name="social.icon" class="size-5" />
            </a>
          </div>
        </div>

        <!-- Product Links -->
        <div>
          <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Product</h4>
          <ul class="space-y-3">
            <li v-for="link in productLinks" :key="link.to">
              <NuxtLink
                :to="link.to"
                class="text-muted-foreground hover:text-foreground text-sm transition"
              >
                {{ link.label }}
              </NuxtLink>
            </li>
          </ul>
        </div>

        <!-- Resources Links -->
        <div>
          <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Resources</h4>
          <ul class="space-y-3">
            <li v-for="link in resourceLinks" :key="link.to">
              <NuxtLink
                :to="link.to"
                class="text-muted-foreground hover:text-foreground text-sm transition"
              >
                {{ link.label }}
              </NuxtLink>
            </li>
          </ul>
        </div>

        <!-- Company Links -->
        <div>
          <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Company</h4>
          <ul class="space-y-3">
            <li v-for="link in companyLinks" :key="link.to">
              <NuxtLink
                :to="link.to"
                class="text-muted-foreground hover:text-foreground text-sm transition"
              >
                {{ link.label }}
              </NuxtLink>
            </li>
          </ul>
        </div>

        <!-- Account Links -->
        <div>
          <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Account</h4>
          <ul class="space-y-3">
            <li v-for="link in accountLinks" :key="link.to">
              <NuxtLink
                :to="link.to"
                class="text-muted-foreground hover:text-foreground text-sm transition"
              >
                {{ link.label }}
              </NuxtLink>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div
        class="border-border mt-12 flex flex-col items-center justify-between gap-4 border-t pt-8 md:flex-row"
      >
        <div class="flex items-center gap-2">
          <span class="bg-success size-2 rounded-full" />
          <span class="text-muted-foreground text-sm">All systems operational</span>
        </div>
        <p class="text-muted-foreground text-sm">
          &copy; {{ new Date().getFullYear() }} PM One. All rights reserved.
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup>
const socialLinks = [
  { label: "Twitter", icon: "lucide:twitter", url: "https://twitter.com" },
  { label: "LinkedIn", icon: "lucide:linkedin", url: "https://linkedin.com" },
  { label: "GitHub", icon: "lucide:github", url: "https://github.com" },
  { label: "Instagram", icon: "lucide:instagram", url: "https://instagram.com" },
];

const productLinks = [
  { label: "Projects", to: "/projects" },
  { label: "Short Links", to: "/links" },
  { label: "Blog", to: "/posts" },
  { label: "Analytics", to: "/web-analytics" },
  { label: "API", to: "/api-consumers" },
];

const resourceLinks = [
  { label: "News", to: "/news" },
  { label: "Documentation", to: "/web-analytics/docs" },
  { label: "Demo", to: "/demo" },
];

const companyLinks = [
  { label: "Privacy Policy", to: "/privacy" },
  { label: "Terms of Service", to: "/terms" },
];

const accountLinks = [
  { label: "Log in", to: "/login" },
  { label: "Sign up", to: "/signup" },
  { label: "Forgot Password", to: "/forgot-password" },
];
</script>
