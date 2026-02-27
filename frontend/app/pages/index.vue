<template>
  <div>
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
          class="flex flex-col items-center justify-center pt-10 pb-12 text-center lg:pt-14 lg:pb-20"
        >
          <!-- Badge -->
          <NuxtLink
            to="/news"
            class="group bg-muted/50 ring-border hover:bg-muted mb-6 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm ring-1 transition"
          >
            <span class="text-muted-foreground tracking-tight"
              >Event management, project tools, and analytics</span
            >
            <span
              class="text-primary inline-flex items-center gap-1 font-medium transition group-hover:translate-x-0.5"
            >
              <Icon name="lucide:arrow-right" class="size-3.5" />
            </span>
          </NuxtLink>

          <!-- Headline -->
          <h1
            class="text-foreground max-w-3xl text-4xl leading-[1.2]! font-medium tracking-tighter text-balance sm:text-6xl"
          >
            One platform. Every moving part of your event, handled.
          </h1>

          <!-- Subtitle -->
          <p
            class="text-body mt-5 max-w-2xl text-center text-base leading-relaxed tracking-tight text-pretty sm:text-lg"
          >
            Projects, exhibitors, orders, links, analytics, content. All under one roof. Built for
            event organizers who need less tabs and more clarity.
          </p>

          <!-- CTA -->
          <div class="mt-8">
            <NuxtLink
              to="/signup"
              class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-xl px-5 py-2.5 text-sm font-medium tracking-tight transition active:scale-95"
              v-ripple
            >
              Get started
            </NuxtLink>
          </div>

          <!-- Feature pills -->
          <div class="mt-12 flex flex-wrap items-center justify-center gap-2">
            <div
              v-for="pill in heroPills"
              :key="pill.label"
              class="ring-border bg-background inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium tracking-tight ring-1"
            >
              <span class="flex size-5 items-center justify-center rounded-md" :class="pill.bg">
                <Icon :name="pill.icon" class="size-3 text-white" />
              </span>
              {{ pill.label }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Core Features: Bento Grid -->
    <section class="py-20 lg:py-28">
      <div class="container">
        <div class="mb-12 max-w-xl lg:mb-16">
          <p class="section-subtitle text-sm">Core platform</p>
          <h2 class="section-title mt-3">The stuff you'll use every day.</h2>
          <p class="section-description mt-4">
            Everything you need in one place. Each tool designed to talk to the others.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <NuxtLink
            v-for="feature in coreFeatures"
            :key="feature.title"
            :to="feature.link"
            class="group bg-card ring-border relative flex flex-col rounded-xl p-6 ring-1 transition hover:shadow-md"
          >
            <div class="mb-4 flex items-center gap-3">
              <div
                class="flex size-9 items-center justify-center rounded-lg transition"
                :class="feature.iconBg"
              >
                <Icon :name="feature.icon" class="size-4 text-white" />
              </div>
              <h3 class="text-foreground text-base font-medium tracking-tighter">
                {{ feature.title }}
              </h3>
            </div>
            <p class="text-muted-foreground text-sm leading-relaxed tracking-tight">
              {{ feature.description }}
            </p>
            <div class="mt-4 flex flex-wrap gap-1.5">
              <span
                v-for="tag in feature.tags"
                :key="tag"
                class="bg-muted text-muted-foreground rounded-md px-2 py-0.5 text-xs tracking-tight"
              >
                {{ tag }}
              </span>
            </div>
            <div
              class="text-muted-foreground mt-4 flex items-center gap-1 text-sm tracking-tight transition group-hover:translate-x-0.5"
            >
              <span>Explore</span>
              <Icon name="lucide:arrow-right" class="size-3.5" />
            </div>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- Event Management: Spotlight -->
    <section class="bg-muted/30 py-20 lg:py-28">
      <div class="container">
        <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
          <!-- Left: Copy -->
          <div class="max-w-xl shrink-0 lg:w-1/2">
            <p class="section-subtitle text-sm">Event management</p>
            <h2 class="section-title mt-3">
              Events have enough chaos already. Your tools shouldn't add more.
            </h2>
            <p class="section-description mt-4">
              Create events under any project. Manage brands, booth assignments, product catalogs,
              and orders. Your exhibitors get their own portal. You keep the overview.
            </p>

            <div class="mt-8 space-y-4">
              <div v-for="item in eventHighlights" :key="item.title" class="flex gap-3">
                <div class="bg-muted flex size-8 shrink-0 items-center justify-center rounded-lg">
                  <Icon :name="item.icon" class="text-muted-foreground size-4" />
                </div>
                <div>
                  <p class="text-foreground text-sm font-medium tracking-tighter">
                    {{ item.title }}
                  </p>
                  <p class="text-muted-foreground text-sm leading-relaxed tracking-tight">
                    {{ item.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Coming Soon Features Grid -->
          <div class="flex-1">
            <p class="text-muted-foreground mb-4 text-sm font-medium tracking-tight">
              Event features
            </p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <div
                v-for="item in eventFeatures"
                :key="item.label"
                class="bg-card ring-border group flex flex-col items-center gap-2 rounded-xl p-4 text-center ring-1 transition hover:shadow-md"
              >
                <div
                  class="bg-muted flex size-9 items-center justify-center rounded-lg transition group-hover:bg-orange-500/10"
                >
                  <Icon
                    :name="item.icon"
                    class="text-muted-foreground size-4 transition group-hover:text-orange-500"
                  />
                </div>
                <span class="text-foreground text-xs font-medium tracking-tight sm:text-sm">{{
                  item.label
                }}</span>
                <span
                  v-if="item.soon"
                  class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[10px] tracking-tight sm:text-xs"
                >
                  Soon
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Links & Analytics -->
    <section class="py-20 lg:py-28">
      <div class="container">
        <div class="flex flex-col gap-12 lg:flex-row-reverse lg:items-start lg:gap-16">
          <!-- Right: Copy -->
          <div class="max-w-xl shrink-0 lg:w-1/2">
            <p class="section-subtitle text-sm">Links & Analytics</p>
            <h2 class="section-title mt-3">Short links with memory.</h2>
            <p class="section-description mt-4">
              Create branded short links, auto-generate QR codes, and know exactly where your clicks
              come from. Device, location, referrer. The full picture.
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <div v-for="item in linkHighlights" :key="item.title" class="flex gap-3">
                <Icon :name="item.icon" class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                <div>
                  <p class="text-foreground text-sm font-medium tracking-tighter">
                    {{ item.title }}
                  </p>
                  <p class="text-muted-foreground text-sm leading-relaxed tracking-tight">
                    {{ item.description }}
                  </p>
                </div>
              </div>
            </div>

            <NuxtLink
              to="/links"
              class="text-foreground group mt-6 inline-flex items-center gap-1.5 text-sm font-medium tracking-tight transition"
            >
              Try Short Links
              <Icon
                name="lucide:arrow-right"
                class="size-3.5 transition-transform group-hover:translate-x-0.5"
              />
            </NuxtLink>
          </div>

          <!-- Left: Stats cards -->
          <div class="flex-1">
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="bg-card ring-border rounded-xl p-5 ring-1">
                <div class="flex items-center gap-2">
                  <div class="bg-success/10 flex size-8 items-center justify-center rounded-full">
                    <Icon name="lucide:trending-up" class="text-success-foreground size-4" />
                  </div>
                  <span class="text-muted-foreground text-sm tracking-tight">Traffic</span>
                </div>
                <p class="text-foreground mt-3 text-2xl font-medium tracking-tighter">+247%</p>
                <p class="text-muted-foreground text-sm tracking-tight">this month</p>
              </div>
              <div class="bg-card ring-border rounded-xl p-5 ring-1">
                <div class="flex items-center gap-2">
                  <div class="bg-info/10 flex size-8 items-center justify-center rounded-full">
                    <Icon name="lucide:mouse-pointer-click" class="text-info-foreground size-4" />
                  </div>
                  <span class="text-muted-foreground text-sm tracking-tight">Clicks</span>
                </div>
                <p class="text-foreground mt-3 text-2xl font-medium tracking-tighter">12.5K</p>
                <p class="text-muted-foreground text-sm tracking-tight">this week</p>
              </div>
              <div class="bg-card ring-border rounded-xl p-5 ring-1">
                <div class="flex items-center gap-2">
                  <div class="bg-warning/10 flex size-8 items-center justify-center rounded-full">
                    <Icon name="lucide:qr-code" class="text-warning-foreground size-4" />
                  </div>
                  <span class="text-muted-foreground text-sm tracking-tight">QR Scans</span>
                </div>
                <p class="text-foreground mt-3 text-2xl font-medium tracking-tighter">3.8K</p>
                <p class="text-muted-foreground text-sm tracking-tight">total scans</p>
              </div>
              <div class="bg-card ring-border rounded-xl p-5 ring-1">
                <div class="flex items-center gap-2">
                  <div
                    class="bg-destructive/10 flex size-8 items-center justify-center rounded-full"
                  >
                    <Icon name="lucide:globe" class="text-destructive-foreground size-4" />
                  </div>
                  <span class="text-muted-foreground text-sm tracking-tight">Countries</span>
                </div>
                <p class="text-foreground mt-3 text-2xl font-medium tracking-tighter">24</p>
                <p class="text-muted-foreground text-sm tracking-tight">unique locations</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Content Publishing -->
    <section class="bg-muted/30 py-20 lg:py-28">
      <div class="container">
        <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
          <div class="max-w-xl shrink-0 lg:w-1/2">
            <p class="section-subtitle text-sm">Content publishing</p>
            <h2 class="section-title mt-3">Write it. Schedule it. Done.</h2>
            <p class="section-description mt-4">
              A clean rich-text editor with image uploads, categories, tags, and scheduled
              publishing. No plugin hell. No theme conflicts. Just writing.
            </p>

            <div class="mt-8 space-y-3">
              <div
                v-for="item in contentHighlights"
                :key="item.label"
                class="flex items-center gap-3"
              >
                <Icon name="lucide:check" class="text-success-foreground size-4 shrink-0" />
                <span class="text-body text-sm tracking-tight">{{ item.label }}</span>
              </div>
            </div>

            <NuxtLink
              to="/posts"
              class="text-foreground group mt-6 inline-flex items-center gap-1.5 text-sm font-medium tracking-tight transition"
            >
              Explore Content
              <Icon
                name="lucide:arrow-right"
                class="size-3.5 transition-transform group-hover:translate-x-0.5"
              />
            </NuxtLink>
          </div>

          <!-- Right: Simulated editor -->
          <div class="flex-1">
            <div class="bg-card ring-border overflow-hidden rounded-xl ring-1">
              <!-- Toolbar -->
              <div class="border-border flex items-center gap-1 border-b px-4 py-2.5">
                <div
                  v-for="tool in editorTools"
                  :key="tool"
                  class="bg-muted text-muted-foreground flex size-7 items-center justify-center rounded-md"
                >
                  <Icon :name="tool" class="size-3.5" />
                </div>
              </div>
              <!-- Content -->
              <div class="p-5 sm:p-6">
                <div class="bg-muted mb-3 h-3 w-3/4 rounded" />
                <div class="bg-muted/70 mb-2 h-2.5 w-full rounded" />
                <div class="bg-muted/70 mb-2 h-2.5 w-full rounded" />
                <div class="bg-muted/70 mb-2 h-2.5 w-5/6 rounded" />
                <div class="bg-muted/50 mt-5 aspect-video w-full rounded-lg" />
                <div class="bg-muted/70 mt-5 mb-2 h-2.5 w-full rounded" />
                <div class="bg-muted/70 mb-2 h-2.5 w-4/5 rounded" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- API & Integrations -->
    <section class="py-20 lg:py-28">
      <div class="container">
        <div class="mb-12 max-w-xl lg:mb-16">
          <p class="section-subtitle text-sm">Integrations</p>
          <h2 class="section-title mt-3">Connects to the tools you already use.</h2>
          <p class="section-description mt-4">
            Google Analytics, custom webhooks, REST API. Build what you need on top of PM One.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="item in integrations"
            :key="item.title"
            class="group bg-card ring-border rounded-xl p-5 ring-1 transition hover:shadow-md"
          >
            <div class="mb-3">
              <Icon
                :name="item.icon"
                class="text-muted-foreground size-5 transition group-hover:text-orange-500"
              />
            </div>
            <h3 class="text-foreground text-sm font-medium tracking-tighter">{{ item.title }}</h3>
            <p class="text-muted-foreground mt-1.5 text-sm leading-relaxed tracking-tight">
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Platform Features Grid -->
    <section class="bg-muted/30 py-20 lg:py-28">
      <div class="container">
        <div class="mb-12 text-center lg:mb-16">
          <h2 class="section-title">Built for real work.</h2>
          <p class="section-description mx-auto mt-4">
            The details that make the difference when you're managing events at scale.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <div
            v-for="feature in platformFeatures"
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
              <h3 class="text-foreground text-sm font-medium tracking-tighter">
                {{ feature.title }}
              </h3>
            </div>
            <p class="text-muted-foreground text-sm leading-relaxed tracking-tight">
              {{ feature.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Numbers -->
    <section class="border-border/50 border-y py-16">
      <div class="container">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
          <div v-for="stat in stats" :key="stat.label" class="text-center">
            <p class="text-primary text-4xl font-medium tracking-tighter lg:text-5xl">
              {{ stat.value }}
            </p>
            <p class="text-muted-foreground mt-2 text-sm tracking-tight">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Who it's for -->
    <section class="bg-muted/30 py-20 lg:py-28">
      <div class="container">
        <div class="mb-12 text-center lg:mb-16">
          <p class="section-subtitle text-sm">Built for</p>
          <h2 class="section-title mt-3">Different roles. Same platform.</h2>
          <p class="section-description mx-auto mt-4">
            Whether you're running the show or exhibiting at one, PM One fits your workflow.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="persona in personas"
            :key="persona.title"
            class="bg-card ring-border rounded-xl p-6 ring-1"
          >
            <div
              class="mb-4 flex size-10 items-center justify-center rounded-lg"
              :class="persona.iconBg"
            >
              <Icon :name="persona.icon" class="size-5 text-white" />
            </div>
            <h3 class="text-foreground text-base font-medium tracking-tighter">
              {{ persona.title }}
            </h3>
            <p class="text-muted-foreground mt-2 text-sm leading-relaxed tracking-tight">
              {{ persona.description }}
            </p>
            <ul class="mt-4 space-y-2">
              <li v-for="point in persona.points" :key="point" class="flex items-start gap-2">
                <Icon
                  name="lucide:check"
                  class="text-success-foreground mt-0.5 size-3.5 shrink-0"
                />
                <span class="text-body text-sm tracking-tight">{{ point }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonial -->
    <section class="py-20 lg:py-28">
      <div class="container">
        <div class="mx-auto max-w-3xl text-center">
          <Icon name="lucide:quote" class="text-muted-foreground/30 mx-auto size-10" />
          <blockquote
            class="text-foreground mt-6 text-xl leading-relaxed font-medium tracking-tighter sm:text-2xl"
          >
            "{{ testimonial.quote }}"
          </blockquote>
          <div class="mt-6 flex items-center justify-center gap-3">
            <div class="bg-muted flex size-10 items-center justify-center rounded-full">
              <Icon name="lucide:user" class="text-muted-foreground size-5" />
            </div>
            <div class="text-left">
              <p class="text-foreground text-sm font-medium tracking-tight">
                {{ testimonial.author }}
              </p>
              <p class="text-muted-foreground text-sm tracking-tight">
                {{ testimonial.role }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="relative overflow-hidden py-20 lg:py-28">
      <div class="relative container">
        <div class="flex flex-col items-center text-center">
          <h2
            class="max-w-2xl text-4xl leading-tight font-medium tracking-tighter sm:text-5xl lg:text-6xl"
          >
            Less tools. More focus.
          </h2>
          <p class="text-body mt-4 max-w-lg text-base tracking-tight sm:text-lg">
            Try it with your next event. Free plan, no credit card, 5-minute setup.
          </p>

          <div class="mt-8 flex items-center gap-3">
            <NuxtLink
              to="/signup"
              class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-xl px-5 py-2.5 text-sm font-medium tracking-tight transition active:scale-95"
              v-ripple
            >
              Create free account
            </NuxtLink>
          </div>

          <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <div class="text-primary/70 flex items-center gap-2">
              <Icon name="hugeicons:star-circle" class="size-5 shrink-0" />
              <span class="text-sm tracking-tight">5.0 Rating</span>
            </div>
            <div class="bg-border hidden h-4 w-px sm:block" />
            <p class="text-primary/70 text-sm tracking-tight">No credit card</p>
            <div class="bg-border hidden h-4 w-px sm:block" />
            <p class="text-primary/70 text-sm tracking-tight">Setup in 5 minutes</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-background border-border border-t py-16">
      <div class="container">
        <div class="grid gap-10 lg:grid-cols-6">
          <div class="lg:col-span-2">
            <NuxtLink to="/">
              <Logo class="text-primary h-6" />
            </NuxtLink>
            <p class="text-muted-foreground mt-4 max-w-xs text-sm leading-relaxed tracking-tight">
              Event management and project collaboration platform. Built for teams who run events,
              manage exhibitors, and track everything in between.
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

          <div>
            <h4 class="text-foreground mb-4 text-sm font-medium tracking-tighter">Platform</h4>
            <ul class="space-y-3">
              <li v-for="link in platformLinks" :key="link.to">
                <NuxtLink
                  :to="link.to"
                  class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
                >
                  {{ link.label }}
                </NuxtLink>
              </li>
            </ul>
          </div>

          <div>
            <h4 class="text-foreground mb-4 text-sm font-medium tracking-tighter">Events</h4>
            <ul class="space-y-3">
              <li v-for="link in eventLinks" :key="link.label">
                <NuxtLink
                  :to="link.to"
                  class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
                >
                  {{ link.label }}
                </NuxtLink>
              </li>
            </ul>
          </div>

          <div>
            <h4 class="text-foreground mb-4 text-sm font-medium tracking-tighter">Resources</h4>
            <ul class="space-y-3">
              <li v-for="link in resourceLinks" :key="link.to">
                <NuxtLink
                  :to="link.to"
                  class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
                >
                  {{ link.label }}
                </NuxtLink>
              </li>
            </ul>
          </div>

          <div>
            <h4 class="text-foreground mb-4 text-sm font-medium tracking-tighter">Account</h4>
            <ul class="space-y-3">
              <li v-for="link in accountLinks" :key="link.to">
                <NuxtLink
                  :to="link.to"
                  class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
                >
                  {{ link.label }}
                </NuxtLink>
              </li>
            </ul>
          </div>
        </div>

        <div
          class="border-border mt-12 flex flex-col items-center justify-between gap-4 border-t pt-8 md:flex-row"
        >
          <div class="flex items-center gap-2">
            <span class="bg-success size-2 rounded-full" />
            <span class="text-muted-foreground text-sm tracking-tight"
              >All systems operational</span
            >
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            &copy; {{ new Date().getFullYear() }} PM One. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
definePageMeta({});

usePageMeta(null, { title: "PM One", withoutTitleTemplate: true });

// Hero pills
const heroPills = [
  { label: "Projects", icon: "lucide:folder-kanban", bg: "bg-orange-500" },
  { label: "Events", icon: "lucide:calendar-days", bg: "bg-red-500" },
  { label: "Exhibitors", icon: "lucide:store", bg: "bg-emerald-500" },
  { label: "Short Links", icon: "lucide:link", bg: "bg-green-500" },
  { label: "Analytics", icon: "lucide:bar-chart-3", bg: "bg-blue-500" },
  { label: "Blog", icon: "lucide:file-text", bg: "bg-purple-500" },
  { label: "Tasks", icon: "lucide:square-check", bg: "bg-sky-500" },
  { label: "Inbox", icon: "lucide:inbox", bg: "bg-pink-500" },
];

// Core features
const coreFeatures = [
  {
    title: "Projects",
    icon: "lucide:folder-kanban",
    iconBg: "bg-orange-500",
    link: "/projects",
    description:
      "Unlimited projects with their own teams, settings, and visibility. Kanban tasks, member roles, custom fields, and activity logs built in.",
    tags: ["Members", "Custom Fields", "Activity", "Settings"],
  },
  {
    title: "Events",
    icon: "lucide:calendar-days",
    iconBg: "bg-red-500",
    link: "/projects",
    description:
      "Create events under any project. Handle dates, venues, product catalogs, and order forms. Multi-day or single-day, both work.",
    tags: ["Products", "Orders", "Schedules", "Multi-day"],
  },
  {
    title: "Brands & Exhibitors",
    icon: "lucide:store",
    iconBg: "bg-emerald-500",
    link: "/brands",
    description:
      "Onboard exhibitors, assign them to events, manage booth details, and let them submit marketing materials through their own portal.",
    tags: ["Booth", "Portal", "Import/Export", "Invites"],
  },
  {
    title: "Short Links",
    icon: "lucide:link",
    iconBg: "bg-green-500",
    link: "/links",
    description:
      "Branded short URLs with auto-generated QR codes. Track clicks by device, country, and referrer. Static QR generator included.",
    tags: ["QR Codes", "Click Analytics", "Custom Slugs"],
  },
  {
    title: "Web Analytics",
    icon: "lucide:bar-chart-3",
    iconBg: "bg-blue-500",
    link: "/web-analytics",
    description:
      "First-party analytics that actually respect privacy. Page views, traffic sources, device breakdowns. Connect Google Analytics for deeper data.",
    tags: ["GA Integration", "Pageviews", "Traffic Sources"],
  },
  {
    title: "Content & Blog",
    icon: "lucide:file-text",
    iconBg: "bg-purple-500",
    link: "/posts",
    description:
      "Rich text editor with images, embeds, and formatting. Scheduled publishing, categories, tags. Per-post analytics included.",
    tags: ["Rich Editor", "Scheduling", "Categories"],
  },
  {
    title: "Tasks",
    icon: "lucide:square-check",
    iconBg: "bg-sky-500",
    link: "/projects",
    description:
      "Assign work, set priorities, track deadlines. List view or kanban. Drag to reorder, click to update. Works inside any project.",
    tags: ["Kanban", "Priorities", "Assignees", "Due Dates"],
  },
  {
    title: "Inbox",
    icon: "lucide:inbox",
    iconBg: "bg-pink-500",
    link: "/inbox",
    description:
      "Every contact form submission, exhibitor inquiry, and message lands here. Read, reply, and mark resolved. Nothing gets lost.",
    tags: ["Inquiries", "Replies", "Read/Unread", "Filters"],
  },
];

// Event management highlights
const eventHighlights = [
  {
    title: "Brand Portal",
    icon: "lucide:layout-dashboard",
    description: "Exhibitors log in and manage their own brand profiles, booth info, and orders.",
  },
  {
    title: "Product & Order System",
    icon: "lucide:shopping-cart",
    description: "Define event products, let exhibitors place orders, track everything.",
  },
  {
    title: "Bulk Import",
    icon: "lucide:upload",
    description: "Import hundreds of brands or exhibitors via CSV. Export any time.",
  },
];

// Event feature grid
const eventFeatures = [
  { label: "Brands", icon: "lucide:store", soon: false },
  { label: "Orders", icon: "lucide:shopping-cart", soon: false },
  { label: "Products", icon: "lucide:package", soon: false },
  { label: "Rundown", icon: "lucide:list-ordered", soon: true },
  { label: "Tickets", icon: "lucide:ticket", soon: true },
  { label: "Programs", icon: "lucide:presentation", soon: true },
  { label: "FAQ", icon: "lucide:help-circle", soon: true },
  { label: "Partners", icon: "lucide:handshake", soon: true },
  { label: "Gallery", icon: "lucide:image", soon: true },
  { label: "Guests", icon: "lucide:mic-2", soon: true },
  { label: "Promotion", icon: "lucide:megaphone", soon: false },
  { label: "Inquiries", icon: "lucide:mail", soon: false },
];

// Link highlights
const linkHighlights = [
  {
    title: "Custom Slugs",
    icon: "lucide:link-2",
    description: "Memorable URLs, not random strings.",
  },
  {
    title: "Auto QR Codes",
    icon: "lucide:qr-code",
    description: "Every link gets one. Download or print.",
  },
  {
    title: "Click Analytics",
    icon: "lucide:mouse-pointer-click",
    description: "Device, location, referrer. Full picture.",
  },
  {
    title: "Static QR",
    icon: "lucide:scan",
    description: "Generate QR codes for any URL. No account needed.",
  },
];

// Content highlights
const contentHighlights = [
  { label: "Rich text editor with image uploads and embeds" },
  { label: "Scheduled publishing. Write now, go live later" },
  { label: "Categories and tags for organized content" },
  { label: "Per-post analytics so you see what resonates" },
  { label: "Autosave so you never lose a draft" },
  { label: "Multi-language support" },
];

// Editor toolbar icons
const editorTools = [
  "lucide:bold",
  "lucide:italic",
  "lucide:underline",
  "lucide:heading-1",
  "lucide:heading-2",
  "lucide:list",
  "lucide:list-ordered",
  "lucide:image",
  "lucide:link",
  "lucide:align-left",
];

// Integrations
const integrations = [
  {
    title: "REST API",
    icon: "lucide:code",
    description: "Well-documented endpoints. Create keys, set rate limits, whitelist origins.",
  },
  {
    title: "Google Analytics",
    icon: "lucide:line-chart",
    description: "Connect your GA properties and pull data directly into PM One dashboards.",
  },
  {
    title: "Exchange Rate",
    icon: "lucide:coins",
    description: "Built-in currency converter for international events and multi-currency orders.",
  },
];

// Platform features
const platformFeatures = [
  {
    title: "Two-Factor Auth",
    description: "TOTP-based 2FA for every account. One tap to enable.",
    icon: "lucide:shield-check",
  },
  {
    title: "Roles & Permissions",
    description: "Granular access control. Master, staff, exhibitor. Each sees what they need.",
    icon: "lucide:user-cog",
  },
  {
    title: "User Management",
    description: "Invite users, assign roles, import via CSV, manage from one table.",
    icon: "lucide:users",
  },
  {
    title: "Activity Logs",
    description: "Full audit trail. Know who changed what, and when.",
    icon: "lucide:history",
  },
  {
    title: "Media Library",
    description: "Upload, organize, and optimize images automatically with Spatie Media.",
    icon: "lucide:image",
  },
  {
    title: "Dark Mode",
    description: "Switch between light and dark. Persists across sessions.",
    icon: "lucide:moon",
  },
  {
    title: "Import / Export",
    description: "Bulk operations with CSV and Excel. Brands, users, exhibitors. All supported.",
    icon: "lucide:download",
  },
  {
    title: "Multi-language",
    description: "Interface available in multiple languages. Content translation support built in.",
    icon: "lucide:languages",
  },
];

// Personas
const personas = [
  {
    title: "Event Organizers",
    icon: "lucide:calendar-check",
    iconBg: "bg-orange-500",
    description:
      "You're running expos, conferences, or trade shows. You need one place for everything, not five spreadsheets.",
    points: [
      "Create events under projects with full exhibitor management",
      "Track orders, products, and booth assignments",
      "Import hundreds of brands in one go",
    ],
  },
  {
    title: "Exhibitors",
    icon: "lucide:store",
    iconBg: "bg-emerald-500",
    description:
      "You got invited to an event. Log in, set up your brand, pick a booth, place orders. All through your own portal.",
    points: [
      "Dedicated portal with brand and booth management",
      "Submit promotion materials and company info",
      "Place and track orders for event products",
    ],
  },
  {
    title: "Project Teams",
    icon: "lucide:users",
    iconBg: "bg-blue-500",
    description:
      "You have projects to ship, content to publish, and links to track. PM One keeps it all connected.",
    points: [
      "Manage tasks with priorities, assignees, and due dates",
      "Publish blog posts and track content performance",
      "Create short links and monitor analytics",
    ],
  },
];

// Testimonial
const testimonial = {
  quote:
    "Before PM One, our event data lived in seven different places. Links in one tool, exhibitors in spreadsheets, tasks in another app. Took us two weeks to consolidate after each event. Now it takes a day.",
  author: "Rina Hartono",
  role: "Event Director, Southeast Asia Expo Group",
};

// Stats
const stats = [
  { value: "10K+", label: "Links created" },
  { value: "1M+", label: "Clicks tracked" },
  { value: "500+", label: "Active projects" },
  { value: "99.9%", label: "Uptime" },
];

// Footer data
const socialLinks = [
  { label: "Twitter", icon: "lucide:twitter", url: "https://twitter.com" },
  { label: "LinkedIn", icon: "lucide:linkedin", url: "https://linkedin.com" },
  { label: "GitHub", icon: "lucide:github", url: "https://github.com" },
  { label: "Instagram", icon: "lucide:instagram", url: "https://instagram.com" },
];

const platformLinks = [
  { label: "Projects", to: "/projects" },
  { label: "Short Links", to: "/links" },
  { label: "Blog", to: "/posts" },
  { label: "Web Analytics", to: "/web-analytics" },
  { label: "API Consumers", to: "/api-consumers" },
];

const eventLinks = [
  { label: "Brands", to: "/brands" },
  { label: "Exhibitors", to: "/exhibitors" },
  { label: "Orders", to: "/orders" },
  { label: "Exchange Rate", to: "/exchange-rate" },
];

const resourceLinks = [
  { label: "News", to: "/news" },
  { label: "Documentation", to: "/web-analytics/docs" },
  { label: "Privacy Policy", to: "/privacy" },
  { label: "Terms of Service", to: "/terms" },
];

const accountLinks = [
  { label: "Log in", to: "/login" },
  { label: "Sign up", to: "/signup" },
  { label: "Forgot Password", to: "/forgot-password" },
];
</script>
