<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Back + Title -->
    <div class="flex items-center gap-x-3">
      <NuxtLink
        to="/brands"
        aria-label="Back to brands"
        class="text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors active:scale-98"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-5" />
      </NuxtLink>
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-lg font-medium tracking-tighter">
          {{ brand?.name || "Brand" }}
        </h2>
        <p v-if="brand?.company_name" class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
          {{ brand.company_name }}
        </p>
      </div>
      <div class="flex shrink-0 items-center gap-x-1.5">
        <Button v-if="canViewAnalytics" :to="`/brands/${route.params.slug}/analytics`" variant="outline" size="sm">
          <Icon name="hugeicons:analytics-02" class="size-4 shrink-0" />
          Analytics
        </Button>

        <Button v-if="canEditBrand" :to="`/brands/${route.params.slug}/edit`" variant="outline" size="sm">
          <Icon name="hugeicons:edit-02" class="size-4 shrink-0" />
          {{ $t('common.edit') }}
        </Button>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <template v-if="loading">
      <!-- Brand Info Card Skeleton -->
      <div class="border-border rounded-xl border p-5">
        <div class="flex items-start gap-x-4">
          <Skeleton class="size-14 shrink-0 rounded-xl" />
          <div class="min-w-0 flex-1 space-y-3">
            <div class="flex items-center gap-x-2">
              <Skeleton class="h-5 w-36" />
              <Skeleton class="h-5 w-14 rounded-full" />
            </div>
            <Skeleton class="h-4 w-28" />
            <div class="space-y-1.5">
              <Skeleton class="h-3.5 w-full" />
              <Skeleton class="h-3.5 w-3/4" />
            </div>
            <div class="flex gap-x-4">
              <Skeleton class="h-3.5 w-32" />
              <Skeleton class="h-3.5 w-28" />
            </div>
          </div>
        </div>
      </div>

      <!-- Events Section Skeleton -->
      <div class="space-y-3">
        <Skeleton class="h-5 w-20" />
        <div class="space-y-2">
          <div v-for="i in 2" :key="i" class="border-border rounded-xl border p-4">
            <div class="flex items-start gap-x-3">
              <Skeleton class="size-12 shrink-0 rounded-lg" />
              <div class="min-w-0 flex-1 space-y-2">
                <Skeleton class="h-4 w-40" />
                <Skeleton class="h-3.5 w-56" />
                <Skeleton class="h-3.5 w-32" />
              </div>
            </div>
            <div class="mt-3 flex gap-2">
              <Skeleton v-for="j in 4" :key="j" class="h-8 w-24 rounded-lg" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="brand">
      <!-- Brand Info Card -->
      <div class="border-border rounded-xl border p-5">
        <div class="flex items-start gap-x-4">
          <Avatar
            :model="{ name: brand.name, profile_image: brand.profile_image }"
            class="size-14 shrink-0"
            rounded="rounded-xl"
          />
          <div class="min-w-0 flex-1 space-y-2">
            <div>
              <div class="flex items-center gap-x-2">
                <h3 class="truncate font-medium tracking-tight">{{ brand.name }}</h3>
                <span
                  v-if="brand.status"
                  :class="[
                    'shrink-0 rounded-full border px-2 py-0.5 text-xs leading-none font-medium tracking-tight capitalize',
                    brand.status === 'active'
                      ? 'bg-success/10 text-success-foreground border-success/20'
                      : 'bg-muted text-muted-foreground border-transparent',
                  ]"
                >
                  {{ brand.status }}
                </span>
              </div>
              <p v-if="brand.company_name" class="text-muted-foreground text-sm tracking-tight">
                {{ brand.company_name }}
              </p>
            </div>
            <div v-if="brand.description" class="text-muted-foreground text-sm leading-relaxed tracking-tight">
              {{ brand.description }}
            </div>
            <div class="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs tracking-tight sm:text-sm">
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
        <h3 class="font-medium tracking-tight">{{ $t('brands.events') }}</h3>

        <Accordion v-if="events.length" type="multiple" :default-value="defaultOpenEvents" class="space-y-2">
          <AccordionItem
            v-for="ev in events"
            :key="ev.id"
            :value="String(ev.id)"
            class="rounded-xl px-4 lg:rounded-xl lg:px-4"
          >
            <AccordionTrigger class="py-4 sm:py-4">
              <div class="flex min-w-0 flex-1 items-start gap-x-3 pr-2">
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
                  <div class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-3 text-xs font-normal tracking-tight sm:text-sm">
                    <span v-if="ev.event.date_label">{{ ev.event.date_label }}</span>
                    <span v-if="ev.event.location">{{ ev.event.location }}</span>
                  </div>
                  <div
                    v-if="ev.fascia_name || ev.badge_name"
                    class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 text-xs font-normal tracking-tight sm:text-sm"
                  >
                    <span v-if="ev.fascia_name" class="inline-flex items-center gap-x-1">
                      <Icon name="hugeicons:signature" class="size-3.5 shrink-0" />
                      {{ ev.fascia_name }}
                    </span>
                    <span v-if="ev.badge_name" class="inline-flex items-center gap-x-1">
                      <Icon name="hugeicons:id" class="size-3.5 shrink-0" />
                      {{ ev.badge_name }}
                    </span>
                  </div>
                </div>

                <!-- Booth -->
                <div v-if="ev.booth_number" class="shrink-0 text-right">
                  <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    {{ $t('brands.booth') }}
                  </p>
                  <p class="text-foreground text-lg font-semibold tracking-tighter sm:text-xl">
                    {{ ev.booth_number }}
                  </p>
                  <p v-if="ev.booth_type_label" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    {{ ev.booth_type_label }}
                  </p>
                </div>
              </div>
            </AccordionTrigger>

            <AccordionContent class="pb-4 sm:pb-4">
              <!-- Quick Links -->
              <div class="flex flex-wrap gap-2">
                <Button :to="`/brands/${route.params.slug}/documents/${ev.id}`" variant="outline" size="sm">
                  <Icon name="hugeicons:file-01" class="size-4 shrink-0" />
                  Documents
                </Button>
                <Button :to="`/brands/${route.params.slug}/order-form/${ev.id}`" variant="outline" size="sm">
                  <Icon name="hugeicons:shopping-cart-01" class="size-4 shrink-0" />
                  {{ $t('brands.orderForm') }}
                </Button>
                <Button :to="`/brands/${route.params.slug}/orders/${ev.id}`" variant="outline" size="sm">
                  <Icon name="hugeicons:shopping-bag-01" class="size-4 shrink-0" />
                  {{ $t('brands.myOrders') }}
                </Button>
                <Button :to="`/brands/${route.params.slug}/promotion-posts/${ev.id}`" variant="outline" size="sm">
                  <Icon name="hugeicons:image-02" class="size-4 shrink-0" />
                  {{ $t('brands.promotionPosts') }}
                  <span class="bg-muted text-muted-foreground -mr-1 rounded-full px-1.5 py-0.5 text-xs tabular-nums tracking-tight">
                    {{ ev.promotion_posts_count }}
                  </span>
                </Button>
                <Button :to="`/brands/${route.params.slug}/leads/${ev.id}`" variant="outline" size="sm">
                  <Icon name="hugeicons:agreement-02" class="size-4 shrink-0" />
                  Leads
                </Button>
              </div>
            </AccordionContent>
          </AccordionItem>
        </Accordion>

        <Empty v-else class="border-dashed">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:calendar-03" />
            </EmptyMedia>
            <EmptyTitle>{{ $t('brands.noEventsYet') }}</EmptyTitle>
          </EmptyHeader>
        </Empty>
      </div>
    </template>

    <!-- Not Found -->
    <Empty v-else class="border-dashed">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:store-02" />
        </EmptyMedia>
        <EmptyTitle>{{ $t('brands.notFound') }}</EmptyTitle>
      </EmptyHeader>
      <EmptyContent>
        <Button to="/dashboard" variant="outline" size="sm">
          {{ $t('brands.backToDashboard') }}
        </Button>
      </EmptyContent>
    </Empty>
  </div>
</template>

<script setup>
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { toast } from "vue-sonner";

const { t } = useI18n();
const { hasPermission, hasAnyRole } = usePermission();
const { user: authUser } = useSanctumAuth();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const canEditBrand = computed(() => hasPermission("brands.update"));

const canViewAnalytics = computed(() => {
  if (!authUser.value) return false;
  if (hasAnyRole(["master", "admin"])) return true;
  if (hasPermission("analytics.view")) return true;
  return !!brand.value;
});

const brand = ref(null);
const events = ref([]);
const loading = ref(true);

// Default open: collapse all when multiple events, open the only one when exactly one.
const defaultOpenEvents = computed(() => {
  if (events.value.length === 1) {
    return [String(events.value[0].id)];
  }
  return [];
});

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
