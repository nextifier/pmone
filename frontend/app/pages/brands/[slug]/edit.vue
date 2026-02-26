<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Back + Title -->
    <div class="flex items-center gap-x-3">
      <NuxtLink
        to="/brands"
        class="text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-lg transition"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-5" />
      </NuxtLink>
      <h2 class="text-lg font-bold tracking-tight">{{ $t('brands.editBrand') }}</h2>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <BrandFormBrandProfile
      v-else-if="brand"
      :brand="brand"
      :api-url="`/api/brands/${slug}`"
      :show-logo="true"
      :show-status="true"
      :show-categories="true"
      @saved="fetchBrand"
    />

    <!-- Not Found -->
    <div v-else class="flex flex-col items-center justify-center gap-3 py-20">
      <div class="bg-muted flex size-14 items-center justify-center rounded-full">
        <Icon name="hugeicons:blockchain-01" class="text-muted-foreground size-7" />
      </div>
      <p class="text-muted-foreground text-sm">{{ $t('brands.notFound') }}</p>
      <NuxtLink to="/brands" class="text-primary text-sm hover:underline">
        {{ $t('brands.backToBrands') }}
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
const { t } = useI18n();

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["brands.update"],
  layout: "app",
});

usePageMeta(null, {
  title: "Edit Brand",
});

const route = useRoute();
const client = useSanctumClient();
const slug = route.params.slug;

const pending = ref(true);
const brand = ref(null);

const fetchBrand = async () => {
  try {
    const res = await client(`/api/brands/${slug}`);
    brand.value = res.data;
  } catch (e) {
    console.error("Failed to fetch brand:", e);
  }
  pending.value = false;
};

onMounted(fetchBrand);
</script>
