<template>
  <div class="space-y-16 pb-10 lg:space-y-24 lg:pb-16">
    <ComingSoon title="Home" />
    <DevOnly>
      <Hero />
      <AboutSection />
      <LazyBlogPostSlider
        :headline="useContentStore().components.postSlider.title.default"
      />
    </DevOnly>
  </div>
</template>

<script setup>
usePageMeta("home");
</script>
