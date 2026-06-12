<template>
  <div class="space-y-16 lg:space-y-24">
    <!-- Hero -->
    <section>
      <div class="container">
        <div class="pt-10 pb-8 lg:pt-14 lg:pb-12">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
              <h1
                class="text-foreground text-4xl leading-[1.15]! font-medium tracking-tighter text-balance sm:text-6xl"
              >
                Manage events, exhibitors, and content from one dashboard.
              </h1>
              <p
                class="text-body mt-4 max-w-xl text-base leading-relaxed tracking-tight text-pretty sm:text-lg"
              >
                Projects, brands, orders, short links, blog posts, analytics, forms, and tasks.
                Connected and searchable under one login.
              </p>
            </div>

            <div class="flex shrink-0 gap-2">
              <Button to="/login" size="lg" variant="default" v-ripple> Get Started </Button>
              <Button size="lg" variant="outline" v-ripple> Watch Demo </Button>
            </div>
          </div>

          <!-- Row 3: Hero images -->
          <div
            class="bg-muted border-border mt-8 overflow-hidden rounded-2xl border p-6 sm:rounded-4xl sm:p-10 lg:p-14"
          >
            <div class="flex flex-col -space-y-[50%]">
              <img
                v-for="(n, index) in 3"
                :key="index"
                :src="`/img/hero-img-${n}.png`"
                :alt="`PM One Dashboard Screenshot ${n}`"
                class="size-full rounded-xl shadow-2xl"
                :style="`z-index: ${3 - index}; transform: skewX(6deg) translateX(${[8, 4, 0][index]}%)`"
                width="1470"
                height="848"
              />
            </div>
          </div>
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
            <div class="flex flex-wrap items-center gap-2">
              <p class="section-subtitle">{{ feature.subtitle }}</p>
              <Badge v-if="feature.comingSoon" variant="muted">Coming soon</Badge>
            </div>
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

            <Button v-if="feature.link" variant="link" :to="feature.link" class="group mt-6 gap-1.5 px-0">
              {{ feature.linkText }}
              <Icon
                name="hugeicons:arrow-right-01"
                class="size-3.5 transition-transform group-hover:translate-x-0.5"
              />
            </Button>
          </div>

          <!-- Video column -->
          <div class="flex-1">
            <BrowserMockup :title="feature.mockupUrl" />
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
          <p
            class="text-body mt-4 max-w-lg text-base leading-relaxed tracking-tight text-pretty sm:text-lg"
          >
            We handle the setup. You run the show. One login, your whole team aligned, exhibitors
            managing themselves.
          </p>

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

const activeTab = ref("overview");

