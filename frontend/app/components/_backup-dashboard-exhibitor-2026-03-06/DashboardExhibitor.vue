<template>
  <div class="mx-auto flex flex-col gap-y-6 pt-2 pb-16 lg:max-w-4xl lg:pt-4 xl:max-w-6xl">
    <DashboardGreeting />

    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="dashboard">
      <!-- Step 1: Complete Your Profile -->
      <div class="border-border rounded-xl border">
        <div class="flex items-center gap-x-3 px-5 py-4" :class="{ 'border-b': !dashboard.profile_complete }">
          <DashboardStepIndicator :step="1" :completed="dashboard.profile_complete" />
          <div class="min-w-0 flex-1">
            <h3 class="text-sm font-semibold tracking-tight">Complete Your Profile</h3>
            <p class="text-muted-foreground text-xs">
              {{ dashboard.profile_complete ? "Your profile is complete." : "Fill in all required fields to continue." }}
            </p>
          </div>
          <Icon
            v-if="dashboard.profile_complete"
            name="hugeicons:checkmark-circle-02"
            class="size-5 shrink-0 text-green-500"
          />
        </div>
        <form v-if="!dashboard.profile_complete" class="space-y-4 p-5" @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="ex_name">Full Name</Label>
              <Input id="ex_name" v-model="profileForm.name" placeholder="Your full name" />
            </div>
            <div class="space-y-2">
              <Label for="ex_phone">Phone Number</Label>
              <InputPhone v-model="profileForm.phone" id="ex_phone" />
            </div>
            <div class="space-y-2">
              <Label for="ex_title">Job Title</Label>
              <Input id="ex_title" v-model="profileForm.title" placeholder="e.g. Marketing Manager" />
            </div>
            <div class="space-y-2">
              <Label for="ex_company">Company Name</Label>
              <Input id="ex_company" v-model="profileForm.company_name" placeholder="Your company" />
            </div>
          </div>
          <Button type="submit" size="sm" :disabled="profileSaving">
            <Icon v-if="profileSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            Save Profile
          </Button>
        </form>
      </div>

      <!-- No brand events -->
      <div
        v-if="!dashboard.brand_events?.length"
        class="border-border flex flex-col items-center gap-3 rounded-xl border px-4 py-12"
      >
        <div class="bg-muted flex size-12 items-center justify-center rounded-full">
          <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-6" />
        </div>
        <p class="text-muted-foreground text-sm">No events found for your brands.</p>
      </div>

      <!-- Brand Event Cards -->
      <div v-for="be in dashboard.brand_events" :key="be.brand_event_id" class="space-y-3">
        <!-- Event Header -->
        <div class="flex items-center gap-x-3">
          <img
            v-if="be.event.poster_image?.sm"
            :src="be.event.poster_image.sm"
            :alt="be.event.title"
            class="size-10 shrink-0 rounded-lg object-cover"
          />
          <div
            v-else
            class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:calendar-03" class="size-5" />
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="truncate font-semibold tracking-tight">{{ be.event.title }}</h3>
            <div class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs">
              <span v-if="be.event.date_label">{{ be.event.date_label }}</span>
              <span v-if="be.event.location">{{ be.event.location }}</span>
            </div>
          </div>
          <div class="text-right">
            <p class="text-xs font-medium tracking-tight">{{ be.brand.name }}</p>
            <p v-if="be.booth_number" class="text-muted-foreground text-xs">
              Booth {{ be.booth_number }} · {{ be.booth_type_label }}
            </p>
          </div>
        </div>

        <!-- Steps -->
        <div class="border-border divide-border divide-y rounded-xl border">
          <!-- Step 2: Event Rules -->
          <div v-if="be.event_rules?.length" class="p-5">
            <div class="flex items-center gap-x-3">
              <DashboardStepIndicator :step="2" :completed="be.event_rules_agreed" :locked="!dashboard.profile_complete" />
              <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold tracking-tight">Event Rules</h4>
                <p class="text-muted-foreground text-xs">Read and agree to all event rules to proceed.</p>
              </div>
              <Icon
                v-if="!dashboard.profile_complete"
                name="hugeicons:lock-01"
                class="text-muted-foreground size-5 shrink-0"
              />
              <Icon
                v-else-if="be.event_rules_agreed"
                name="hugeicons:checkmark-circle-02"
                class="size-5 shrink-0 text-green-500"
              />
              <Icon
                v-else
                name="hugeicons:alert-02"
                class="size-5 shrink-0 text-amber-500"
              />
            </div>

            <div v-if="dashboard.profile_complete" class="mt-4 space-y-4">
              <div v-for="rule in be.event_rules" :key="rule.document.id" class="space-y-2">
                <!-- Document description -->
                <div v-if="rule.document.description" class="prose prose-sm text-muted-foreground max-w-none" v-html="rule.document.description" />

                <!-- Download links -->
                <div v-if="rule.document.template_en || rule.document.template_id" class="flex flex-wrap gap-2">
                  <a
                    v-if="rule.document.template_en"
                    :href="getMediaUrl(rule.document.template_en)"
                    target="_blank"
                    class="text-primary inline-flex items-center gap-x-1 text-xs hover:underline"
                  >
                    <Icon name="hugeicons:download-04" class="size-3" />
                    Download (EN)
                  </a>
                  <a
                    v-if="rule.document.template_id"
                    :href="getMediaUrl(rule.document.template_id)"
                    target="_blank"
                    class="text-primary inline-flex items-center gap-x-1 text-xs hover:underline"
                  >
                    <Icon name="hugeicons:download-04" class="size-3" />
                    Download (ID)
                  </a>
                </div>

                <!-- Checkbox -->
                <div class="flex items-start gap-x-2">
                  <Checkbox
                    :id="`rule_${be.brand_event_id}_${rule.document.id}`"
                    :checked="rule.agreed && !rule.needs_reagreement"
                    :disabled="agreeingId === rule.document.id"
                    @click="handleAgreeRule(be, rule)"
                  />
                  <Label
                    :for="`rule_${be.brand_event_id}_${rule.document.id}`"
                    class="text-sm font-normal leading-snug"
                  >
                    I have read and agree to all the rules in "{{ rule.document.title }}"
                  </Label>
                </div>

                <p v-if="rule.agreed && rule.submission" class="text-muted-foreground text-xs">
                  Agreed on {{ formatDate(rule.submission.agreed_at) }}
                  <span v-if="rule.submission.submitter_name"> by {{ rule.submission.submitter_name }}</span>
                  (v{{ rule.submission.document_version }})
                </p>
                <p v-if="rule.needs_reagreement" class="text-xs text-amber-600">
                  The rules have been updated. Please re-agree to the latest version.
                </p>
              </div>
            </div>
          </div>

          <!-- Step 3: Brand Profile -->
          <div class="p-5">
            <div class="flex items-center gap-x-3">
              <DashboardStepIndicator
                :step="be.event_rules?.length ? 3 : 2"
                :completed="be.brand_complete"
                :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              />
              <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold tracking-tight">Brand Profile</h4>
                <p class="text-muted-foreground text-xs">
                  {{ be.brand_complete ? "Brand profile is complete." : `Missing: ${be.brand.missing_fields.join(", ")}` }}
                </p>
              </div>
              <NuxtLink
                v-if="isUnlocked(be, false)"
                :to="`/brands/${be.brand.slug}/edit`"
                class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition"
              >
                <Icon name="hugeicons:edit-02" class="size-3.5" />
                {{ be.brand_complete ? "View" : "Complete" }}
              </NuxtLink>
            </div>
          </div>

          <!-- Step 4: Promotion Posts -->
          <div class="p-5">
            <div class="flex items-center gap-x-3">
              <DashboardStepIndicator
                :step="be.event_rules?.length ? 4 : 3"
                :completed="be.promotion_posts_count > 0"
                :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              />
              <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold tracking-tight">Promotion Posts</h4>
                <p class="text-muted-foreground text-xs">
                  {{ be.promotion_posts_count }} / {{ be.promotion_post_limit }} uploaded
                  <span v-if="be.promotion_post_deadline"> · Deadline: {{ formatDate(be.promotion_post_deadline) }}</span>
                </p>
              </div>
              <NuxtLink
                v-if="isUnlocked(be, false)"
                :to="`/brands/${be.brand.slug}/promotion-posts/${be.brand_event_id}`"
                class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition"
              >
                <Icon name="hugeicons:image-02" class="size-3.5" />
                {{ be.promotion_posts_count > 0 ? "Manage" : "Upload" }}
              </NuxtLink>
            </div>
          </div>

          <!-- Step 5: Operational Documents -->
          <div v-if="be.documents?.length" class="p-5">
            <div class="flex items-center gap-x-3">
              <DashboardStepIndicator
                :step="be.event_rules?.length ? 5 : 4"
                :completed="be.documents_total > 0 && be.documents_completed === be.documents_total"
                :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              />
              <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold tracking-tight">Operational Documents</h4>
                <p class="text-muted-foreground text-xs">
                  {{ be.documents_completed }} / {{ be.documents_total }} completed
                </p>
              </div>
              <NuxtLink
                v-if="isUnlocked(be, false)"
                :to="`/brands/${be.brand.slug}/documents/${be.brand_event_id}`"
                class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition"
              >
                <Icon name="hugeicons:document-01" class="size-3.5" />
                {{ be.documents_completed === be.documents_total ? "View" : "Complete" }}
              </NuxtLink>
            </div>

            <!-- Inline document items (collapsed by default) -->
            <div v-if="isUnlocked(be, false)" class="mt-3 space-y-2">
              <div
                v-for="doc in be.documents"
                :key="doc.document.id"
                class="flex items-center gap-x-2 text-xs"
              >
                <Icon
                  :name="docStatusIcon(doc.status)"
                  :class="docStatusColor(doc.status)"
                  class="size-3.5 shrink-0"
                />
                <span class="min-w-0 flex-1 truncate tracking-tight">{{ doc.document.title }}</span>
                <span class="text-muted-foreground shrink-0">
                  {{ documentTypeLabel(doc.document.document_type) }}
                </span>
                <Badge v-if="doc.document.is_required" variant="outline" class="text-[10px] font-normal">
                  Required
                </Badge>
              </div>
            </div>
          </div>

          <!-- Step 5b: Fascia & Badge Names (inline text inputs) -->
          <div
            v-if="showFasciaField(be) || showBadgeField(be)"
            class="p-5"
          >
            <div class="flex items-center gap-x-3 mb-3">
              <div class="flex size-6 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon name="hugeicons:text-font" class="size-3.5" />
              </div>
              <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold tracking-tight">Booth Details</h4>
                <p class="text-muted-foreground text-xs">Additional information for your booth.</p>
              </div>
            </div>

            <div
              v-if="isUnlocked(be, false)"
              class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
              <div v-if="showFasciaField(be)" class="space-y-2">
                <Label :for="`fascia_${be.brand_event_id}`">Fascia Name</Label>
                <Input
                  :id="`fascia_${be.brand_event_id}`"
                  :model-value="boothFields[be.brand_event_id]?.fascia_name ?? be.fascia_name ?? ''"
                  @update:model-value="(v) => setBoothField(be.brand_event_id, 'fascia_name', v)"
                  placeholder="Name displayed on booth fascia"
                />
              </div>
              <div v-if="showBadgeField(be)" class="space-y-2">
                <Label :for="`badge_${be.brand_event_id}`">Badge Name</Label>
                <Input
                  :id="`badge_${be.brand_event_id}`"
                  :model-value="boothFields[be.brand_event_id]?.badge_name ?? be.badge_name ?? ''"
                  @update:model-value="(v) => setBoothField(be.brand_event_id, 'badge_name', v)"
                  placeholder="Name displayed on exhibitor badge"
                />
              </div>
              <div class="sm:col-span-2">
                <Button
                  size="sm"
                  :disabled="savingBoothFields === be.brand_event_id"
                  @click="saveBoothFields(be)"
                >
                  <Icon v-if="savingBoothFields === be.brand_event_id" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                  Save
                </Button>
              </div>
            </div>
          </div>

          <!-- Step 6: Order Form -->
          <div class="p-5">
            <div class="flex items-center gap-x-3">
              <DashboardStepIndicator
                :step="getOrderFormStep(be)"
                :completed="be.orders_count > 0"
                :locked="!dashboard.profile_complete || (be.event_rules?.length > 0 && !be.event_rules_agreed)"
              />
              <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold tracking-tight">Order Form</h4>
                <p class="text-muted-foreground text-xs">
                  {{ be.orders_count > 0 ? `${be.orders_count} order(s) submitted` : "Submit your order for this event." }}
                  <span v-if="be.order_form_deadline"> · Deadline: {{ formatDate(be.order_form_deadline) }}</span>
                </p>
              </div>
              <div v-if="isUnlocked(be, false)" class="flex items-center gap-x-1">
                <NuxtLink
                  v-if="be.orders_count > 0"
                  :to="`/brands/${be.brand.slug}/orders/${be.brand_event_id}`"
                  class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition"
                >
                  <Icon name="hugeicons:shopping-bag-01" class="size-3.5" />
                  My Orders
                </NuxtLink>
                <NuxtLink
                  :to="`/brands/${be.brand.slug}/order-form/${be.brand_event_id}`"
                  class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium tracking-tight transition"
                >
                  <Icon name="hugeicons:shopping-cart-01" class="size-3.5" />
                  {{ be.orders_count > 0 ? "New Order" : "Order Form" }}
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "vue-sonner";

