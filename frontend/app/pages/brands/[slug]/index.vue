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
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-lg font-bold tracking-tight">
          {{ brand?.name || "Brand" }}
        </h2>
        <p v-if="brand?.company_name" class="text-muted-foreground truncate text-xs">
          {{ brand.company_name }}
        </p>
      </div>
      <NuxtLink
        v-if="canEditBrand"
        :to="`/brands/${route.params.slug}/edit`"
        class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:edit-02" class="size-4" />
        {{ $t('common.edit') }}
      </NuxtLink>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="brand">
      <!-- Brand Info Card -->
      <div class="border-border rounded-xl border p-5">
        <div class="flex items-start gap-x-4">
          <Avatar
            :model="{ name: brand.name, profile_image: brand.brand_logo }"
            class="size-14 shrink-0"
            rounded="rounded-xl"
          />
          <div class="min-w-0 flex-1 space-y-2">
            <div>
              <div class="flex items-center gap-x-2">
                <h3 class="truncate font-semibold tracking-tight">{{ brand.name }}</h3>
                <span
                  v-if="brand.status"
                  :class="[
                    'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] leading-none font-medium',
                    brand.status === 'active'
                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-muted text-muted-foreground',
                  ]"
                >
                  {{ brand.status }}
                </span>
              </div>
              <p v-if="brand.company_name" class="text-muted-foreground text-sm">
                {{ brand.company_name }}
              </p>
            </div>
            <div v-if="brand.description" class="text-muted-foreground text-sm leading-relaxed">
              {{ brand.description }}
            </div>
            <div class="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
              <span v-if="brand.company_email" class="inline-flex items-center gap-x-1">
                <Icon name="hugeicons:mail-01" class="size-3" />
                {{ brand.company_email }}
              </span>
              <span v-if="brand.company_phone" class="inline-flex items-center gap-x-1">
                <Icon name="hugeicons:call" class="size-3" />
                {{ brand.company_phone }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Events -->
      <div class="space-y-3">
        <h3 class="text-sm font-semibold tracking-tight">{{ $t('brands.events') }}</h3>

        <div v-if="events.length" class="space-y-2">
          <div
            v-for="ev in events"
            :key="ev.id"
            class="border-border rounded-xl border p-4"
          >
            <div class="flex items-start gap-x-3">
              <img
                v-if="ev.event.poster_image?.sm"
                :src="ev.event.poster_image.sm"
                :alt="ev.event.title"
                class="size-12 shrink-0 rounded-lg object-cover"
              />
              <div
                v-else
                class="bg-muted text-muted-foreground flex size-12 shrink-0 items-center justify-center rounded-lg"
              >
                <Icon name="hugeicons:calendar-03" class="size-5" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium tracking-tight">{{ ev.event.title }}</p>
                <div class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-3 text-xs">
                  <span v-if="ev.event.date_label">{{ ev.event.date_label }}</span>
                  <span v-if="ev.event.location">{{ ev.event.location }}</span>
                </div>
                <div class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 text-xs">
                  <span v-if="ev.booth_number">
                    {{ $t('brands.booth') }} <span class="text-foreground font-medium">{{ ev.booth_number }}</span>
                  </span>
                  <span v-if="ev.booth_type_label">
                    {{ ev.booth_type_label }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Quick Links -->
            <div class="mt-3 flex flex-wrap gap-2">
              <NuxtLink
                :to="`/brands/${route.params.slug}/order-form/${ev.id}`"
                class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition active:scale-98"
              >
                <Icon name="hugeicons:shopping-cart-01" class="size-3.5" />
                {{ $t('brands.orderForm') }}
              </NuxtLink>
              <NuxtLink
                :to="`/brands/${route.params.slug}/orders/${ev.id}`"
                class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition active:scale-98"
              >
                <Icon name="hugeicons:shopping-bag-01" class="size-3.5" />
                {{ $t('brands.myOrders') }}
              </NuxtLink>
              <NuxtLink
                :to="`/brands/${route.params.slug}/promotion-posts/${ev.id}`"
                class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition active:scale-98"
              >
                <Icon name="hugeicons:image-02" class="size-3.5" />
                {{ $t('brands.promotionPosts') }}
                <span class="bg-muted text-muted-foreground rounded-full px-1.5 py-0.5 text-[10px]">
                  {{ ev.promotion_posts_count }}
                </span>
              </NuxtLink>
            </div>
          </div>
        </div>

        <div
          v-else
          class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
        >
          <div class="bg-muted flex size-10 items-center justify-center rounded-full">
            <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-5" />
          </div>
          <p class="text-muted-foreground text-sm">{{ $t('brands.noEventsYet') }}</p>
        </div>
      </div>
    </template>

    <!-- Not Found -->
    <div v-else class="flex flex-col items-center justify-center gap-3 py-20">
      <div class="bg-muted flex size-14 items-center justify-center rounded-full">
        <Icon name="hugeicons:blockchain-01" class="text-muted-foreground size-7" />
      </div>
      <p class="text-muted-foreground text-sm">{{ $t('brands.notFound') }}</p>
      <NuxtLink to="/dashboard" class="text-primary text-sm hover:underline">
        {{ $t('brands.backToDashboard') }}
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const { t } = useI18n();
const { hasPermission } = usePermission();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const canEditBrand = computed(() => hasPermission("brands.update"));

const brand = ref(null);
const events = ref([]);
const loading = ref(true);

usePageMeta(null, {
  title: computed(() => brand.value?.name || "Brand"),
});

async function fetchData() {
  loading.value = true;
  try {
    const [brandRes, eventsRes] = await Promise.all([
      client(`/api/exhibitor/brands/${route.params.slug}`),
      client(`/api/exhibitor/brands/${route.params.slug}/events`),
    ]);
    brand.value = brandRes.data;
    events.value = eventsRes.data;
  } catch {
    toast.error(t("brands.failedToLoad"));
  } finally {
    loading.value = false;
  }
}

onMounted(fetchData);
</script>
