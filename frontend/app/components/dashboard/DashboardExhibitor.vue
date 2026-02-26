<template>
  <div class="mx-auto flex flex-col gap-y-3 pt-2 pt-4 pb-16 pb-20 lg:max-w-4xl lg:pt-4 xl:max-w-6xl">
    <DashboardGreetingTips />

    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="dashboard">
      <!-- Complete Your Profile (conditional) -->
      <div v-if="!dashboard.profile_complete" class="border-border rounded-xl border">
        <div class="flex items-center gap-x-3 border-b px-5 py-4">
          <div
            class="flex size-8 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30"
          >
            <Icon name="hugeicons:user-edit-01" class="size-4" />
          </div>
          <div>
            <h3 class="text-sm font-semibold tracking-tight">
              {{ $t("dashboard.completeYourProfile") }}
            </h3>
            <p class="text-muted-foreground text-xs">{{ $t("dashboard.fillInAllFields") }}</p>
          </div>
        </div>
        <form class="space-y-4 p-5" @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="ex_name">{{ $t("dashboard.fullName") }}</Label>
              <Input
                id="ex_name"
                v-model="profileForm.name"
                :placeholder="$t('dashboard.yourFullName')"
              />
            </div>
            <div class="space-y-2">
              <Label for="ex_phone">{{ $t("dashboard.phoneNumber") }}</Label>
              <InputPhone v-model="profileForm.phone" id="ex_phone" />
            </div>
            <div class="space-y-2">
              <Label for="ex_title">{{ $t("dashboard.jobTitle") }}</Label>
              <Input
                id="ex_title"
                v-model="profileForm.title"
                :placeholder="$t('dashboard.egJobTitle')"
              />
            </div>
            <div class="space-y-2">
              <Label for="ex_company">{{ $t("dashboard.companyName") }}</Label>
              <Input
                id="ex_company"
                v-model="profileForm.company_name"
                :placeholder="$t('dashboard.yourCompany')"
              />
            </div>
          </div>
          <Button type="submit" size="sm" :disabled="profileSaving">
            <Icon v-if="profileSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            {{ $t("dashboard.saveProfile") }}
          </Button>
        </form>
      </div>

      <!-- Your Brands -->
      <div class="mt-5 space-y-4 sm:mt-8">
        <h3 class="page-title text-lg!">{{ $t("dashboard.yourBrands") }}</h3>
        <div v-if="dashboard.brands?.length" class="space-y-2">
          <NuxtLink
            v-for="brand in dashboard.brands"
            :key="brand.id"
            :to="`/brands/${brand.slug}`"
            class="flex items-start gap-x-3 transition-opacity hover:opacity-80"
          >
            <Avatar
              :model="{ name: brand.name, profile_image: brand.brand_logo }"
              class="size-12 shrink-0"
              rounded="rounded-lg"
            />
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-x-2">
                <p class="truncate text-sm font-medium tracking-tight">{{ brand.name }}</p>
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
              <p v-if="brand.company_name" class="text-muted-foreground mt-0.5 truncate text-xs tracking-tight">
                {{ brand.company_name }}
              </p>
              <div class="text-muted-foreground mt-1 flex items-center gap-x-3 text-xs tracking-tight">
                <span class="inline-flex items-center gap-1">
                  <Icon name="hugeicons:calendar-03" class="size-3" />
                  {{ brand.events_count }} {{ $t("common.event", brand.events_count) }}
                </span>
              </div>
              <!-- Incomplete warning -->
              <div
                v-if="!brand.is_complete"
                class="mt-2 flex items-start gap-x-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 dark:bg-amber-900/20"
              >
                <Icon
                  name="hugeicons:alert-02"
                  class="mt-0.5 size-3 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <p class="text-xs text-amber-700 dark:text-amber-300">
                  {{
                    $t("dashboard.incompleteProfile", { fields: brand.missing_fields.join(", ") })
                  }}
                </p>
              </div>
            </div>
            <div
              v-if="brand.is_complete"
              class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30"
            >
              <Icon name="hugeicons:checkmark-circle-02" class="size-4" />
            </div>
          </NuxtLink>
        </div>
        <div
          v-else
          class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
        >
          <div class="bg-muted flex size-10 items-center justify-center rounded-full">
            <Icon name="hugeicons:blockchain-01" class="text-muted-foreground size-5" />
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">{{ $t("dashboard.noBrandsYet") }}</p>
        </div>
      </div>

      <!-- Two Column: Orders + Promotion Posts -->
      <div class="mt-5 grid gap-10 sm:mt-8 lg:grid-cols-2">
        <!-- Your Orders -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="page-title text-lg!">{{ $t("dashboard.yourOrders") }}</h3>
            <NuxtLink
              to="/orders"
              class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
            >
              <span>{{ $t("common.viewAll") }}</span>
              <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
            </NuxtLink>
          </div>
          <div v-if="dashboard.recent_orders?.length" class="space-y-2">
            <NuxtLink
              v-for="order in dashboard.recent_orders"
              :key="order.ulid"
              :to="`/brands/${order.brand_event?.brand?.slug}/orders/${order.brand_event_id}/${order.ulid}`"
              class="flex items-center justify-between gap-x-4 transition-opacity hover:opacity-80"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-x-2">
                  <p class="font-mono text-sm font-medium tracking-tight">{{ order.order_number }}</p>
                  <span
                    :class="[
                      'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] leading-none font-medium',
                      orderStatusClass(order.status),
                    ]"
                  >
                    {{ order.status }}
                  </span>
                </div>
                <p class="text-muted-foreground mt-0.5 text-xs tracking-tight">
                  {{ order.items_count }} {{ $t("common.item", order.items_count) }} ·
                  {{ formatDate(order.submitted_at) }}
                </p>
              </div>
              <p class="shrink-0 text-sm font-semibold tracking-tight">
                {{ formatPrice(order.total) }}
              </p>
            </NuxtLink>
          </div>
          <div
            v-else
            class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
          >
            <div class="bg-muted flex size-10 items-center justify-center rounded-full">
              <Icon name="hugeicons:shopping-bag-01" class="text-muted-foreground size-5" />
            </div>
            <p class="text-muted-foreground text-sm tracking-tight">{{ $t("dashboard.noOrdersYet") }}</p>
          </div>
        </div>

        <!-- Upload Promotion Posts -->
        <div class="space-y-4">
          <div>
            <h3 class="page-title text-lg!">
              {{ $t("dashboard.uploadPromotionPosts") }}
            </h3>
            <p class="text-muted-foreground mt-1 text-xs leading-relaxed tracking-tight">
              {{ $t("dashboard.promotionPostsDescription") }}
            </p>
          </div>
          <div v-if="dashboard.upcoming_brand_events?.length" class="space-y-2">
            <NuxtLink
              v-for="item in dashboard.upcoming_brand_events"
              :key="item.brand_event_id"
              :to="`/brands/${item.brand.slug}/promotion-posts/${item.brand_event_id}`"
              class="flex items-center gap-x-3 transition-opacity hover:opacity-80"
            >
              <div class="bg-muted border-border size-12 shrink-0 overflow-hidden rounded-sm border">
                <img
                  v-if="item.event.poster_image?.sm"
                  :src="item.event.poster_image.sm"
                  :alt="item.event.title"
                  class="size-full object-cover select-none"
                  loading="lazy"
                />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium tracking-tight">{{ item.event.title }}</p>
                <p class="text-muted-foreground text-xs tracking-tight">{{ item.brand.name }}</p>
                <div class="text-muted-foreground mt-0.5 flex items-center gap-x-3 text-xs tracking-tight">
                  <span v-if="item.event.date_label">{{ item.event.date_label }}</span>
                  <span v-if="item.event.location">{{ item.event.location }}</span>
                </div>
              </div>
              <div class="flex flex-col items-end gap-1">
                <span
                  class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
                >
                  <Icon name="hugeicons:image-02" class="size-3" />
                  {{ item.promotion_posts_count }}
                </span>
                <span
                  v-if="item.event.order_form_deadline && new Date(item.event.order_form_deadline) < new Date()"
                  class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                  {{ $t('dashboard.ordersClosed') }}
                </span>
                <span
                  v-if="item.event.promotion_post_deadline && new Date(item.event.promotion_post_deadline) < new Date()"
                  class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                  {{ $t('dashboard.uploadsClosed') }}
                </span>
              </div>
            </NuxtLink>
          </div>
          <div
            v-else
            class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
          >
            <div class="bg-muted flex size-10 items-center justify-center rounded-full">
              <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-5" />
            </div>
            <p class="text-muted-foreground text-sm tracking-tight">{{ $t("dashboard.noUpcomingEvents") }}</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const { t } = useI18n();
