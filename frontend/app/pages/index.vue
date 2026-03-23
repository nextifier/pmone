<template>
  <div class="space-y-16 lg:space-y-24">
    <!-- Hero -->
    <section class="relative isolate">
      <!-- Grid Background -->
      <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
        <div
          class="border-border/50 dark:border-border/30 absolute inset-x-0 top-0 mx-auto h-full max-w-6xl border-x"
        />
        <svg
          class="text-border absolute inset-0 size-full mask-[linear-gradient(to_bottom,black,black_70%,transparent)]"
        >
          <defs>
            <pattern
              id="grid-pattern"
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
          <rect width="100%" height="100%" fill="url(#grid-pattern)" />
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
          class="flex flex-col items-center justify-center pt-10 pb-8 text-center lg:pt-14 lg:pb-12"
        >
          <!-- Headline -->
          <h1
            class="text-foreground max-w-3xl text-4xl leading-[1.15]! font-medium tracking-tighter text-balance sm:text-6xl"
          >
            Manage events, exhibitors, and content from one dashboard.
          </h1>

          <!-- Subtitle -->
          <p
            class="text-body mt-4 max-w-2xl text-center text-base leading-relaxed tracking-tight text-pretty sm:text-lg"
          >
            Projects, brands, orders, short links, blog posts, analytics, forms, and tasks.
            Connected and searchable under one login.
          </p>

          <!-- CTA -->
          <div class="mt-6">
            <Button to="/signup" size="lg" class="rounded-xl" v-ripple>Get started</Button>
          </div>
        </div>

        <!-- Hero Browser Mockup -->
        <div class="mx-auto max-w-5xl">
          <HomeBrowserMockup title="pmone.id/dashboard" size="lg" />
        </div>
      </div>
    </section>

    <!-- Feature Sections -->
    <section v-for="(feature, index) in features" :key="feature.id">
      <div class="container">
        <div
          class="flex flex-col gap-8 lg:items-start lg:gap-16"
          :class="index % 2 === 0 ? 'lg:flex-row' : 'lg:flex-row-reverse'"
        >
          <!-- Text column -->
          <div class="flex max-w-xl shrink-0 flex-col items-start lg:w-1/2">
            <p class="section-subtitle">{{ feature.subtitle }}</p>
            <h2 class="section-title mt-2 leading-[1.2]!">{{ feature.title }}</h2>
            <p class="section-description mt-3">{{ feature.description }}</p>

            <div class="mt-8 space-y-4">
              <div
                v-for="highlight in feature.highlights"
                :key="highlight.title"
                class="flex gap-3"
              >
                <div class="bg-muted flex size-8 shrink-0 items-center justify-center rounded-lg">
                  <Icon :name="highlight.icon" class="text-muted-foreground size-4" />
                </div>
                <div>
                  <p class="text-foreground text-sm font-medium tracking-tighter">
                    {{ highlight.title }}
                  </p>
                  <p class="text-muted-foreground text-sm leading-relaxed tracking-tight">
                    {{ highlight.description }}
                  </p>
                </div>
              </div>
            </div>

            <Button variant="link" :to="feature.link" class="group mt-6 gap-1.5 px-0">
              {{ feature.linkText }}
              <Icon
                name="hugeicons:arrow-right-01"
                class="size-3.5 transition-transform group-hover:translate-x-0.5"
              />
            </Button>
          </div>

          <!-- Video column -->
          <div class="flex-1">
            <HomeBrowserMockup :title="feature.mockupUrl" />
          </div>
        </div>
      </div>
    </section>

    <!-- Coming Soon -->
    <section>
      <div class="container">
        <div class="mb-12 text-center lg:mb-16">
          <p class="section-subtitle text-sm">Coming soon</p>
          <h2 class="section-title mt-3">More on the way.</h2>
          <p class="section-description mx-auto mt-4">
            These features are in active development. They will roll out over the coming months.
          </p>
        </div>

        <div class="mx-auto grid max-w-4xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in comingSoon"
            :key="item.title"
            class="bg-card ring-border rounded-xl p-6 ring-1"
          >
            <Badge variant="secondary">Coming soon</Badge>
            <div class="bg-muted mt-4 flex size-10 items-center justify-center rounded-lg">
              <Icon :name="item.icon" class="text-muted-foreground size-5" />
            </div>
            <h3 class="text-foreground mt-4 text-lg font-medium tracking-tighter">
              {{ item.title }}
            </h3>
            <p
              class="text-muted-foreground mt-2 text-sm leading-relaxed tracking-tight sm:text-base"
            >
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="relative overflow-hidden">
      <div class="relative container">
        <div class="flex flex-col items-center text-center">
          <h2
            class="max-w-2xl text-4xl leading-tight font-medium tracking-tighter sm:text-5xl lg:text-6xl"
          >
            Try it with your next event.
          </h2>

          <div class="mt-8">
            <a
              href="mailto:hello@panoramamedia.co.id"
              class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center rounded-xl px-6 py-3 text-base font-medium tracking-tight transition"
              v-ripple
            >
              Contact Us
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="pt-10 lg:pt-16">
      <div class="container">
        <div class="flex flex-col gap-x-2 gap-y-10 lg:flex-row lg:gap-x-8">
          <div class="flex shrink-0 flex-col items-center gap-y-1 lg:items-start">
            <NuxtLink to="/">
              <Logo class="text-primary h-6" />
            </NuxtLink>
            <p
              class="text-muted-foreground mt-4 max-w-xs text-center text-sm leading-relaxed tracking-tight lg:text-left"
            >
              Event management and project collaboration platform for teams who run events, manage
              exhibitors, and keep everything in one place.
            </p>
          </div>

          <div
            class="grid grow grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-x-2 gap-y-10 lg:gap-x-4"
          >
            <div v-for="section in footerSections" :key="section.label" class="flex flex-col">
              <span class="text-muted-foreground px-3 text-xs font-medium tracking-tight uppercase">
                {{ section.label }}
              </span>
              <div class="mt-3 flex flex-col gap-y-0.5">
                <NuxtLink
                  v-for="link in section.links"
                  :key="link.to"
                  :to="link.to"
                  class="text-primary hover:bg-muted rounded-lg px-3 py-1 text-base font-medium tracking-[-0.04em]"
                >
                  {{ link.label }}
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-10 flex items-center justify-center gap-6">
          <a
            v-for="social in socialLinks"
            :key="social.label"
            :href="social.url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-muted-foreground hover:text-foreground transition"
            :aria-label="social.label"
          >
            <Icon :name="social.icon" class="size-6" />
          </a>
        </div>

        <div
          class="text-muted-foreground flex items-center justify-center pt-8 pb-16 text-center text-xs"
        >
          <span>
            <span class="hidden sm:inline">Copyright</span> &copy; {{ new Date().getFullYear() }} PM
            One. All rights reserved.
          </span>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
