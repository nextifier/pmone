<template>
  <div class="flex items-center justify-center gap-x-2">
    <SocialLink
      v-for="link in Object.values(useAppConfig().socialLinks)"
      :key="link.label"
      :to="link.path"
      :iconName="link.iconName"
      :iconClasses="iconClasses"
      :label="link.label"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  iconClasses: {
    type: String,
  },
});
</script>