const client = useSanctumClient();

const data = ref(null);
const pending = ref(true);
const dashboard = computed(() => data.value?.data);
const agreeingId = ref(null);
const boothFields = ref({});
const savingBoothFields = ref(null);

// Profile form
const profileForm = reactive({
  name: "",
  phone: "",
  title: "",
  company_name: "",
});
const profileSaving = ref(false);

function initProfileForm() {
  const user = dashboard.value?.user;
  if (user) {
    profileForm.name = user.name || "";
    profileForm.phone = user.phone || "";
    profileForm.title = user.title || "";
    profileForm.company_name = user.company_name || "";
  }
}

async function fetchData() {
  try {
    data.value = await client("/api/exhibitor/dashboard");
    initProfileForm();
  } catch (e) {
    console.error("Failed to fetch exhibitor dashboard:", e);
  }
  pending.value = false;
}

async function saveProfile() {
  profileSaving.value = true;
  try {
    await client("/api/user/profile", { method: "PUT", body: profileForm });
    toast.success("Profile updated");
    await fetchData();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to update profile");
  } finally {
    profileSaving.value = false;
  }
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function getMediaUrl(media) {
  if (!media) return "";
  if (typeof media === "string") return media;
  return media.url || media.original || "";
}

function isUnlocked(be, requireRules = true) {
  if (!dashboard.value?.profile_complete) return false;
  if (requireRules && be.event_rules?.length > 0 && !be.event_rules_agreed) return false;
  return true;
}

// --- Event Rules ---
async function handleAgreeRule(be, rule) {
  if (rule.agreed && !rule.needs_reagreement) return;
  agreeingId.value = rule.document.id;
  try {
    await client(
      `/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/documents/${rule.document.ulid}`,
      { method: "POST", body: {} }
    );
    toast.success("Agreement recorded");
    await fetchData();
  } catch (err) {
    toast.error(err?.data?.message || "Failed to submit");
  } finally {
    agreeingId.value = null;
  }
}

// --- Document helpers ---
const documentTypeLabels = {
  checkbox_agreement: "Checkbox",
  file_upload: "File Upload",
  text_input: "Text Input",
};

function documentTypeLabel(type) {
  return documentTypeLabels[type] || type;
}

function docStatusIcon(status) {
  if (status === "completed") return "hugeicons:checkmark-circle-02";
  if (status === "needs_reagreement") return "hugeicons:alert-02";
  return "hugeicons:circle";
}

function docStatusColor(status) {
  if (status === "completed") return "text-green-500";
  if (status === "needs_reagreement") return "text-amber-500";
  return "text-muted-foreground";
}

// --- Booth fields ---
function showFasciaField(be) {
  return be.booth_type === "standard_shell_scheme" || be.booth_type === "enhanced_shell_scheme";
}

function showBadgeField(be) {
  return !!be.booth_type;
}

function setBoothField(beId, field, value) {
  if (!boothFields.value[beId]) {
    boothFields.value[beId] = {};
  }
  boothFields.value[beId][field] = value;
}

async function saveBoothFields(be) {
  const fields = boothFields.value[be.brand_event_id] || {};
  savingBoothFields.value = be.brand_event_id;
  try {
    await client(
      `/api/exhibitor/brands/${be.brand.slug}/events/${be.brand_event_id}/booth-fields`,
      {
        method: "PUT",
        body: {
          fascia_name: fields.fascia_name ?? be.fascia_name,
          badge_name: fields.badge_name ?? be.badge_name,
        },
      }
    );
    toast.success("Booth details saved");
    await fetchData();
  } catch (err) {
    toast.error(err?.data?.message || "Failed to save booth details");
  } finally {
    savingBoothFields.value = null;
  }
}

// --- Step numbering ---
function getOrderFormStep(be) {
  let step = 2;
  if (be.event_rules?.length) step++; // event rules
  step++; // brand profile
  step++; // promotion posts
  if (be.documents?.length) step++; // operational documents
  return step;
}

onMounted(fetchData);
</script>