definePageMeta({});

usePageMeta(null, { title: "PM One", withoutTitleTemplate: true });

// Feature sections
const features = [
  {
    id: "events",
    subtitle: "Event management",
    title: "Set up events in minutes, manage them for months.",
    description:
      "Create events under any project. Add venues, dates, product catalogs, and order forms. Your exhibitors get their own portal while you keep the full overview.",
    highlights: [
      {
        icon: "hugeicons:map-pin",
        title: "Venues & schedules",
        description: "Set locations, halls, and time slots for any event format.",
      },
      {
        icon: "hugeicons:package",
        title: "Product catalogs",
        description: "Define event products with pricing, categories, and booth types.",
      },
      {
        icon: "hugeicons:clipboard",
        title: "Order tracking",
        description: "Follow every order from placement through to fulfillment.",
      },
    ],
    mockupUrl: "pmone.id/events",
    link: "/projects",
    linkText: "Explore events",
  },
  {
    id: "exhibitors",
    subtitle: "Exhibitor portal",
    title: "Your exhibitors handle their own setup.",
    description:
      "Exhibitors log in, update their brand profile, review booth details, submit documents, and place orders through their own dashboard. You review and approve when ready.",
    highlights: [
      {
        icon: "hugeicons:building-02",
        title: "Brand profiles",
        description: "Exhibitors update company info, logos, and descriptions on their own.",
      },
      {
        icon: "hugeicons:file-validation",
        title: "Document submission",
        description: "Collect signed agreements and required uploads per event.",
      },
      {
        icon: "hugeicons:shopping-cart-01",
        title: "Self-service orders",
        description: "Exhibitors browse the catalog and submit orders without your help.",
      },
    ],
    mockupUrl: "pmone.id/exhibitor",
    link: "/brands",
    linkText: "Explore brands",
  },
  {
    id: "content",
    subtitle: "Content publishing",
    title: "Write, schedule, publish. See what gets traction.",
    description:
      "A clean rich-text editor with image uploads, embeds, and formatting. Organize posts with categories and tags. Schedule ahead or publish on the spot. Every post shows its own view count.",
    highlights: [
      {
        icon: "hugeicons:cursor-text",
        title: "Rich editor",
        description: "Full formatting, image uploads, and embed support. Autosave included.",
      },
      {
        icon: "hugeicons:clock-01",
        title: "Scheduling",
        description: "Write today, publish next Tuesday. Set it and move on.",
      },
      {
        icon: "hugeicons:eye",
        title: "Post analytics",
        description: "See which posts pull traffic without extra setup.",
      },
    ],
    mockupUrl: "pmone.id/posts",
    link: "/posts",
    linkText: "Explore content",
  },
  {
    id: "contacts",
    subtitle: "Contacts & CRM",
    title: "Every contact, one searchable place.",
    description:
      "Add contacts one by one or import thousands via spreadsheet. Tag them, sort by business type, link to projects. Find duplicates before they pile up.",
    highlights: [
      {
        icon: "hugeicons:tags",
        title: "Tags & categories",
        description: "Organize contacts by type, industry, or any custom label.",
      },
      {
        icon: "hugeicons:copy-01",
        title: "Duplicate detection",
        description: "Scan for duplicates and merge or remove them in bulk.",
      },
      {
        icon: "hugeicons:upload-01",
        title: "Import & export",
        description: "Bring contacts in via CSV. Export any time for reports.",
      },
    ],
    mockupUrl: "pmone.id/contacts",
    link: "/contacts",
    linkText: "Explore contacts",
  },
  {
    id: "links",
    subtitle: "Short links & link pages",
    title: "Branded short links with click tracking.",
    description:
      "Create short URLs with custom slugs. Every link gets a QR code. Track clicks by device, country, and referrer. Bundle multiple links into one page for your bio or event.",
    highlights: [
      {
        icon: "hugeicons:link-02",
        title: "Custom slugs",
        description: "Pick your own URL endings instead of random strings.",
      },
      {
        icon: "hugeicons:qr-code",
        title: "Auto QR codes",
        description: "Every short link generates a QR code you can download or print.",
      },
      {
        icon: "hugeicons:layout-table-01",
        title: "Link pages",
        description: "Group links under one URL for bios and event landing pages.",
      },
    ],
    mockupUrl: "pmone.id/links",
    link: "/links",
    linkText: "Explore links",
  },
  {
    id: "forms",
    subtitle: "Form builder",
    title: "Build forms, collect responses, export the data.",
    description:
      "Drag and drop fields to create any form. Publish it with a short link. Responses land in your inbox with read/unread tracking and export to Excel.",
    highlights: [
      {
        icon: "hugeicons:task-add-01",
        title: "Drag & drop fields",
        description: "Text, email, dropdown, file upload, checkbox, and more.",
      },
      {
        icon: "hugeicons:link-forward",
        title: "Public links",
        description: "Each form gets its own URL and short link.",
      },
      {
        icon: "hugeicons:inbox",
        title: "Response inbox",
        description: "Filter, export, and track submission status in one place.",
      },
    ],
    mockupUrl: "pmone.id/forms",
    link: "/forms",
    linkText: "Explore forms",
  },
  {
    id: "analytics",
    subtitle: "Web analytics",
    title: "See where your traffic comes from.",
    description:
      "Connect your Google Analytics properties and pull the data into PM One. Page views, traffic sources, device breakdowns, and real-time visitors in one dashboard.",
    highlights: [
      {
        icon: "hugeicons:link-01",
        title: "GA4 integration",
        description: "Link GA4 properties and sync data on a schedule.",
      },
      {
        icon: "hugeicons:pie-chart",
        title: "Traffic breakdown",
        description: "See which channels bring visitors to your event sites.",
      },
      {
        icon: "hugeicons:activity-01",
        title: "Real-time visitors",
        description: "Check how many people are on your site right now.",
      },
    ],
    mockupUrl: "pmone.id/analytics",
    link: "/web-analytics",
    linkText: "Explore analytics",
  },
  {
    id: "tasks",
    subtitle: "Task management",
    title: "Assign work, track progress, hit deadlines.",
    description:
      "Create tasks inside any project. Set priorities, assign team members, add due dates. Drag to reorder. Everyone sees what they need to do next.",
    highlights: [
      {
        icon: "hugeicons:user-check-01",
        title: "Assignments",
        description: "Give tasks to specific team members with due dates.",
      },
      {
        icon: "hugeicons:signal",
        title: "Priorities",
        description: "Mark tasks as high, medium, or low.",
      },
      {
        icon: "hugeicons:folder-01",
        title: "Project-scoped",
        description: "Each project has its own task list. Nothing bleeds across.",
      },
    ],
    mockupUrl: "pmone.id/tasks",
    link: "/projects",
    linkText: "Explore tasks",
  },
];

