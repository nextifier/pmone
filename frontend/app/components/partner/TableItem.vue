<template>
  <NuxtLink
    :to="detailUrl"
    class="flex items-center gap-x-2 transition hover:opacity-80"
  >
    <Avatar
      :model="{ name: partner.partner_name, profile_image: partner.partner_logo }"
      class="size-10"
      rounded="rounded-lg"
      :colorful="false"
    />

    <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
      <div class="flex items-center gap-x-2">
        <span
          class="text-xs font-medium tracking-tight capitalize"
          :class="{
            'text-success-foreground': partner.status === 'active',
            'text-muted-foreground': partner.status === 'inactive',
          }"
        >
          {{ partner.status }}
        </span>
      </div>
      <p class="truncate">{{ partner.partner_name }}</p>
      <p v-if="partner.website_url" class="text-muted-foreground truncate text-xs tracking-tight">
        {{ partner.website_url }}
      </p>
    </div>
  </NuxtLink>
</template>

<script setup>
const props = defineProps({
  partner: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  linkSuffix: { type: String, default: "" },
});

const detailUrl = computed(() => `${props.baseUrl}/${props.partner.partner_slug}${props.linkSuffix}`);
</script>
