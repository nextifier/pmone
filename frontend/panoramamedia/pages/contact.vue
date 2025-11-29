<template>
  <div class="min-h-screen-offset container max-w-lg pt-6 pb-16">
    <ContactForm
      :title="content.title"
      :description="content.description"
      :showBrandField="false"
    />
  </div>
</template>

<script setup>
usePageMeta("contact");
const content = useContentStore().components.contact;
</script>
