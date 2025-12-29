<template>
  <div class="relative overflow-hidden">
    <!-- Hero Section -->
    <section class="relative isolate z-10">
      <!-- Grid Background Pattern - Global -->
      <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <!-- Vertical border lines on sides -->
        <div
          class="border-border/50 dark:border-border/30 absolute inset-x-0 top-0 mx-auto h-full max-w-6xl border-x"
        />
        <!-- SVG Grid Pattern - Light Mode -->
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
              <path
                d="M 60 0 L 0 0 0 60"
                fill="transparent"
                stroke="currentColor"
                stroke-width="1"
              />
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
            PM One brings your projects, short links, analytics, and content together in one place.
            No more tab chaos. No more scattered data. Just everything you need, where you need it.
          </p>

          <!-- CTA Buttons -->
          <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <NuxtLink
              to="/signup"
              class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-11 items-center justify-center gap-2 rounded-lg px-6 text-sm font-semibold tracking-tight shadow-sm transition active:scale-98"
            >
              Start for free
              <Icon name="lucide:arrow-right" class="size-4" />
            </NuxtLink>
            <NuxtLink
              to="/demo"
              class="border-border hover:bg-muted inline-flex h-11 items-center justify-center gap-2 rounded-lg border bg-transparent px-6 text-sm font-semibold tracking-tight transition active:scale-98"
            >
              See it in action
            </NuxtLink>
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

    <!-- Trusted By Section -->
    <section class="border-border/50 border-y py-12">
      <div class="container">
        <p class="text-muted-foreground mb-8 text-center text-sm font-medium tracking-tight">
          Trusted by event pros and creators across Indonesia
        </p>
        <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
          <div
            v-for="logo in trustedLogos"
            :key="logo"
            class="text-muted-foreground/60 text-lg font-semibold tracking-tight"
          >
            {{ logo }}
          </div>
        </div>
      </div>
    </section>

    <!-- Problem Section -->
    <section class="py-20 lg:py-32">
      <div class="container">
        <div
          class="flex flex-col items-center lg:flex-row lg:items-start lg:justify-between lg:gap-16"
        >
          <!-- Left Side: Floating Cards -->
          <div class="relative hidden h-[400px] w-full max-w-md shrink-0 lg:block">
            <div class="bg-card ring-border absolute top-8 left-0 rounded-xl p-4 shadow-lg ring-1">
              <div class="flex items-center gap-3">
                <div class="bg-success/10 flex size-10 items-center justify-center rounded-full">
                  <Icon name="lucide:trending-up" class="text-success size-5" />
                </div>
                <div>
                  <p class="text-foreground text-sm font-semibold">+247%</p>
                  <p class="text-muted-foreground text-xs">Traffic this month</p>
                </div>
              </div>
            </div>
            <div
              class="bg-card ring-border absolute right-0 bottom-12 rounded-xl p-4 shadow-lg ring-1"
            >
              <div class="flex items-center gap-3">
                <div class="bg-info/10 flex size-10 items-center justify-center rounded-full">
                  <Icon name="lucide:mouse-pointer-click" class="text-info size-5" />
                </div>
                <div>
                  <p class="text-foreground text-sm font-semibold">12.5K</p>
                  <p class="text-muted-foreground text-xs">Clicks this week</p>
                </div>
              </div>
            </div>
            <div
              class="bg-card ring-border absolute top-1/2 left-12 -translate-y-1/2 rounded-xl p-4 shadow-lg ring-1"
            >
              <div class="flex items-center gap-3">
                <div class="bg-warning/10 flex size-10 items-center justify-center rounded-full">
                  <Icon name="lucide:users" class="text-warning size-5" />
                </div>
                <div>
                  <p class="text-foreground text-sm font-semibold">1.2K</p>
                  <p class="text-muted-foreground text-xs">Active visitors</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Side: Content -->
          <div class="max-w-2xl text-center lg:text-left">
            <h2 class="section-title">
              You're probably tired of the same old mess.
              <span class="text-muted-foreground">We get it.</span>
            </h2>
            <p class="section-description mt-6">
              Spreadsheets for projects. Bitly for links. Google Analytics for tracking. WordPress
              for your blog. And somehow you still can't find that one report your boss needs.
            </p>
            <p class="text-muted-foreground mt-4 text-base leading-relaxed tracking-tight">
              PM One fixes that. One login. One dashboard. Everything connected.
              <span class="text-primary font-medium">Projects</span>,
              <span class="text-primary font-medium">links</span>,
              <span class="text-primary font-medium">analytics</span>, and
              <span class="text-primary font-medium">content</span> — finally talking to each other.
            </p>
            <p class="text-muted-foreground mt-4 text-base leading-relaxed tracking-tight">
              Fast. Reliable. Actually looks good. And it scales as you grow.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Feature: Projects Management -->
    <section class="bg-muted/30 py-20 lg:py-32">
      <div class="container">
        <div class="mb-12 lg:mb-16">
          <div class="flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-md bg-orange-500">
              <Icon name="lucide:folder-kanban" class="size-3.5 text-white" />
            </span>
            <span class="text-sm font-semibold tracking-tight">Projects</span>
          </div>
          <h2 class="section-title mt-4 max-w-xl">
            Keep every project organized.<br />
            <span class="text-muted-foreground">Without the headache.</span>
          </h2>
          <p class="section-description mt-4 max-w-xl">
            Remember that time you lost an important file in a sea of folders? Or couldn't find who
            was responsible for what? Yeah, those days are over.
          </p>
          <NuxtLink
            to="/projects"
            class="border-border hover:bg-muted mt-6 inline-flex h-10 items-center justify-center rounded-lg border px-5 text-sm font-medium tracking-tight transition"
          >
            Explore Projects
          </NuxtLink>
        </div>

        <!-- Feature Cards Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="feature in projectFeatures" :key="feature.title" class="group relative">
            <div class="bg-card ring-border rounded-xl p-6 ring-1 transition hover:shadow-md">
              <div class="mb-4">
                <Icon
                  :name="feature.icon"
                  class="text-muted-foreground size-5 transition group-hover:text-orange-500"
                />
              </div>
              <h3 class="text-foreground font-semibold tracking-tight">{{ feature.title }}</h3>
              <p class="text-muted-foreground mt-2 text-sm leading-relaxed">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonial 1 -->
    <section class="py-16">
      <div class="container">
        <div class="flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
          <blockquote
            class="text-foreground max-w-3xl text-xl leading-relaxed font-medium tracking-tight lg:text-2xl"
          >
            "Finally, a platform that doesn't make me want to pull my hair out. Everything's in one
            place, and my team actually enjoys using it."
          </blockquote>
          <div class="flex shrink-0 items-center gap-4">
            <div class="text-right">
              <p class="text-foreground font-semibold tracking-tight">Sarah Chen</p>
              <p class="text-muted-foreground text-sm">Event Director at TechSummit</p>
            </div>
            <img
              src="https://api.dicebear.com/7.x/avataaars/svg?seed=sarah"
              alt="Sarah Chen"
              class="bg-muted size-12 rounded-full"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Feature: Links & Analytics -->
    <section class="bg-muted/30 py-20 lg:py-32">
      <div class="container">
        <div class="mb-12 lg:mb-16">
          <div class="flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-md bg-green-500">
              <Icon name="lucide:link" class="size-3.5 text-white" />
            </span>
            <span class="text-sm font-semibold tracking-tight">Links & Analytics</span>
          </div>
          <h2 class="section-title mt-4 max-w-xl">Short links that actually tell you something.</h2>
          <p class="section-description mt-4 max-w-xl">
            Ever shared a link and wondered if anyone clicked it? Or where your traffic actually
            came from? Stop guessing. Start knowing.
          </p>
          <NuxtLink
            to="/links"
            class="border-border hover:bg-muted mt-6 inline-flex h-10 items-center justify-center rounded-lg border px-5 text-sm font-medium tracking-tight transition"
          >
            Explore Links
          </NuxtLink>
        </div>

        <!-- Feature Cards Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="feature in linkFeatures" :key="feature.title" class="group relative">
            <div class="bg-card ring-border rounded-xl p-6 ring-1 transition hover:shadow-md">
              <div class="mb-4">
                <Icon
                  :name="feature.icon"
                  class="text-muted-foreground size-5 transition group-hover:text-green-500"
                />
              </div>
              <h3 class="text-foreground font-semibold tracking-tight">{{ feature.title }}</h3>
              <p class="text-muted-foreground mt-2 text-sm leading-relaxed">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Feature: Content Publishing -->
    <section class="py-20 lg:py-32">
      <div class="container">
        <div class="mb-12 lg:mb-16">
          <div class="flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-md bg-purple-500">
              <Icon name="lucide:file-text" class="size-3.5 text-white" />
            </span>
            <span class="text-sm font-semibold tracking-tight">Content Publishing</span>
          </div>
          <h2 class="section-title mt-4 max-w-xl">
            Write. Schedule. Publish.<br />
            <span class="text-muted-foreground">Without the WordPress drama.</span>
          </h2>
          <p class="section-description mt-4 max-w-xl">
            Your blog shouldn't need a PhD to manage. Create beautiful content with a clean editor,
            schedule posts when your audience is awake, and watch the engagement roll in.
          </p>
          <NuxtLink
            to="/posts"
            class="border-border hover:bg-muted mt-6 inline-flex h-10 items-center justify-center rounded-lg border px-5 text-sm font-medium tracking-tight transition"
          >
            Explore Content
          </NuxtLink>
        </div>

        <!-- Feature Cards Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="feature in contentFeatures" :key="feature.title" class="group relative">
            <div class="bg-card ring-border rounded-xl p-6 ring-1 transition hover:shadow-md">
              <div class="mb-4">
                <Icon
                  :name="feature.icon"
                  class="text-muted-foreground size-5 transition group-hover:text-purple-500"
                />
              </div>
              <h3 class="text-foreground font-semibold tracking-tight">{{ feature.title }}</h3>
              <p class="text-muted-foreground mt-2 text-sm leading-relaxed">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonial 2 -->
    <section class="bg-muted/30 py-16">
      <div class="container">
        <div class="flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
          <blockquote
            class="text-foreground max-w-3xl text-xl leading-relaxed font-medium tracking-tight lg:text-2xl"
          >
            "The QR codes and link analytics helped us understand our offline marketing for the
            first time. Game changer for our event promotions."
          </blockquote>
          <div class="flex shrink-0 items-center gap-4">
            <div class="text-right">
              <p class="text-foreground font-semibold tracking-tight">Marcus Rodriguez</p>
              <p class="text-muted-foreground text-sm">Marketing Lead at StartupXYZ</p>
            </div>
            <img
              src="https://api.dicebear.com/7.x/avataaars/svg?seed=marcus"
              alt="Marcus Rodriguez"
              class="bg-muted size-12 rounded-full"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Feature: API & Integrations -->
    <section class="py-20 lg:py-32">
      <div class="container">
        <div class="mb-12 lg:mb-16">
          <div class="flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-md bg-blue-500">
              <Icon name="lucide:code" class="size-3.5 text-white" />
            </span>
            <span class="text-sm font-semibold tracking-tight">API & Integrations</span>
          </div>
          <h2 class="section-title mt-4 max-w-xl">Plays nice with your existing tools.</h2>
          <p class="section-description mt-4 max-w-xl">
            Already have a tech stack you love? Keep it. Our API lets you connect PM One to
            anything. Google Analytics, custom apps, you name it.
          </p>
          <NuxtLink
            to="/api-consumers"
            class="border-border hover:bg-muted mt-6 inline-flex h-10 items-center justify-center rounded-lg border px-5 text-sm font-medium tracking-tight transition"
          >
            Explore API
          </NuxtLink>
        </div>

        <!-- Feature Cards Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="feature in apiFeatures" :key="feature.title" class="group relative">
            <div class="bg-card ring-border rounded-xl p-6 ring-1 transition hover:shadow-md">
              <div class="mb-4">
                <Icon
                  :name="feature.icon"
                  class="text-muted-foreground size-5 transition group-hover:text-blue-500"
                />
              </div>
              <h3 class="text-foreground font-semibold tracking-tight">{{ feature.title }}</h3>
              <p class="text-muted-foreground mt-2 text-sm leading-relaxed">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="border-border/50 border-y py-16">
      <div class="container">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
          <div v-for="stat in stats" :key="stat.label" class="text-center">
            <p class="text-primary text-4xl font-bold tracking-tighter lg:text-5xl">
              {{ stat.value }}
            </p>
            <p class="text-muted-foreground mt-2 text-sm tracking-tight">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- All Features Grid -->
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
            v-for="feature in allFeatures"
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

    <!-- CTA Section -->
    <section class="relative overflow-hidden py-20 lg:py-28">
      <div class="relative container">
        <div class="flex flex-col items-center text-center">
          <h2
            class="xs:text-5xl max-w-3xl text-4xl leading-tight font-semibold tracking-tighter lg:text-6xl"
          >
            Ready to simplify your workflow?
          </h2>
          <p class="mt-4 max-w-xl text-lg tracking-tight">
            Join thousands of event pros who ditched the chaos for a calmer, more organized way of
            working.
          </p>

          <!-- CTA Buttons -->
          <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <NuxtLink
              to="/signup"
              class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-11 items-center justify-center gap-2 rounded-lg px-6 text-sm font-semibold tracking-tight shadow-sm transition active:scale-98"
            >
              Get started free
            </NuxtLink>
            <NuxtLink
              to="/demo"
              class="border-border text-foreground hover:bg-muted inline-flex h-11 items-center justify-center gap-2 rounded-lg border px-6 text-sm font-semibold tracking-tight transition active:scale-98"
            >
              Watch demo
            </NuxtLink>
          </div>

          <!-- Trust Badges -->
          <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <div class="text-primary/70 flex items-center gap-2">
              <Icon name="hugeicons:star-circle" class="size-5 shrink-0" />
              <span class="text-sm">5.0 Rating</span>
            </div>
            <div class="text-primary/10 hidden h-4 w-px bg-current sm:block" />
            <p class="text-primary/70 text-sm">Free to start</p>
            <div class="text-primary/10 hidden h-4 w-px bg-current sm:block" />
            <p class="text-primary/70 text-sm">Setup in 5 minutes</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
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
                href="https://twitter.com"
                target="_blank"
                rel="noopener noreferrer"
                class="text-muted-foreground hover:text-foreground transition"
                aria-label="Twitter"
              >
                <Icon name="lucide:twitter" class="size-5" />
              </a>
              <a
                href="https://linkedin.com"
                target="_blank"
                rel="noopener noreferrer"
                class="text-muted-foreground hover:text-foreground transition"
                aria-label="LinkedIn"
              >
                <Icon name="lucide:linkedin" class="size-5" />
              </a>
              <a
                href="https://github.com"
                target="_blank"
                rel="noopener noreferrer"
                class="text-muted-foreground hover:text-foreground transition"
                aria-label="GitHub"
              >
                <Icon name="lucide:github" class="size-5" />
              </a>
              <a
                href="https://instagram.com"
                target="_blank"
                rel="noopener noreferrer"
                class="text-muted-foreground hover:text-foreground transition"
                aria-label="Instagram"
              >
                <Icon name="lucide:instagram" class="size-5" />
              </a>
            </div>
          </div>

          <!-- Product Links -->
          <div>
            <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Product</h4>
            <ul class="space-y-3">
              <li>
                <NuxtLink
                  to="/projects"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Projects
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/links"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Short Links
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/posts"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Blog
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/web-analytics"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Analytics
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/api-consumers"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  API
                </NuxtLink>
              </li>
            </ul>
          </div>

          <!-- Resources Links -->
          <div>
            <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Resources</h4>
            <ul class="space-y-3">
              <li>
                <NuxtLink
                  to="/news"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  News
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/web-analytics/docs"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Documentation
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/demo"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Demo
                </NuxtLink>
              </li>
            </ul>
          </div>

          <!-- Company Links -->
          <div>
            <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Company</h4>
            <ul class="space-y-3">
              <li>
                <NuxtLink
                  to="/privacy"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Privacy Policy
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/terms"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Terms of Service
                </NuxtLink>
              </li>
            </ul>
          </div>

          <!-- Account Links -->
          <div>
            <h4 class="text-foreground mb-4 text-sm font-semibold tracking-tight">Account</h4>
            <ul class="space-y-3">
              <li>
                <NuxtLink
                  to="/login"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Log in
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/signup"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Sign up
                </NuxtLink>
              </li>
              <li>
                <NuxtLink
                  to="/forgot-password"
                  class="text-muted-foreground hover:text-foreground text-sm transition"
                >
                  Forgot Password
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
  </div>
