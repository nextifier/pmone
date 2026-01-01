<template>
  <section class="relative isolate z-10">
    <!-- Grid Background Pattern -->
    <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
      <!-- Vertical border lines on sides -->
      <div
        class="border-border/50 dark:border-border/30 absolute inset-x-0 top-0 mx-auto h-full max-w-6xl border-x"
      />
      <!-- SVG Grid Pattern -->
      <svg
        class="text-border absolute inset-0 size-full mask-[linear-gradient(to_bottom,black,black_70%,transparent)]"
      >
        <defs>
          <pattern
            id="grid-pattern-light"
            x="0"
            y="0"
            width="60"
            height="60"
            patternUnits="userSpaceOnUse"
          >
            <path d="M 60 0 L 0 0 0 60" fill="transparent" stroke="currentColor" stroke-width="1" />
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#grid-pattern-light)" />
      </svg>
    </div>

    <!-- Gradient Glow -->
    <div class="pointer-events-none absolute inset-0 z-1 overflow-hidden" aria-hidden="true">
      <div
        class="bg-background absolute -top-40 left-1/2 h-[600px] w-[1200px] -translate-x-1/2 rounded-full blur-3xl"
      />
    </div>

    <div class="relative z-2 container">
      <div
        class="flex flex-col items-center justify-center pt-10 pb-12 text-center lg:pt-14 lg:pb-20"
      >
        <!-- Announcement Badge -->
        <NuxtLink
          to="/news"
          class="group bg-muted/50 ring-border hover:bg-muted mb-6 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm ring-1 transition"
        >
          <span class="text-muted-foreground">Your all-in-one event management platform</span>
          <span
            class="text-primary inline-flex items-center gap-1 font-medium transition group-hover:translate-x-0.5"
          >
            <span class="hidden sm:inline">Learn more</span>
            <Icon name="lucide:arrow-right" class="size-3.5" />
          </span>
        </NuxtLink>

        <!-- Headline -->
        <h1
          class="text-foreground max-w-3xl text-4xl leading-[1.25]! font-medium tracking-tighter text-balance sm:text-6xl"
        >
          Stop juggling 10 different tools. Just use one.
        </h1>

        <!-- Subtitle -->
        <p class="section-description mt-5 max-w-2xl text-center">
          PM One brings your projects, short links, analytics, and content together in one place. No
          more tab chaos. No more scattered data. Just everything you need, where you need it.
        </p>

        <!-- CTA Buttons -->
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Button to="/signup" variant="default" rounded="lg">
            Start for free
            <Icon name="lucide:arrow-right" class="size-4" />
          </Button>
          <Button to="/demo" variant="outline" rounded="lg"> See it in action </Button>
        </div>

        <!-- Feature Tabs -->
        <div class="mt-12 flex flex-wrap items-center justify-center gap-2">
          <div
            v-for="tab in featureTabs"
            :key="tab.label"
            class="ring-border bg-background inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium ring-1"
          >
            <span class="flex size-5 items-center justify-center rounded-md" :class="tab.iconBg">
              <Icon :name="tab.icon" class="size-3 text-white" />
            </span>
            {{ tab.label }}
          </div>
        </div>

        <!-- Hero Image -->
        <div class="relative mt-12 w-full max-w-5xl lg:mt-16">
          <div class="shadow-wrapper rounded-xl">
            <div
              class="bg-muted/50 border-border flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl border"
            >
              <NuxtImg
                v-if="heroImageExists"
                src="/img/dashboard-preview.png"
                alt="PM One Dashboard"
                class="size-full object-cover object-top"
              />
              <div v-else class="flex flex-col items-center gap-4 p-8 text-center">
                <div class="bg-muted flex size-16 items-center justify-center rounded-2xl">
                  <Icon name="lucide:layout-dashboard" class="text-muted-foreground size-8" />
                </div>
                <div>
                  <p class="text-foreground font-semibold tracking-tight">Dashboard Preview</p>
                  <p class="text-muted-foreground mt-1 text-sm">
                    Manage all your projects, links, and analytics in one place
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const heroImageExists = ref(false);

const featureTabs = [
  { label: "Projects", icon: "lucide:folder-kanban", iconBg: "bg-orange-500" },
  { label: "Short Links", icon: "lucide:link", iconBg: "bg-green-500" },
  { label: "Analytics", icon: "lucide:bar-chart-3", iconBg: "bg-blue-500" },
  { label: "Blog", icon: "lucide:file-text", iconBg: "bg-purple-500" },
];
</script>
