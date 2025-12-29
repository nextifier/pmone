<template>
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
</template>
