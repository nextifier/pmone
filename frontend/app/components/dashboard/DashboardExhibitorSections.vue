<template>
  <div :ref="(el) => (wrapRefs.profile = el)" class="space-y-2.5">
    <!-- Section 1: Profile (only if incomplete) -->
    <DashboardExhibitorSection
      v-if="!dashboard.profile_complete"
      v-model:open="sectionStates.profile"
      :title="$t('ed.profile.title')"
      icon="hugeicons:user-edit-01"
      :summary="$t('ed.profile.summary')"
      :badge-text="$t('ed.profile.required')"
      badge-variant="destructive"
      :default-open="defaultProfileOpen"
      section-key="profile"
    >
      <form class="space-y-4" @submit.prevent="saveProfile">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label :for="`ex_name_${be.brand_event_id}`">{{ $t("ed.profile.fullName") }}</Label>
            <Input
              :id="`ex_name_${be.brand_event_id}`"
              v-model="profileForm.name"
              :placeholder="$t('ed.profile.placeholderName')"
            />
          </div>
          <div class="space-y-2">
            <Label :for="`ex_phone_${be.brand_event_id}`">{{ $t("ed.profile.phone") }}</Label>
            <InputPhone :id="`ex_phone_${be.brand_event_id}`" v-model="profileForm.phone" />
          </div>
          <div class="space-y-2">
            <Label :for="`ex_title_${be.brand_event_id}`">{{ $t("ed.profile.jobTitle") }}</Label>
            <Input
              :id="`ex_title_${be.brand_event_id}`"
              v-model="profileForm.title"
              :placeholder="$t('ed.profile.placeholderTitle')"
            />
          </div>
          <div class="space-y-2">
            <Label :for="`ex_company_${be.brand_event_id}`">{{ $t("ed.profile.company") }}</Label>
            <Input
              :id="`ex_company_${be.brand_event_id}`"
              v-model="profileForm.company_name"
              :placeholder="$t('ed.profile.placeholderCompany')"
            />
          </div>
        </div>
        <Button type="submit" size="sm" :disabled="profileSaving">
          <Spinner v-if="profileSaving" class="mr-1.5 size-4" />
          {{ $t("ed.profile.save") }}
        </Button>
      </form>
    </DashboardExhibitorSection>

    <!-- Section 2: Event Rules -->
    <div :ref="(el) => (wrapRefs.rules = el)">
      <DashboardExhibitorSection
        v-if="be.event_rules?.length"
        v-model:open="sectionStates.rules"
        :title="$t('ed.rules.title')"
        icon="hugeicons:file-validation"
        :summary="
          be.event_rules_agreed
            ? $t('ed.rules.allAgreed')
            : $t('ed.rules.agreedCount', { agreed: rulesAgreedCount, total: be.event_rules.length })
        "
        :completed="be.event_rules_agreed"
        :locked="!dashboard.profile_complete"
        :badge-text="!be.event_rules_agreed ? $t('ed.profile.required') : ''"
        badge-variant="destructive"
        :attention-count="rulesNeedingAttention"
        section-key="rules"
      >
        <div class="space-y-4">
          <template v-for="rule in be.event_rules" :key="rule.document.id">
            <DashboardExhibitorDocItem :doc="rule.document" mode="view" />
            <!-- Already agreed: show agreement info only -->
            <div
              v-if="rule.agreed && !rule.needs_reagreement && rule.submission"
              class="flex items-center gap-x-1.5"
            >
              <div
                class="bg-primary text-primary-foreground flex size-5 shrink-0 items-center justify-center rounded-full"
              >
                <Icon name="lucide:check" class="size-3.5" />
              </div>
              <p class="text-muted-foreground text-sm tracking-tight sm:text-sm">
                {{ $t("ed.rules.agreedOn", { date: formatDate(rule.submission.agreed_at) }) }}
                <span v-if="rule.submission.submitter_name">
                  {{ $t("ed.rules.agreedBy", { name: rule.submission.submitter_name }) }}</span
                >
                <span v-if="rule.submission.document_version > 1">
                  (v{{ rule.submission.document_version }})</span
                >
              </p>
            </div>
            <!-- Not yet agreed or needs re-agreement: show checkbox + submit -->
            <div v-else>
              <div class="flex items-start gap-x-2">
                <Checkbox
                  :id="`rule_${be.brand_event_id}_${rule.document.id}`"
                  :model-value="!!checkedRules[rule.document.id]"
                  @update:model-value="(v) => (checkedRules[rule.document.id] = v)"
                />
                <div>
                  <Label
                    :for="`rule_${be.brand_event_id}_${rule.document.id}`"
                    class="text-sm leading-snug font-normal"
                  >
                    {{ agreeLabel(rule.document) }}
                  </Label>
                  <p
                    v-if="rule.needs_reagreement"
                    class="text-warning-foreground mt-1 text-sm tracking-tight sm:text-sm"
                  >
                    {{ $t("ed.rules.reagreeWarning") }}
                  </p>
                </div>
              </div>
              <Button
                v-if="checkedRules[rule.document.id]"
                size="sm"
                class="mt-2 ml-6"
                :disabled="agreeingId === rule.document.id"
                @click="handleAgreeRule(rule)"
              >
                <Spinner v-if="agreeingId === rule.document.id" class="mr-1.5 size-4" />
                {{ $t("ed.rules.submit") }}
              </Button>
            </div>
          </template>
        </div>
      </DashboardExhibitorSection>
    </div>

    <!-- Section 3: Brand Profile -->
    <div :ref="(el) => (wrapRefs.brand = el)">
      <DashboardExhibitorSection
        v-model:open="sectionStates.brand"
        :title="$t('ed.brand.title')"
        icon="hugeicons:store-02"
        :summary="
          be.brand_complete
            ? $t('ed.brand.complete', { name: be.brand.name })
            : $t('ed.brand.missing', { fields: be.brand.missing_fields.join(', ') })
        "
        :completed="be.brand_complete"
        :locked="sectionsLocked"
        section-key="brand"
      >
        <div class="flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <img
              v-if="be.brand.profile_image?.sm"
              :src="be.brand.profile_image.sm"
              :alt="be.brand.name"
              class="size-10 rounded-lg object-cover"
            />
            <div
              v-else
              class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg"
            >
              <Icon name="hugeicons:store-02" class="size-5" />
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium">{{ be.brand.name }}</p>
              <p
                v-if="!be.brand_complete"
                class="text-muted-foreground text-sm tracking-tight sm:text-sm"
              >
                {{ $t("ed.brand.fieldsRemaining", be.brand.missing_fields.length, { count: be.brand.missing_fields.length }) }}
              </p>
            </div>
          </div>
          <Button :to="`/brands/${be.brand.slug}/edit`" size="sm" variant="outline" class="shrink-0">
            <Icon
              :name="be.brand_complete ? 'hugeicons:view' : 'hugeicons:edit-02'"
              class="mr-1.5 size-4"
            />
            {{ be.brand_complete ? $t("ed.brand.view") : $t("ed.brand.completeCta") }}
          </Button>
        </div>
      </DashboardExhibitorSection>
    </div>

    <!-- Section 4: Promotion Posts -->
    <div :ref="(el) => (wrapRefs.promo = el)">
      <DashboardExhibitorSection
        v-model:open="sectionStates.promo"
        :title="$t('ed.promo.title')"
        icon="hugeicons:image-02"
        :summary="$t('ed.promo.uploaded', { count: be.promotion_posts_count, limit: be.promotion_post_limit })"
        :completed="be.promotion_posts_count > 0"
        :locked="sectionsLocked"
        :deadline="formatDeadlineShort(be.promotion_post_deadline)"
        :deadline-urgent="isDeadlineUrgent(be.promotion_post_deadline)"
        section-key="promo"
      >
        <div class="flex items-center justify-between gap-3">
          <p class="text-muted-foreground text-sm tracking-tight">
            {{
              be.promotion_posts_count > 0
                ? $t("ed.promo.uploadedDescription", { count: be.promotion_posts_count, limit: be.promotion_post_limit })
                : $t("ed.promo.uploadDescription", be.promotion_post_limit, { limit: be.promotion_post_limit })
            }}
          </p>
          <Button
            :to="`/brands/${be.brand.slug}/promotion-posts/${be.brand_event_id}`"
            size="sm"
            variant="outline"
            class="shrink-0"
          >
            <Icon
              :name="be.promotion_posts_count > 0 ? 'hugeicons:view' : 'hugeicons:upload-04'"
              class="mr-1.5 size-4"
            />
            {{ be.promotion_posts_count > 0 ? $t("ed.promo.manage") : $t("ed.promo.upload") }}
          </Button>
        </div>
      </DashboardExhibitorSection>
    </div>

    <!-- Section 5: Operational Documents -->
    <div :ref="(el) => (wrapRefs.docs = el)">
      <DashboardExhibitorSection
        v-if="be.documents?.length || showFascia || showBadge"
        v-model:open="sectionStates.docs"
        :title="$t('ed.docs.title')"
        icon="hugeicons:file-01"
        :summary="docsAndBoothSummary"
        :completed="docsComplete"
        :locked="sectionsLocked"
        :attention-count="docsNeedingAttention"
        section-key="docs"
      >
        <!-- Document list -->
        <div v-if="be.documents?.length" class="divide-border -mx-4 divide-y sm:-mx-5">
          <DashboardExhibitorDocItem
            v-for="doc in be.documents"
            :key="doc.document.id"
            class="px-4 py-6 first:pt-0 last:pb-0 sm:px-5"
            :doc="doc.document"
            :submission="doc.submission"
            :status="doc.status"
            :api-base="docsApiBase"
            mode="action"
            @submitted="$emit('refresh')"
          />
        </div>

        <!-- Booth Fields (fascia & badge) -->
        <div
          v-if="showFascia || showBadge"
          :class="{ 'border-border mt-5 border-t pt-5': be.documents?.length }"
        >
          <p class="mb-3 text-sm font-medium">{{ $t("ed.docs.boothDetails") }}</p>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div v-if="showFascia" class="space-y-2">
              <Label :for="`fascia_${be.brand_event_id}`">{{ $t("ed.docs.fasciaName") }}</Label>
              <Input
                :id="`fascia_${be.brand_event_id}`"
                :model-value="boothFields.fascia_name ?? be.fascia_name ?? ''"
                :placeholder="$t('ed.docs.fasciaPlaceholder')"
                maxlength="24"
                @update:model-value="(v) => setBoothField('fascia_name', v.toUpperCase())"
              />
              <p class="text-muted-foreground text-sm tracking-tight sm:text-sm">
                {{ $t("ed.docs.fasciaHint") }}
              </p>
            </div>
            <div v-if="showBadge" class="space-y-2">
              <Label :for="`badge_${be.brand_event_id}`">{{ $t("ed.docs.badgeName") }}</Label>
              <p class="text-muted-foreground text-sm tracking-tight sm:text-sm">
                {{ $t("ed.docs.badgeDescription") }}
              </p>
              <Input
                :id="`badge_${be.brand_event_id}`"
                :model-value="boothFields.badge_name ?? be.badge_name ?? ''"
                :placeholder="$t('ed.docs.badgePlaceholder')"
                @update:model-value="(v) => setBoothField('badge_name', v)"
              />
            </div>
            <div class="sm:col-span-2">
              <Button size="sm" :disabled="savingBoothFields" @click="saveBoothFields">
                <Spinner v-if="savingBoothFields" class="mr-1.5 size-4" />
                {{ $t("ed.docs.saveBoothDetails") }}
              </Button>
            </div>
          </div>
        </div>
      </DashboardExhibitorSection>
    </div>

    <!-- Section 6: Order Form -->
    <div :ref="(el) => (wrapRefs.order = el)">
      <DashboardExhibitorSection
        v-model:open="sectionStates.order"
        :title="$t('ed.order.title')"
        icon="hugeicons:shopping-cart-01"
        :summary="orderSectionSummary"
        :completed="be.orders_count > 0"
        :locked="sectionsLocked"
        section-key="order"
      >
        <DashboardExhibitorOrderInfo :be="be" />
      </DashboardExhibitorSection>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { exhibitorDocsComplete, exhibitorShowBadge, exhibitorShowFascia } from "@/utils/exhibitorDashboard";