const client = useSanctumClient();
const { formatPrice, formatDateId: formatDate, orderStatusClass } = useFormatters();

const data = ref(null);
const pending = ref(true);
const dashboard = computed(() => data.value?.data);

// Profile form
const profileForm = reactive({
  name: "",
  phone: "",
  title: "",
  company_name: "",
});
const profileSaving = ref(false);

const initProfileForm = () => {
  const user = dashboard.value?.user;
  if (user) {
    profileForm.name = user.name || "";
    profileForm.phone = user.phone || "";
    profileForm.title = user.title || "";
    profileForm.company_name = user.company_name || "";
  }
};

const fetchData = async () => {
  try {
    data.value = await client("/api/exhibitor/dashboard");
    initProfileForm();
  } catch (e) {
    console.error("Failed to fetch exhibitor dashboard:", e);
  }
  pending.value = false;
};

const saveProfile = async () => {
  profileSaving.value = true;
  try {
    await client("/api/user/profile", { method: "PUT", body: profileForm });
    toast.success(t("dashboard.profileUpdated"));
    await fetchData();
  } catch (e) {
    toast.error(e?.data?.message || t("dashboard.failedToUpdateProfile"));
  } finally {
    profileSaving.value = false;
  }
};

onMounted(fetchData);
</script>
