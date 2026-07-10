<template>
  <NuxtLink :to="detailUrl" class="flex items-center gap-x-3">
    <Avatar
      :model="{ name: brand.brand_name, profile_image: brand.profile_image }"
      class="size-11"
      rounded="rounded-full"
      :colorful="false"
      :gradient-frame="hasInstagram"
    />

    <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
      <p class="truncate">{{ brand.brand_name }}</p>
      <p v-if="brand.company_name" class="text-muted-foreground truncate text-xs tracking-tight">
        {{ brand.company_name }}
      </p>
    </div>
  </NuxtLink>
</template>

<script setup>
const props = defineProps({
  brand: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  linkSuffix: { type: String, default: "" },
});

const hasInstagram = computed(() => {
  const links = props.brand.links || [];
  return links.some((l) => l.url?.includes("instagram.com"));
});

const detailUrl = computed(() => `${props.baseUrl}/${props.brand.brand_slug}${props.linkSuffix}`);
</script>
