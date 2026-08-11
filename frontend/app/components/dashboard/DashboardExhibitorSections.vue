<template>
  <div :ref="(el) => (wrapRefs.profile = el)" class="divide-border divide-y">
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
      <!-- All four fields are what `profile_complete` is computed from, so all
           four are required. The red asterisk comes from main.css off the
           `required` attribute - no manual markup. `data-invalid` on the Field
           is what turns the label red alongside the control's ring. -->
      <form class="space-y-4" novalidate @submit.prevent="saveProfile">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field :data-invalid="!!profileErrors.name">
            <FieldLabel :for="`ex_name_${be.brand_event_id}`">{{ $t("ed.profile.fullName") }}</FieldLabel>
            <Input
              :id="`ex_name_${be.brand_event_id}`"
              v-model="profileForm.name"
              required
              :aria-invalid="!!profileErrors.name"
              :placeholder="$t('ed.profile.placeholderName')"
              @blur="validateProfileField('name')"
            />
            <FieldError :errors="profileErrors.name" />
          </Field>
          <Field :data-invalid="!!profileErrors.phone">
            <FieldLabel :for="`ex_phone_${be.brand_event_id}`">{{ $t("ed.profile.phone") }}</FieldLabel>
            <InputPhone
              :id="`ex_phone_${be.brand_event_id}`"
              v-model="profileForm.phone"
              required
              :aria-invalid="!!profileErrors.phone"
              @blur="validateProfileField('phone')"
            />
            <FieldError :errors="profileErrors.phone" />
          </Field>
          <Field :data-invalid="!!profileErrors.title">
            <FieldLabel :for="`ex_title_${be.brand_event_id}`">{{ $t("ed.profile.jobTitle") }}</FieldLabel>
            <Input
              :id="`ex_title_${be.brand_event_id}`"
              v-model="profileForm.title"
              required
              :aria-invalid="!!profileErrors.title"
              :placeholder="$t('ed.profile.placeholderTitle')"
              @blur="validateProfileField('title')"
            />
            <FieldError :errors="profileErrors.title" />
          </Field>
          <Field :data-invalid="!!profileErrors.company_name">
            <FieldLabel :for="`ex_company_${be.brand_event_id}`">{{ $t("ed.profile.company") }}</FieldLabel>
            <Input
              :id="`ex_company_${be.brand_event_id}`"
              v-model="profileForm.company_name"
              required
              :aria-invalid="!!profileErrors.company_name"
              :placeholder="$t('ed.profile.placeholderCompany')"
              @blur="validateProfileField('company_name')"
            />
            <FieldError :errors="profileErrors.company_name" />
          </Field>
        </div>
        <Button type="submit" size="sm" :disabled="profileSaving">
          <Spinner v-if="profileSaving" class="mr-1.5 size-4" />
          {{ $t("ed.profile.save") }}
        </Button>
      </form>
    </DashboardExhibitorSection>

    <!-- Section 2: Event Rules -->
    <div v-if="be.event_rules?.length" :ref="(el) => (wrapRefs.rules = el)">
      <DashboardExhibitorSection
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
            <Field v-else orientation="horizontal">
              <Checkbox
                :id="`rule_${be.brand_event_id}_${rule.document.id}`"
                :model-value="!!checkedRules[rule.document.id]"
                @update:model-value="(v) => (checkedRules[rule.document.id] = v)"
              />
              <FieldContent>
                <FieldLabel
                  :for="`rule_${be.brand_event_id}_${rule.document.id}`"
                  class="font-normal"
                >
                  {{ agreeLabel(rule.document) }}
                </FieldLabel>
                <FieldDescription
                  v-if="rule.needs_reagreement"
                  class="text-warning-foreground mt-1"
                >
                  {{ $t("ed.rules.reagreeWarning") }}
                </FieldDescription>
                <!-- Inside FieldContent so it lines up with the label text
                     whatever gap the active style gives the Field. -->
                <Button
                  v-if="checkedRules[rule.document.id]"
                  size="sm"
                  class="mt-2 w-fit"
                  :disabled="agreeingId === rule.document.id"
                  @click="handleAgreeRule(rule)"
                >
                  <Spinner v-if="agreeingId === rule.document.id" class="mr-1.5 size-4" />
                  {{ $t("ed.rules.submit") }}
                </Button>
              </FieldContent>
            </Field>
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
        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
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
          <Button
            :to="`/brands/${be.brand.slug}/edit`"
            size="sm"
            variant="outline"
            class="w-full shrink-0 sm:w-auto"
          >
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
        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
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
            class="w-full shrink-0 sm:w-auto"
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

    <!-- Shared-booth notice: docs + order form handled under the primary brand -->
    <div v-if="!isBoothPrimary" class="border-border bg-muted/40 rounded-xl border border-dashed p-4 sm:p-5">
      <div class="flex items-start gap-3">
        <Icon name="hugeicons:information-circle" class="text-muted-foreground mt-0.5 size-5 shrink-0" />
        <p class="text-muted-foreground text-sm tracking-tight">
          {{
            $t("ed.booth.sharedNote", {
              booth: be.booth_number,
              brand: be.booth_primary_brand_name || $t("ed.booth.anotherBrand"),
            })
          }}
        </p>
      </div>
    </div>

    <!-- Section 5: Operational Documents -->
    <div
      v-if="isBoothPrimary && (be.documents?.length || showFascia || showBadge)"
      :ref="(el) => (wrapRefs.docs = el)"
    >
      <DashboardExhibitorSection
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
            <!-- Both fields gate `exhibitorDocsComplete`, so both are required
                 wherever they are shown. `<FieldLabel required>` rather than the
                 CSS rule: Badge Name puts a description between label and input,
                 so the adjacent-sibling selector would never match there. -->
            <Field v-if="showFascia" :data-invalid="!!boothErrors.fascia_name">
              <FieldLabel :for="`fascia_${be.brand_event_id}`" required>
                {{ $t("ed.docs.fasciaName") }}
              </FieldLabel>
              <Input
                :id="`fascia_${be.brand_event_id}`"
                :model-value="boothFields.fascia_name ?? be.fascia_name ?? ''"
                :placeholder="$t('ed.docs.fasciaPlaceholder')"
                maxlength="24"
                required
                :aria-invalid="!!boothErrors.fascia_name"
                @update:model-value="(v) => setBoothField('fascia_name', v.toUpperCase())"
              />
              <FieldError :errors="boothErrors.fascia_name" />
              <FieldDescription>
                {{ $t("ed.docs.fasciaHint") }}
              </FieldDescription>
              <FieldDescription v-if="be.event?.project_contact">
                {{ $t("ed.docs.fasciaContactHint") }}
                <a
                  :href="`https://wa.me/${be.event.project_contact}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-primary font-medium underline-offset-2 hover:underline"
                >
                  {{ $t("ed.docs.fasciaContactCta") }}
                </a>
              </FieldDescription>
            </Field>
            <Field v-if="showBadge" :data-invalid="!!boothErrors.badge_name">
              <FieldLabel :for="`badge_${be.brand_event_id}`" required>
                {{ $t("ed.docs.badgeName") }}
              </FieldLabel>
              <FieldDescription>
                {{ $t("ed.docs.badgeDescription") }}
              </FieldDescription>
              <Input
                :id="`badge_${be.brand_event_id}`"
                :model-value="boothFields.badge_name ?? be.badge_name ?? ''"
                :placeholder="$t('ed.docs.badgePlaceholder')"
                required
                :aria-invalid="!!boothErrors.badge_name"
                @update:model-value="(v) => setBoothField('badge_name', v)"
              />
              <FieldError :errors="boothErrors.badge_name" />
            </Field>
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
    <div v-if="isBoothPrimary" :ref="(el) => (wrapRefs.order = el)">
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
import {
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
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
const boothErrors = ref({});
const savingBoothFields = ref(false);
const wrapRefs = {};

// When a booth is shared by several brands, only the primary brand handles
// operational documents and the order form; the others see a note instead.
const isBoothPrimary = computed(() => props.be.is_booth_primary !== false);

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
const profileErrors = ref({});

const PROFILE_FIELDS = ["name", "phone", "title", "company_name"];

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

function profileFieldError(field) {
  return profileForm[field]?.trim() ? null : [t("ed.profile.fieldRequired")];
}

function validateProfileField(field) {
  const next = { ...profileErrors.value };
  const error = profileFieldError(field);

  if (error) next[field] = error;
  else delete next[field];

  profileErrors.value = next;
}

async function saveProfile() {
  // Every field at once, then scroll to the first one, rather than letting the
  // server reject them one round trip at a time.
  const errors = {};
  for (const field of PROFILE_FIELDS) {
    const error = profileFieldError(field);
    if (error) errors[field] = error;
  }
  profileErrors.value = errors;

  if (Object.keys(errors).length) {
    toast.error(t("ed.profile.fixErrors"));
    await nextTick();
    wrapRefs.profile
      ?.querySelector('[data-slot="field-error"]')
      ?.scrollIntoView({ behavior: "smooth", block: "center" });
    return;
  }

  profileSaving.value = true;
  try {
    await client("/api/exhibitor/profile", { method: "PUT", body: profileForm });
    toast.success(t("ed.profile.updated"));
    emit("refresh");
  } catch (e) {
    // ofetch parks the parsed body on `response._data`; reading `e.data` swallowed
    // every 422 detail.
    const body = e?.response?._data ?? e?.data;
    if (body?.errors) profileErrors.value = body.errors;
    toast.error(body?.message || t("ed.profile.failedToUpdate"));
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
  const fascia = boothFields.fascia_name ?? props.be.fascia_name ?? "";
  const badge = boothFields.badge_name ?? props.be.badge_name ?? "";

  const errors = {};
  if (showFascia.value && !fascia.trim()) errors.fascia_name = [t("ed.docs.fieldRequired")];
  if (showBadge.value && !badge.trim()) errors.badge_name = [t("ed.docs.fieldRequired")];
  boothErrors.value = errors;

  if (Object.keys(errors).length) {
    toast.error(t("ed.docs.fixErrors"));
    await nextTick();
    wrapRefs.docs
      ?.querySelector('[data-slot="field-error"]')
      ?.scrollIntoView({ behavior: "smooth", block: "center" });
    return;
  }

  savingBoothFields.value = true;
  try {
    await client(
      `/api/exhibitor/brands/${props.be.brand.slug}/events/${props.be.brand_event_id}/booth-fields`,
      {
        method: "PUT",
        body: { fascia_name: fascia, badge_name: badge },
      }
    );
    toast.success(t("ed.docs.saved"));
    emit("refresh");
  } catch (err) {
    const body = err?.response?._data ?? err?.data;
    if (body?.errors) boothErrors.value = body.errors;
    toast.error(body?.message || t("ed.docs.failedToSave"));
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