</template>

<script setup>
definePageMeta({
  layout: "default",
});

usePageMeta("home");

// Check if hero image exists
const heroImageExists = ref(false);

// Feature tabs for hero section
const featureTabs = [
  { label: "Projects", icon: "lucide:folder-kanban", iconBg: "bg-orange-500" },
  { label: "Short Links", icon: "lucide:link", iconBg: "bg-green-500" },
  { label: "Analytics", icon: "lucide:bar-chart-3", iconBg: "bg-blue-500" },
  { label: "Blog", icon: "lucide:file-text", iconBg: "bg-purple-500" },
];

// Trusted by logos (placeholder text)
const trustedLogos = [
  "TechCorp",
  "StartupXYZ",
  "MediaGroup",
  "EventPro",
  "CreativeStudio",
  "DigitalAgency",
];

// Project management features
const projectFeatures = [
  {
    title: "Multi-Project Management",
    description:
      "Run unlimited projects with their own settings, teams, and visibility controls. No more mixing things up.",
    icon: "lucide:layers",
  },
  {
    title: "Team Collaboration",
    description:
      "Bring your crew in with role-based access. Everyone sees what they need to see, nothing more.",
    icon: "lucide:users",
  },
  {
    title: "Project Analytics",
    description: "Know exactly how each project is doing. Real numbers, not guesses.",
    icon: "lucide:activity",
  },
];