const heroTabs = [
  { id: "overview", label: "Overview" },
  { id: "events", label: "Events" },
  { id: "exhibitors", label: "Exhibitors" },
  { id: "content", label: "Content" },
  { id: "analytics", label: "Analytics" },
  { id: "links", label: "Short Links" },
];

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
    id: "websites",
    subtitle: "Event websites",
    title: "Your event website feeds itself.",
    description:
      "Update a speaker, a program, or the rundown once in PM One and every page that uses it follows. The same engine already runs Megabuild, Global AI Expo, and nine more live event sites.",
    highlights: [
      {
        icon: "hugeicons:layout-table-01",
        title: "Programs & rundown",
        description: "Schedules and session details, edited in one place.",
      },
      {
        icon: "lucide:images",
        title: "Speakers, FAQ & gallery",
        description: "Profiles, answers, and photos served straight to the site.",
      },
      {
        icon: "lucide:languages",
        title: "Five languages",
        description: "Content translates once and ships to every locale.",
      },
    ],
    mockupUrl: "megabuild.co.id",
    link: "/projects",
    linkText: "Explore projects",
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
      "Build any form with 25 field types and drag and drop ordering. Responses land in your inbox with read tracking, email notifications, and per-form analytics, plus export to Excel.",
    highlights: [
      {
        icon: "hugeicons:task-add-01",
        title: "25 field types",
        description: "Text, dropdowns, uploads, ratings, phone, links, and more.",
      },
      {
        icon: "hugeicons:inbox",
        title: "One inbox",
        description: "Submissions and contact messages with read and unread tracking.",
      },
      {
        icon: "hugeicons:link-forward",
        title: "Analytics & notifications",
        description: "Completion stats per form, and an email when answers come in.",
      },
    ],
    mockupUrl: "pmone.id/forms",
    link: "/forms",
    linkText: "Explore forms",
  },
  {
    id: "hotels",
    subtitle: "Hotel reservations",
    title: "Hotel bookings, with the payment part solved.",
    description:
      "Manage room inventory and rates per event, take reservations from your event site, and collect payment through Xendit or Midtrans. Guests get a proper voucher PDF without anyone touching a spreadsheet.",
    highlights: [
      {
        icon: "lucide:bed-double",
        title: "Allotments per event",
        description: "Room blocks and nightly rates, set for each event.",
      },
      {
        icon: "lucide:credit-card",
        title: "Payments built in",
        description: "Xendit and Midtrans checkout with automatic status updates.",
      },
      {
        icon: "hugeicons:file-01",
        title: "Voucher & receipt PDFs",
        description: "Branded documents sent to guests automatically.",
      },
    ],
    mockupUrl: "pmone.id/hotels",
    link: "/hotels",
    linkText: "Explore hotel reservations",
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
  {
    id: "tickets",
    subtitle: "Ticket sales",
    comingSoon: true,
    title: "Sell tickets straight from your event site.",
    description:
      "Attendees pick a tier, apply a promo code, and pay without leaving your website. Orders, quotas, and revenue tracking live in the same dashboard as everything else.",
    highlights: [
      {
        icon: "hugeicons:ticket-01",
        title: "Pricing tiers",
        description: "Early bird, regular, VIP, each with its own quota and window.",
      },
      {
        icon: "hugeicons:tags",
        title: "Promo codes",
        description: "Discounts with rules, quotas, and expiry dates.",
      },
      {
        icon: "lucide:credit-card",
        title: "Payment built in",
        description: "The same gateways that already power hotel bookings.",
      },
    ],
    mockupUrl: "pmone.id/tickets",
  },
  {
    id: "redeem",
    subtitle: "Check-in & redeem",
    comingSoon: true,
    title: "One QR at the door, attendance counted live.",
    description:
      "Every ticket comes with a QR code. Staff scan it at the gate, the system flags reuse, and the dashboard shows who is inside while the doors are still open.",
    highlights: [
      {
        icon: "hugeicons:barcode-scan",
        title: "Fast scanning",
        description: "Works from any phone camera, no special hardware.",
      },
      {
        icon: "hugeicons:qr-code",
        title: "One-time codes",
        description: "Each ticket redeems once. Duplicates get stopped at the gate.",
      },
      {
        icon: "hugeicons:activity-01",
        title: "Live attendance",
        description: "Real-time counts per gate, session, and ticket tier.",
      },
    ],
    mockupUrl: "pmone.id/check-in",
  },
  {
    id: "bizmatch",
    subtitle: "Business matching",
    comingSoon: true,
    title: "Match visitors with the right booths.",
    description:
      "Visitors and exhibitors fill in their interests, PM One pairs them up, and meetings get booked before the doors open. Exhibitors walk away with a contact list, not a fishbowl of name cards.",
    highlights: [
      {
        icon: "hugeicons:agreement-02",
        title: "Interest matching",
        description: "Pairings based on category, product, and buying intent.",
      },
      {
        icon: "hugeicons:clock-01",
        title: "Meeting scheduling",
        description: "Time slots agreed before the event starts.",
      },
      {
        icon: "hugeicons:user-check-01",
        title: "Qualified leads",
        description: "Exhibitors get a clean list of who they met and why.",
      },
    ],
    mockupUrl: "pmone.id/matching",
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