import { toast } from "vue-sonner";

const props = defineProps({
  be: { type: Object, required: true },
  dashboard: { type: Object, required: true },
  defaultProfileOpen: { type: Boolean, default: false },
});

const emit = defineEmits(["refresh"]);

const { t, locale } = useI18n();
const client = useSanctumClient();

const dateLocale = computed(() => (locale.value === "zh" ? "zh-CN" : "en-US"));

const sectionStates = reactive({});
const checkedRules = reactive({});
const agreeingId = ref(null);
const boothFields = reactive({});
const savingBoothFields = ref(false);
const wrapRefs = {};

// --- Booth fields / docs (shared logic) ---
const showFascia = computed(() => exhibitorShowFascia(props.be));
const showBadge = computed(() => exhibitorShowBadge(props.be));
const docsComplete = computed(() => exhibitorDocsComplete(props.be));
const docsApiBase = computed(
  () => `/api/exhibitor/brands/${props.be.brand.slug}/events/${props.be.brand_event_id}/documents`
);

const sectionsLocked = computed(
  () =>
    !props.dashboard.profile_complete ||
    (props.be.event_rules?.length > 0 && !props.be.event_rules_agreed)
);

// --- Profile form ---
const profileForm = reactive({ name: "", phone: "", title: "", company_name: "" });
const profileSaving = ref(false);

