<template>
  <section class="py-20 lg:py-32">
    <div class="container">
      <div class="mb-12 text-center lg:mb-16">
        <h2 class="section-title">Everything you need. Nothing you don't.</h2>
        <p class="section-description mx-auto mt-4">
          We packed in all the features that actually matter, and left out the bloat.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div
          v-for="feature in features"
          :key="feature.title"
          class="group bg-card ring-border rounded-xl p-5 ring-1 transition hover:shadow-md"
        >
          <div class="mb-3 flex items-center gap-3">
            <div
              class="bg-muted flex size-9 items-center justify-center rounded-lg transition group-hover:bg-orange-500/10"
            >
              <Icon
                :name="feature.icon"
                class="text-muted-foreground size-4 transition group-hover:text-orange-500"
              />
            </div>
            <h3 class="text-foreground text-sm font-semibold tracking-tight">
              {{ feature.title }}
            </h3>
          </div>
          <p class="text-muted-foreground text-sm leading-relaxed">{{ feature.description }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const features = [
  {
    title: "Two-Factor Auth",
    description: "Extra security with TOTP-based 2FA. Sleep better at night.",
    icon: "lucide:shield-check",
  },
  {
    title: "Role & Permissions",
    description: "Granular access control. Give people exactly the access they need.",
    icon: "lucide:user-cog",
  },
  {
    title: "Media Library",
    description: "Upload, organize, and optimize your images automatically.",
    icon: "lucide:image",
  },
  {
    title: "Contact Forms",
    description: "Collect and manage form submissions from your audience.",
    icon: "lucide:mail",
  },
  {
    title: "Activity Logs",
    description: "Full audit trail. Know who did what and when.",
    icon: "lucide:history",
  },
  {
    title: "Dark Mode",
    description: "Easy on the eyes, especially during those late-night sessions.",
    icon: "lucide:moon",
  },
  {
    title: "Import/Export",
    description: "Bulk operations with CSV. Move data in and out easily.",
    icon: "lucide:download",
  },
  {
    title: "Real-time Sync",
    description: "Changes show up instantly. No refresh needed.",
    icon: "lucide:refresh-cw",
  },
];
</script>
