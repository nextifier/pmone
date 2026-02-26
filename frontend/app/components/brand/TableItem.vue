<template>
  <NuxtLink
    :to="detailUrl"
    class="flex items-center gap-x-2 transition hover:opacity-80"
  >
    <Avatar
      :model="{ name: brand.brand_name, profile_image: brand.brand_logo }"
      class="size-10"
      rounded="rounded-lg"
    />

    <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
      <div class="flex items-center gap-x-2">
        <span
          class="text-xs font-medium tracking-tight capitalize"
          :class="{
            'text-success-foreground': brand.status === 'active',
            'text-warning-foreground': brand.status === 'draft',
            'text-destructive': brand.status === 'cancelled',
          }"
        >
          {{ brand.status }}
        </span>
      </div>
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

const detailUrl = computed(() => `${props.baseUrl}/${props.brand.brand_slug}${props.linkSuffix}`);
</script>
