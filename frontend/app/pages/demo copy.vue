<template>
  <div class="bg-background relative min-h-screen w-full overflow-x-hidden">
    <!-- CodePen Reference Comparison Section -->
    <section class="bg-muted/50 flex flex-col items-center justify-center px-8 py-20">
      <div class="flex items-center justify-center [--global-size:clamp(2rem,4vw,5rem)]">
        <GlassButton variant="outline" size="inherit">Generate</GlassButton>
      </div>
    </section>

    <section
      class="flex flex-col items-center justify-center bg-gradient-to-r from-pink-300 via-purple-300 to-indigo-400 px-8 py-20"
    >
      <div class="flex items-center justify-center [--global-size:clamp(2rem,4vw,5rem)]">
        <GlassButton variant="outline" size="inherit">Generate</GlassButton>
      </div>
    </section>

    <section class="relative isolate flex flex-col items-center justify-center">
      <NuxtImg
        src="/img/wonyoungdollz_1766704382_3795713926466650278_60528094907.jpg"
        alt=""
        class="size-full object-cover"
      />

      <div
        class="absolute inset-0 z-10 flex items-center justify-center [--global-size:clamp(2rem,4vw,5rem)]"
      >
        <GlassButton variant="outline" size="inherit">Generate</GlassButton>
      </div>
    </section>

    <!-- Main Content -->
    <div class="relative z-10 flex flex-col items-center gap-16 px-8 py-16">
      <!-- All Variants -->
      <section class="flex w-full max-w-[1200px] flex-col gap-8">
        <h2 class="mb-2 text-center text-xl font-semibold">All Variants</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary">Secondary</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">secondary</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive">Destructive</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">destructive</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline">Destructive</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">destructive-outline</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline">Outline</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">outline</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost">Ghost</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">ghost</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="link">Link</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">link</span>
          </div>
        </div>

        <!-- Invert Variants on Dark Background (Light Mode) / Light Background (Dark Mode) -->
        <h2 class="mb-2 text-center text-xl font-semibold">Invert Variants</h2>
        <div
          class="bg-foreground grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-8 rounded-3xl p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="invert">Invert</GlassButton>
            <span class="text-background font-mono text-xs">invert</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline-invert">Outline Invert</GlassButton>
            <span class="text-background font-mono text-xs">outline-invert</span>
          </div>
        </div>
      </section>

      <!-- Sizes for Each Variant -->
      <section class="flex w-full max-w-[1200px] flex-col gap-8">
        <h2 class="mb-2 text-center text-xl font-semibold">Sizes - Default Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="xs">Extra Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="sm">Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="lg">Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="xl">Extra Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Sizes - Outline Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="xs">Extra Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="sm">Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="lg">Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="xl">Extra Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Sizes - Destructive Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="xs">Extra Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="sm">Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="lg">Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="xl">Extra Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Sizes - Destructive Outline Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="xs">Extra Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="sm">Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="lg">Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="xl">Extra Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Sizes - Secondary Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="xs">Extra Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="sm">Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="lg">Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="xl">Extra Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Sizes - Ghost Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="xs">Extra Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="sm">Small</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="default">Default</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="lg">Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="xl">Extra Large</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">xl</span>
          </div>
        </div>
      </section>

      <!-- Icon Sizes -->
      <section class="flex w-full max-w-[1200px] flex-col gap-8">
        <h2 class="mb-2 text-center text-xl font-semibold">Icon Sizes - Default Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="icon-xs">
              <Plus class="size-3" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="icon-sm">
              <Plus class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="icon">
              <Plus class="size-5" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="icon-lg">
              <Plus class="size-6" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" size="icon-xl">
              <Plus class="size-7" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Icon Sizes - Outline Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="icon-xs">
              <Plus class="size-3" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="icon-sm">
              <Plus class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="icon">
              <Plus class="size-5" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="icon-lg">
              <Plus class="size-6" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" size="icon-xl">
              <Plus class="size-7" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Icon Sizes - Destructive Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="icon-xs">
              <Trash class="size-3" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="icon-sm">
              <Trash class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="icon">
              <Trash class="size-5" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="icon-lg">
              <Trash class="size-6" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" size="icon-xl">
              <Trash class="size-7" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">
          Icon Sizes - Destructive Outline Variant
        </h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="icon-xs">
              <Trash class="size-3" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="icon-sm">
              <Trash class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="icon">
              <Trash class="size-5" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="icon-lg">
              <Trash class="size-6" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" size="icon-xl">
              <Trash class="size-7" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Icon Sizes - Secondary Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="icon-xs">
              <Settings class="size-3" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="icon-sm">
              <Settings class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="icon">
              <Settings class="size-5" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="icon-lg">
              <Settings class="size-6" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" size="icon-xl">
              <Settings class="size-7" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xl</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">Icon Sizes - Ghost Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="icon-xs">
              <X class="size-3" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xs</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="icon-sm">
              <X class="size-4" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-sm</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="icon">
              <X class="size-5" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="icon-lg">
              <X class="size-6" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-lg</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" size="icon-xl">
              <X class="size-7" />
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">icon-xl</span>
          </div>
        </div>
      </section>

      <!-- States for Each Variant -->
      <section class="flex w-full max-w-[1200px] flex-col gap-8">
        <h2 class="mb-2 text-center text-xl font-semibold">States - Default Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">States - Outline Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">States - Destructive Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">States - Destructive Outline Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">States - Secondary Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">States - Ghost Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>

        <h2 class="mb-2 text-center text-xl font-semibold">States - Link Variant</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="link">Normal</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">normal</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="link" disabled>Disabled</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">disabled</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="link" :loading="true">Loading</GlassButton>
            <span class="text-muted-foreground font-mono text-xs">loading</span>
          </div>
        </div>
      </section>

      <!-- Buttons with Icons and Text -->
      <section class="flex w-full max-w-[1200px] flex-col gap-8">
        <h2 class="mb-2 text-center text-xl font-semibold">With Icon and Text</h2>
        <div
          class="border-border/30 grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-8 rounded-3xl border p-8"
        >
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="default">
              <Plus class="size-4" />
              <span>Add Item</span>
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">default</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="outline">
              <Download class="size-4" />
              <span>Download</span>
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">outline</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive">
              <Trash class="size-4" />
              <span>Delete</span>
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">destructive</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="destructive-outline">
              <Trash class="size-4" />
              <span>Remove</span>
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">destructive-outline</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="secondary">
              <Settings class="size-4" />
              <span>Settings</span>
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">secondary</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <GlassButton variant="ghost">
              <Menu class="size-4" />
              <span>Menu</span>
            </GlassButton>
            <span class="text-muted-foreground font-mono text-xs">ghost</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { GlassButton } from "@/components/ui/glass-button";
import { Download, Menu, Plus, Settings, Trash, X } from "lucide-vue-next";
</script>
