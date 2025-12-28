<template>
  <div class="overflow-x-hidden">
    <!-- Size Variants Demo -->
    <div class="border-b border-border bg-muted/30 px-8 py-12">
      <h2 class="mb-8 text-center text-2xl font-semibold">Size Variants</h2>
      <div class="flex flex-wrap items-center justify-center gap-4">
        <GlassButton variant="outline" size="xs">xs</GlassButton>
        <GlassButton variant="outline" size="sm">sm</GlassButton>
        <GlassButton variant="outline" size="default">default</GlassButton>
        <GlassButton variant="outline" size="lg">lg</GlassButton>
        <GlassButton variant="outline" size="xl">xl</GlassButton>
      </div>

      <h3 class="mb-4 mt-8 text-center text-lg font-medium text-muted-foreground">
        Icon Sizes
      </h3>
      <div class="flex flex-wrap items-center justify-center gap-4">
        <GlassButton variant="outline" size="icon-xs">
          <Icon name="lucide:plus" />
        </GlassButton>
        <GlassButton variant="outline" size="icon-sm">
          <Icon name="lucide:plus" />
        </GlassButton>
        <GlassButton variant="outline" size="icon">
          <Icon name="lucide:plus" />
        </GlassButton>
        <GlassButton variant="outline" size="icon-lg">
          <Icon name="lucide:plus" />
        </GlassButton>
        <GlassButton variant="outline" size="icon-xl">
          <Icon name="lucide:plus" />
        </GlassButton>
      </div>
    </div>

    <div class="min-h-screen-offset grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))]">
      <div class="flex flex-col items-center justify-center py-20">
        <div class="flex items-center justify-center text-[clamp(2rem,4vw,5rem)]">
          <GlassButton variant="outline" size="inherit">Generate</GlassButton>
        </div>
      </div>

      <div
        class="flex flex-col items-center justify-center bg-linear-to-br from-pink-300 via-purple-300 to-indigo-400 py-20"
      >
        <div class="flex items-center justify-center text-[clamp(2rem,4vw,5rem)]">
          <GlassButton variant="outline" size="inherit">Generate</GlassButton>
        </div>
      </div>

      <div class="relative isolate flex flex-col items-center justify-center">
        <NuxtImg
          src="/img/wonyoungdollz_1766704382_3795713926466650278_60528094907.jpg"
          alt=""
          class="size-full object-cover"
        />

        <div
          class="absolute inset-0 z-10 flex items-center justify-center text-[clamp(2rem,4vw,5rem)]"
        >
          <GlassButton variant="outline" size="inherit">Generate</GlassButton>
        </div>
      </div>
    </div>
  </div>
</template>
