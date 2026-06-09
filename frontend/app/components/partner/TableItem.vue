<template>
  <NuxtLink
    :to="detailUrl"
    class="flex items-center gap-x-2 transition hover:opacity-80"
  >
    <div
      class="relative aspect-3/2 h-10 shrink-0 overflow-hidden rounded-lg border bg-white"
    >
      <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="partner.partner_name"
        class="size-full object-contain"
        loading="lazy"
        referrerpolicy="no-referrer"
      />
      <div
        v-else
        class="bg-muted text-muted-foreground flex size-full items-center justify-center text-xs font-medium"
      >
        {{ initials }}
      </div>
    </div>

    <p class="truncate">{{ partner.partner_name }}</p>
  </NuxtLink>
</template>

<script setup>
const props = defineProps({
  partner: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  linkSuffix: { type: String, default: "" },
});

const detailUrl = computed(
  () => `${props.baseUrl}/${props.partner.partner_slug}${props.linkSuffix}`
);

const logoUrl = computed(
  () =>
    props.partner.partner_logo?.sm ||
    props.partner.partner_logo?.url ||
    props.partner.partner_logo?.original
);

const initials = computed(() => {
  const names = (props.partner.partner_name || "").trim().split(" ");
  const first = names[0]?.[0]?.toUpperCase() || "";
  const last =
    names.length === 1
      ? names[0]?.[1]?.toUpperCase() || ""
      : names[names.length - 1]?.[0]?.toUpperCase() || "";
  return first + last;
});
</script>