// Coming soon features
const comingSoon = [
  {
    title: "Ticket sales",
    icon: "hugeicons:ticket-01",
    description:
      "Attendees buy tickets from your event website. Seat selection, pricing tiers, and payment processing.",
  },
  {
    title: "Check-in & redeem",
    icon: "hugeicons:barcode-scan",
    description:
      "Scan QR codes at the door. A real-time dashboard shows who arrived and who has not.",
  },
  {
    title: "Business matching",
    icon: "hugeicons:agreement-02",
    description:
      "Match exhibitors with attendees by interest and category. Meetings scheduled before the event starts.",
  },
];

// Footer data
const socialLinks = [
  { label: "Twitter", icon: "hugeicons:new-twitter", url: "https://twitter.com" },
  { label: "LinkedIn", icon: "hugeicons:linkedin-01", url: "https://linkedin.com" },
  { label: "GitHub", icon: "hugeicons:github", url: "https://github.com" },
  { label: "Instagram", icon: "hugeicons:instagram", url: "https://instagram.com" },
];

const footerSections = [
  {
    label: "Platform",
    links: [
      { label: "Projects", to: "/projects" },
      { label: "Short Links", to: "/links" },
      { label: "Blog", to: "/posts" },
      { label: "Web Analytics", to: "/web-analytics" },
      { label: "API Consumers", to: "/api-consumers" },
    ],
  },
  {
    label: "Events",
    links: [
      { label: "Brands", to: "/brands" },
      { label: "Exhibitors", to: "/exhibitors" },
      { label: "Orders", to: "/orders" },
      { label: "Exchange Rate", to: "/exchange-rate" },
    ],
  },
  {
    label: "Resources",
    links: [
      { label: "News", to: "/news" },
      { label: "Documentation", to: "/web-analytics/docs" },
      { label: "Privacy Policy", to: "/privacy" },
      { label: "Terms of Service", to: "/terms" },
    ],
  },
  {
    label: "Account",
    links: [
      { label: "Log in", to: "/login" },
      { label: "Sign up", to: "/signup" },
      { label: "Forgot Password", to: "/forgot-password" },
    ],
  },
];
</script>