// Link features
const linkFeatures = [
  {
    title: "Custom Short Links",
    description: "Create memorable links with your own slugs. No more random string soup.",
    icon: "lucide:link-2",
  },
  {
    title: "QR Code Generator",
    description: "Every link gets a QR code automatically. Print it, share it, stick it on things.",
    icon: "lucide:qr-code",
  },
  {
    title: "Click Analytics",
    description: "See who's clicking, from where, on what device. The full picture.",
    icon: "lucide:mouse-pointer-click",
  },
];

// Content features
const contentFeatures = [
  {
    title: "Rich Text Editor",
    description: "A beautiful editor that just works. Format text, add images, embed stuff. Easy.",
    icon: "lucide:edit-3",
  },
  {
    title: "Scheduled Publishing",
    description: "Write now, publish later. Set it and forget it until it goes live.",
    icon: "lucide:calendar-clock",
  },
  {
    title: "Categories & Tags",
    description: "Keep your content organized so your readers can find what they're looking for.",
    icon: "lucide:tags",
  },
];

// API features
const apiFeatures = [
  {
    title: "RESTful API",
    description: "Well-documented endpoints that actually make sense. Your devs will thank you.",
    icon: "lucide:code",
  },
  {
    title: "Google Analytics",
    description: "Already using GA? Connect it and get even deeper insights.",
    icon: "lucide:line-chart",
  },
  {
    title: "API Key Management",
    description: "Create keys, set limits, whitelist origins. Full control over who accesses what.",
    icon: "lucide:key",
  },
];

// Stats
const stats = [
  { value: "10K+", label: "Links created" },
  { value: "1M+", label: "Clicks tracked" },
  { value: "500+", label: "Active projects" },
  { value: "99.9%", label: "Uptime" },
];

// All features grid
const allFeatures = [
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