watch(
  () => props.dashboard?.user,
  (user) => {
    if (!user) return;
    profileForm.name = user.name || "";
    profileForm.phone = user.phone || "";
    profileForm.title = user.title || "";
    profileForm.company_name = user.company_name || "";
  },
  { immediate: true }
);

async function saveProfile() {
  profileSaving.value = true;
  try {
    await client("/api/user/profile", { method: "PUT", body: profileForm });
    toast.success(t("ed.profile.updated"));
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || t("ed.profile.failedToUpdate"));
  } finally {
    profileSaving.value = false;
  }
}

// --- Formatting helpers ---
function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString(dateLocale.value, {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function formatDeadlineShort(dateStr) {
  if (!dateStr) return "";
  const deadline = new Date(dateStr);
  if (deadline < new Date()) return "";
  const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
  if (daysLeft <= 0) return "";
  if (daysLeft <= 7) return t("ed.daysLeft", { days: daysLeft });
  return formatDate(dateStr);
}

function isDeadlineUrgent(dateStr) {
  if (!dateStr) return false;
  const deadline = new Date(dateStr);
  const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
  return daysLeft > 0 && daysLeft <= 7;
}

// --- Event rules ---
const rulesAgreedCount = computed(
  () => props.be.event_rules?.filter((r) => r.agreed && !r.needs_reagreement).length || 0
);

const rulesNeedingAttention = computed(
  () => props.be.event_rules?.filter((r) => r.needs_reagreement || !r.agreed).length || 0
);

/**
 * Rules built in the field builder carry their own agreement wording; the
 * synthesized checkbox on backfilled legacy documents does not.
 */
function agreeLabel(doc) {
  const field = documentAgreementField(doc);
  if (field && field.system_key !== "agreement") return field.label;

  return t("ed.rules.agreeLabel", { title: doc.title });
}

async function handleAgreeRule(rule) {
  agreeingId.value = rule.document.id;
  try {
    await client(
      `/api/exhibitor/brands/${props.be.brand.slug}/events/${props.be.brand_event_id}/documents/${rule.document.ulid}`,
      { method: "POST", body: { agreement: true } }
    );
    delete checkedRules[rule.document.id];
    toast.success(t("ed.rules.recorded"));
    emit("refresh");
  } catch (err) {
    toast.error(err?.data?.message || t("ed.rules.failedToSubmit"));
  } finally {
    agreeingId.value = null;
  }
}

// --- Documents summary ---
const docsAndBoothSummary = computed(() => {
  const be = props.be;
  const parts = [];
  if (be.documents?.length) {
    parts.push(t("ed.docs.documents", { completed: be.documents_completed, total: be.documents_total }));
  }
  if (showFascia.value || showBadge.value) {
    const boothDone =
      (showFascia.value ? (be.fascia_name ? 1 : 0) : 0) +
      (showBadge.value ? (be.badge_name ? 1 : 0) : 0);
    const boothTotal = (showFascia.value ? 1 : 0) + (showBadge.value ? 1 : 0);
    parts.push(t("ed.docs.boothFields", { completed: boothDone, total: boothTotal }));
  }
  return parts.join(", ") || t("ed.docs.noDocsRequired");
});

const docsNeedingAttention = computed(
  () => props.be.documents?.filter((d) => d.status === "needs_reagreement").length || 0
);

// --- Booth fields ---
function setBoothField(field, value) {
  boothFields[field] = value;
}

async function saveBoothFields() {
  savingBoothFields.value = true;
  try {
    await client(
      `/api/exhibitor/brands/${props.be.brand.slug}/events/${props.be.brand_event_id}/booth-fields`,
      {
        method: "PUT",
        body: {
          fascia_name: boothFields.fascia_name ?? props.be.fascia_name,
          badge_name: boothFields.badge_name ?? props.be.badge_name,
        },
      }
    );
    toast.success(t("ed.docs.saved"));
    emit("refresh");
  } catch (err) {
    toast.error(err?.data?.message || t("ed.docs.failedToSave"));
  } finally {
    savingBoothFields.value = false;
  }
}

// --- Order period summary ---
function getCurrentOrderPeriod(be) {
  const now = new Date();
  if (be.normal_order_opens_at && be.normal_order_closes_at) {
    if (now >= new Date(be.normal_order_opens_at) && now <= new Date(be.normal_order_closes_at)) {
      return "normal";
    }
  }
  if (be.onsite_order_opens_at && be.onsite_order_closes_at) {
    if (now >= new Date(be.onsite_order_opens_at) && now <= new Date(be.onsite_order_closes_at)) {
      return "onsite";
    }
  }
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return "normal";
  return null;
}

const orderSectionSummary = computed(() => {
  const be = props.be;
  if (be.orders_count > 0) return t("ed.order.ordersSubmitted", be.orders_count, { count: be.orders_count });
  const period = getCurrentOrderPeriod(be);
  if (period === "normal") return t("ed.order.normalOpen");
  if (period === "onsite") return t("ed.order.onsiteOpen", { rate: be.onsite_penalty_rate });
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return t("ed.order.submitOrder");
  const now = new Date();
  if (be.normal_order_opens_at && now < new Date(be.normal_order_opens_at)) return t("ed.order.notYetOpen");
  if (be.onsite_order_opens_at && now < new Date(be.onsite_order_opens_at)) return t("ed.order.normalClosed");
  return t("ed.order.allClosed");
});

// --- Open + scroll a section (called by parent for Hero / stepper jumps) ---
function openAndScroll(key) {
  sectionStates[key] = true;
  nextTick(() => {
    const el = wrapRefs[key];
    if (el?.scrollIntoView) {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  });
}

defineExpose({ openAndScroll });
</script>
