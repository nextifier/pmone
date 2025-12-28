<template>
  <div class="overflow-x-hidden">
    <div class="min-h-screen-offset grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))]">
      <div class="flex flex-col items-center justify-center py-20">
        <div class="flex items-center justify-center [--global-size:clamp(2rem,4vw,5rem)]">
          <GlassButton variant="outline" size="inherit">Generate</GlassButton>
        </div>
      </div>

      <div
        class="flex flex-col items-center justify-center bg-linear-to-br from-pink-300 via-purple-300 to-indigo-400 py-20"
      >
        <div class="flex items-center justify-center [--global-size:clamp(2rem,4vw,5rem)]">
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
          class="absolute inset-0 z-10 flex items-center justify-center [--global-size:clamp(2rem,4vw,5rem)]"
        >
          <GlassButton variant="outline" size="inherit">Generate</GlassButton>
        </div>
      </div>
    </div>
  </div>
</template>
