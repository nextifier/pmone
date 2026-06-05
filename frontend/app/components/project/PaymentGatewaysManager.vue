<template>
  <div>
    <div v-if="loading" class="flex items-center justify-center py-10">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-sm tracking-tight">Loading</span>
      </div>
    </div>

    <div
      v-else-if="!gateways.length"
      class="border-border flex flex-col items-center justify-center gap-2 rounded-xl border py-12 text-center"
    >
      <div
        class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-full"
      >
        <Icon name="hugeicons:credit-card" class="size-5" />
      </div>
      <p class="text-sm font-medium tracking-tight">No payment gateways configured</p>
      <p class="text-muted-foreground text-sm tracking-tight">
        Add a payment gateway to start collecting payments for this project.
      </p>
    </div>

    <div v-else class="space-y-3" v-auto-animate>
      <div
        v-for="gateway in gateways"
        :key="gateway.id"
        class="bg-card border-border space-y-3 rounded-xl border p-4 shadow-sm sm:p-5"
      >
        <div class="flex items-center justify-between gap-3">
          <!-- Brand wordmark, no container. Same dark-mode treatment as the
               Payment column badge so the dark-ink logos read on a dark surface.
               Both share one height; their SVG viewBoxes are tight so the
               wordmarks line up. -->
          <img
            v-if="providerLogos[gateway.provider]"
            :src="providerLogos[gateway.provider]"
            :alt="gateway.provider"
            class="h-6 w-auto shrink-0 object-contain dark:brightness-90 dark:contrast-200 dark:grayscale dark:invert-[75%]"
          />
          <div
            v-else
            class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:credit-card" class="size-4.5" />
          </div>

          <div class="flex shrink-0 items-center gap-x-3">
            <div v-if="canUpdate" class="flex items-center gap-x-2">
              <Label
                :for="`active-${gateway.id}`"
                class="text-muted-foreground cursor-pointer text-sm font-normal tracking-tight"
              >
                Active
              </Label>
              <Switch
                :id="`active-${gateway.id}`"
                :modelValue="gateway.is_active"
                :disabled="togglingIds.has(gateway.id)"
                @update:modelValue="(v) => confirmToggle(gateway, v)"
              />
            </div>

            <div
              v-if="canUpdate || canDelete || hasDataViews(gateway)"
              class="flex items-center gap-x-1"
            >
              <DropdownMenu v-if="hasDataViews(gateway)" :modal="false">
                <DropdownMenuTrigger as-child>
                  <button
                    v-tippy="'Insights &amp; logs'"
                    type="button"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                  >
                    <Icon name="hugeicons:more-horizontal" class="size-4" />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem
                    v-if="canViewTransactions && gateway.capabilities?.includes('transactions')"
                    class="gap-x-2"
                    @click="openTransactions(gateway)"
                  >
                    <Icon name="hugeicons:invoice-01" class="size-4" />
                    Transactions
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="canViewSettlement && gateway.capabilities?.includes('settlement')"
                    class="gap-x-2"
                    @click="openSettlement(gateway)"
                  >
                    <Icon name="hugeicons:money-receive-01" class="size-4" />
                    Settlement
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="canViewReconciliation && gateway.capabilities?.includes('transactions')"
                    class="gap-x-2"
                    @click="openReconciliation(gateway)"
                  >
                    <Icon name="hugeicons:checkmark-circle-02" class="size-4" />
                    Reconciliation
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="canViewWebhookEvents"
                    class="gap-x-2"
                    @click="openWebhookEvents(gateway)"
                  >
                    <Icon name="hugeicons:notification-01" class="size-4" />
                    Webhook events
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
              <button
                v-if="canUpdate"
                type="button"
                class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                @click="openEditDialog(gateway)"
              >
                <Icon name="hugeicons:edit-02" class="size-4" />
              </button>
              <button
                v-if="canDelete"
                type="button"
                class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md p-1.5 transition"
                @click="openDeleteDialog(gateway)"
              >
                <Icon name="hugeicons:delete-01" class="size-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span class="text-sm font-medium tracking-tight capitalize sm:text-base">
            {{ gateway.provider }}
          </span>
          <span v-if="gateway.label" class="text-muted-foreground text-sm tracking-tight">
            · {{ gateway.label }}
          </span>
          <Badge
            v-if="modeMeta[gateway.mode]"
            :variant="modeMeta[gateway.mode].variant"
            :icon="modeMeta[gateway.mode].icon"
            :plain="false"
          >
            {{ modeMeta[gateway.mode].label }}
          </Badge>
        </div>

        <dl
          class="grid grid-cols-[max-content_minmax(0,1fr)] items-center gap-x-3 gap-y-1 text-sm tracking-tight sm:grid-cols-[8rem_minmax(0,1fr)]"
        >
          <dt class="text-muted-foreground whitespace-nowrap">Secret key</dt>
          <dd class="min-w-0">
            <code
              class="bg-muted/60 inline-flex w-fit items-center rounded px-1.5 py-0.5 font-mono text-xs sm:text-sm"
            >
              {{ gateway.secret_key_masked || "—" }}
            </code>
          </dd>

          <template v-if="gateway.provider !== 'midtrans'">
            <dt class="text-muted-foreground whitespace-nowrap">Webhook token</dt>
            <dd class="min-w-0">
              <code
                class="bg-muted/60 inline-flex w-fit items-center rounded px-1.5 py-0.5 font-mono text-xs sm:text-sm"
              >
                {{ gateway.webhook_token_masked || "—" }}
              </code>
            </dd>
          </template>

          <template v-if="gateway.webhook_url">
            <dt class="text-muted-foreground whitespace-nowrap">Webhook URL</dt>
            <dd class="flex min-w-0 items-center gap-x-1.5">
              <code
                class="bg-muted/60 min-w-0 flex-1 truncate rounded px-1.5 py-0.5 font-mono text-xs sm:text-sm"
              >
                {{ gateway.webhook_url }}
              </code>
              <ButtonCopy :text="gateway.webhook_url" />
            </dd>
          </template>

          <template v-if="gateway.redirect_url">
            <dt class="text-muted-foreground whitespace-nowrap">Redirect URL</dt>
            <dd class="flex min-w-0 items-center gap-x-1.5">
              <code
                class="bg-muted/60 min-w-0 flex-1 truncate rounded px-1.5 py-0.5 font-mono text-xs sm:text-sm"
              >
                {{ gateway.redirect_url }}
              </code>
              <ButtonCopy :text="gateway.redirect_url" />
            </dd>
          </template>
        </dl>

        <ProjectPaymentGatewayBalanceCard
          v-if="canViewBalance && gateway.capabilities?.includes('balance')"
          :project-username="projectUsername"
          :gateway-id="gateway.id"
        />
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="formDialogOpen" dialog-max-width="32rem">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="page-title">
            {{ editing ? "Edit Payment Gateway" : "Add Payment Gateway" }}
          </h3>
          <p class="page-description">Provider credentials are encrypted before saving.</p>
        </div>

        <form @submit.prevent="saveGateway" autocomplete="off" class="mt-4 space-y-4">
          <input type="text" name="prevent_autofill" class="hidden" autocomplete="username" />
          <input
            type="password"
            name="prevent_autofill_pw"
            class="hidden"
            autocomplete="new-password"
          />
          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-2">
              <Label for="pg_provider">Provider</Label>
              <Select v-model="form.provider" :disabled="!!editing">
                <SelectTrigger id="pg_provider">
                  <SelectValue placeholder="Select provider" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="xendit">Xendit</SelectItem>
                  <SelectItem value="midtrans">Midtrans</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="pg_mode">Mode</Label>
              <Select v-model="form.mode">
                <SelectTrigger id="pg_mode">
                  <SelectValue placeholder="Select mode" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="live">Live (Production)</SelectItem>
                  <SelectItem value="test">Test (Sandbox)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div v-if="!isMidtrans" class="space-y-2">
            <Label for="pg_checkout_method">Checkout Method</Label>
            <Select v-model="form.checkout_method">
              <SelectTrigger id="pg_checkout_method">
                <SelectValue placeholder="Select checkout method" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="method in checkoutMethodOptions"
                  :key="method.value"
                  :value="method.value"
                  :disabled="!method.available"
                >
                  {{ method.label
                  }}<template v-if="!method.available"> · Coming soon</template>
                </SelectItem>
              </SelectContent>
            </Select>
            <p
              v-if="selectedCheckoutMethod"
              class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >
              {{ selectedCheckoutMethod.description }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="pg_label">Label</Label>
            <Input
              id="pg_label"
              v-model="form.label"
              placeholder="e.g. Main production account"
              autocomplete="off"
              data-1p-ignore
              data-lpignore="true"
            />
          </div>

          <div v-if="isMidtrans" class="space-y-2">
            <Label for="pg_client_key">Client Key</Label>
            <Input
              id="pg_client_key"
              v-model="form.public_key"
              placeholder="SB-Mid-client-… (optional)"
              autocomplete="off"
              data-1p-ignore
              data-lpignore="true"
            />
          </div>

          <div class="space-y-2">
            <Label for="pg_secret">{{ isMidtrans ? "Server Key" : "Secret API Key" }}</Label>
            <InputPassword
              id="pg_secret"
              v-model="form.secret_key"
              :required="!editing"
              :placeholder="editing ? '•••••••• (leave blank to keep)' : ''"
              autocomplete="new-password"
              data-1p-ignore
              data-lpignore="true"
            />
            <p v-if="isMidtrans" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Midtrans verifies webhooks with the Server Key (SHA512), so no separate webhook token is needed.
            </p>
          </div>

          <div v-if="!isMidtrans" class="space-y-2">
            <Label for="pg_webhook_token">Webhook Verification Token</Label>
            <InputPassword
              id="pg_webhook_token"
              v-model="form.webhook_token"
              :required="!editing"
              :placeholder="editing ? '•••••••• (leave blank to keep)' : ''"
              autocomplete="new-password"
              data-1p-ignore
              data-lpignore="true"
            />
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <div class="text-sm tracking-tight">
                <p class="font-medium">Test Connection</p>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Verify credentials with the provider before saving.
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="testing || !form.secret_key"
                @click="runTestConnection"
              >
                <Spinner v-if="testing" />
                <Icon v-else name="hugeicons:wifi-connected-01" class="size-4" />
                {{ testing ? "Testing..." : "Test Connection" }}
              </Button>
            </div>

            <div
              v-if="testResult"
              :class="[
                'rounded-md border p-3 text-sm tracking-tight',
                testResult.success
                  ? 'border-success/40 bg-success/10'
                  : 'border-destructive/40 bg-destructive/10',
              ]"
            >
              <div class="flex items-start gap-2">
                <Icon
                  :name="
                    testResult.success ? 'hugeicons:checkmark-circle-02' : 'hugeicons:alert-circle'
                  "
                  :class="[
                    'mt-0.5 size-4 shrink-0',
                    testResult.success ? 'text-success-foreground' : 'text-destructive',
                  ]"
                />
                <div class="flex-1 space-y-1">
                  <p
                    :class="[
                      'font-medium',
                      testResult.success ? 'text-success-foreground' : 'text-destructive',
                    ]"
                  >
                    {{
                      testResult.success
                        ? "Connection OK"
                        : testResult.error_code || "Connection failed"
                    }}
                  </p>
                  <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                    {{ testResult.message }}
                  </p>
                  <p
                    v-if="testResult.success && testResult.channels_count !== undefined"
                    class="text-muted-foreground text-xs tracking-tight sm:text-sm"
                  >
                    {{ testResult.channels_count }} payment channel{{
                      testResult.channels_count === 1 ? "" : "s"
                    }}
                    active on this account.
                  </p>
                  <p
                    v-if="testResult.webhook_token"
                    :class="[
                      'text-xs tracking-tight sm:text-sm',
                      testResult.webhook_token.ok ? 'text-muted-foreground' : 'text-destructive',
                    ]"
                  >
                    Webhook token: {{ testResult.webhook_token.message }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <Button
              variant="outline"
              type="button"
              :disabled="saving"
              @click="formDialogOpen = false"
            >
              Cancel
            </Button>
            <Button type="submit" :disabled="saving">
              <Spinner v-if="saving" />
              {{ editing ? "Save Changes" : "Add Gateway" }}
              <KbdGroup>
                <Kbd>{{ metaSymbol }}</Kbd>
                <Kbd>S</Kbd>
              </KbdGroup>
            </Button>
          </div>
        </form>
      </div>
    </DialogResponsive>

    <!-- Toggle active confirmation -->
    <DialogResponsive v-model:open="toggleDialogOpen">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="page-title">
            {{ pendingToggle?.value ? "Activate this gateway?" : "Deactivate this gateway?" }}
          </h3>
          <p class="page-description">
            <template v-if="pendingToggle?.value">
              Setting
              <span class="text-foreground font-medium">
                {{ pendingToggle?.gateway?.provider
                }}<template v-if="pendingToggle?.gateway?.label">
                  ({{ pendingToggle.gateway.label }})</template
                >
              </span>
              as active will deactivate the currently active gateway for this project. New invoices
              will use this gateway's credentials.
            </template>
            <template v-else>
              Deactivating
              <span class="text-foreground font-medium">
                {{ pendingToggle?.gateway?.provider
                }}<template v-if="pendingToggle?.gateway?.label">
                  ({{ pendingToggle.gateway.label }})</template
                >
              </span>
              means new invoices for this project can no longer be created until another gateway is
              activated. Past transactions are unaffected.
            </template>
          </p>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" type="button" :disabled="togglePending" @click="cancelToggle">
            Cancel
          </Button>
          <Button
            :variant="pendingToggle?.value ? 'default' : 'destructive'"
            type="button"
            :disabled="togglePending"
            @click="handleToggleConfirm"
          >
            <Spinner v-if="togglePending" :class="pendingToggle?.value ? '' : 'text-white'" />
            {{ pendingToggle?.value ? "Activate" : "Deactivate" }}
          </Button>
        </div>
      </div>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-1">
          <h3 class="page-title">Delete this gateway?</h3>
          <p class="page-description">
            Removing the
            <span class="text-foreground font-medium">{{ gatewayToDelete?.provider }}</span>
            gateway means new invoices for this project can no longer be created until another
            active gateway exists. Past transactions are unaffected.
          </p>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <Button
            variant="outline"
            type="button"
            :disabled="deletePending"
            @click="deleteDialogOpen = false"
          >
            Cancel
          </Button>
          <Button
            variant="destructive"
            type="button"
            :disabled="deletePending"
            @click="handleDelete"
          >
            <Spinner v-if="deletePending" class="text-white" />
            Delete
          </Button>
        </div>
      </div>
    </DialogResponsive>

    <!-- Transactions -->
    <ProjectPaymentGatewayTransactionsDialog
      v-model:open="transactionsDialogOpen"
      :project-username="projectUsername"
      :gateway="transactionsGateway"
    />

    <!-- Webhook events -->
    <ProjectPaymentGatewayWebhookEventsDialog
      v-model:open="webhookEventsDialogOpen"
      :project-username="projectUsername"
      :gateway="webhookEventsGateway"
    />

    <!-- Reconciliation -->
    <ProjectPaymentGatewayReconciliationDialog
      v-model:open="reconciliationDialogOpen"
      :project-username="projectUsername"
      :gateway="reconciliationGateway"
    />

    <!-- Settlement -->
    <ProjectPaymentGatewaySettlementDialog
      v-model:open="settlementDialogOpen"
      :project-username="projectUsername"
      :gateway="settlementGateway"
    />
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

// Mirrors the badge meta used in the Reservations Mode column so a gateway's
// environment reads the same way wherever it surfaces in the admin.
const modeMeta = {
  live: { label: "Live", variant: "success", icon: "hugeicons:rocket-01" },
  test: { label: "Test", variant: "warning", icon: "hugeicons:test-tube-01" },
};

// Provider brand wordmarks (SVGs in /public/img/payment-methods). Rendered on
// a fixed light chip in the card header; a provider without a logo falls back to
// the generic card icon.
const providerLogos = {
  xendit: "/img/payment-methods/xendit.svg",
  midtrans: "/img/payment-methods/midtrans.svg",
};

const props = defineProps({
  projectUsername: { type: String, required: true },
});

const client = useSanctumClient();
const { metaSymbol } = useShortcuts();
const { hasPermission } = usePermission();

const canUpdate = computed(() => hasPermission("payment_gateways.update"));
const canDelete = computed(() => hasPermission("payment_gateways.delete"));
const canViewBalance = computed(() => hasPermission("payment_gateways.view_balance"));
const canViewTransactions = computed(() =>
  hasPermission("payment_gateways.view_transactions")
);
const canViewWebhookEvents = computed(() =>
  hasPermission("payment_gateways.view_webhook_events")
);
const canViewReconciliation = computed(() =>
  hasPermission("payment_gateways.view_reconciliation")
);
const canViewSettlement = computed(() =>
  hasPermission("payment_gateways.view_settlement")
);

// Whether the gateway has at least one data/insight view to surface in the menu.
function hasDataViews(gateway) {
  const caps = gateway.capabilities || [];
  return (
    (canViewTransactions.value && caps.includes("transactions")) ||
    (canViewSettlement.value && caps.includes("settlement")) ||
    (canViewReconciliation.value && caps.includes("transactions")) ||
    canViewWebhookEvents.value
  );
}

const transactionsDialogOpen = ref(false);
const transactionsGateway = ref(null);

function openTransactions(gateway) {
  transactionsGateway.value = gateway;
  transactionsDialogOpen.value = true;
}

const webhookEventsDialogOpen = ref(false);
const webhookEventsGateway = ref(null);

function openWebhookEvents(gateway) {
  webhookEventsGateway.value = gateway;
  webhookEventsDialogOpen.value = true;
}

const reconciliationDialogOpen = ref(false);
const reconciliationGateway = ref(null);

function openReconciliation(gateway) {
  reconciliationGateway.value = gateway;
  reconciliationDialogOpen.value = true;
}

const settlementDialogOpen = ref(false);
const settlementGateway = ref(null);

function openSettlement(gateway) {
  settlementGateway.value = gateway;
  settlementDialogOpen.value = true;
}

const gateways = ref([]);
const loading = ref(true);
const saving = ref(false);
const editing = ref(null);
const togglingIds = ref(new Set());

const testing = ref(false);
const testResult = ref(null);

const formDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const gatewayToDelete = ref(null);
const deletePending = ref(false);

const toggleDialogOpen = ref(false);
const pendingToggle = ref(null);
const togglePending = ref(false);

const form = reactive({
  provider: "xendit",
  label: "",
  mode: "live",
  checkout_method: "payment_link_sessions",
  secret_key: "",
  public_key: "",
  webhook_token: "",
  config: {},
});

// Midtrans verifies webhooks with the Server Key (SHA512) and only offers the
// Snap checkout, so the checkout-method + webhook-token fields are hidden and a
// Client Key field is shown instead.
const isMidtrans = computed(() => form.provider === "midtrans");

// Checkout-method options. The backend is the source of truth — each gateway
// resource ships `available_checkout_methods` — and this mirrors the
// CheckoutMethod enum as the fallback for the create form, where no gateway
// exists yet to read the list from.
const DEFAULT_CHECKOUT_METHODS = [
  {
    value: "payment_link_sessions",
    label: "Payment Link - Sessions",
    description:
      "Provider-hosted checkout via the Sessions API. Fastest and least effort to integrate.",
    available: true,
  },
  {
    value: "payment_link_legacy",
    label: "Payment Link - Legacy",
    description:
      "The legacy Invoices API checkout page. Kept for backwards compatibility; not recommended for new gateways.",
    available: true,
  },
];

const checkoutMethodOptions = ref(DEFAULT_CHECKOUT_METHODS);

const selectedCheckoutMethod = computed(() =>
  checkoutMethodOptions.value.find((m) => m.value === form.checkout_method)
);

function resetForm() {
  form.provider = "xendit";
  form.label = "";
  form.mode = "live";
  form.checkout_method = "payment_link_sessions";
  form.secret_key = "";
  form.public_key = "";
  form.webhook_token = "";
  form.config = {};
  checkoutMethodOptions.value = DEFAULT_CHECKOUT_METHODS;
  editing.value = null;
  testResult.value = null;
}

// Re-show the test panel only after the most recent test. Any subsequent
// edit to the credential fields invalidates the previous verdict to avoid
// stale "OK" badges next to changed values.
watch(
  () => [form.secret_key, form.webhook_token, form.mode, form.provider],
  () => {
    testResult.value = null;
  }
);

async function runTestConnection() {
  if (!form.secret_key) return;

  testing.value = true;
  testResult.value = null;
  try {
    testResult.value = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/test-connection`,
      {
        method: "POST",
        body: {
          provider: form.provider,
          mode: form.mode,
          secret_key: form.secret_key,
          ...(isMidtrans.value ? {} : { webhook_token: form.webhook_token || null }),
        },
      }
    );
  } catch (e) {
    testResult.value = e?.data || {
      success: false,
      error_code: "UNKNOWN",
      message: e?.message || "Test connection request failed.",
    };
  } finally {
    testing.value = false;
  }
}

async function fetchGateways() {
  loading.value = true;
  try {
    const res = await client(`/api/projects/${props.projectUsername}/payment-gateways`);
    gateways.value = res.data || [];
  } catch (e) {
    toast.error(e?.data?.message || "Failed to load payment gateways");
  } finally {
    loading.value = false;
  }
}

function openCreateDialog() {
  resetForm();
  formDialogOpen.value = true;
}

function openEditDialog(gateway) {
  resetForm();
  editing.value = gateway.id;
  form.provider = gateway.provider;
  form.label = gateway.label || "";
  form.mode = gateway.mode;
  form.checkout_method = gateway.checkout_method || "payment_link_legacy";
  form.public_key = gateway.public_key || "";
  form.config = { ...(gateway.config || {}) };
  if (gateway.available_checkout_methods?.length) {
    checkoutMethodOptions.value = gateway.available_checkout_methods;
  }
  formDialogOpen.value = true;
}

async function saveGateway() {
  saving.value = true;

  try {
    const body = {
      label: form.label || null,
      mode: form.mode,
      config: form.config,
    };

    if (!isMidtrans.value) {
      body.checkout_method = form.checkout_method;
    }

    if (form.secret_key) {
      body.secret_key = form.secret_key;
    }

    if (isMidtrans.value) {
      // Client Key is public; empty = keep existing on edit.
      body.public_key = form.public_key || null;
    } else if (form.webhook_token) {
      body.webhook_token = form.webhook_token;
    }

    if (editing.value) {
      await client(`/api/projects/${props.projectUsername}/payment-gateways/${editing.value}`, {
        method: "PATCH",
        body,
      });
      toast.success("Gateway updated");
    } else {
      body.provider = form.provider;
      body.is_active = false;
      await client(`/api/projects/${props.projectUsername}/payment-gateways`, {
        method: "POST",
        body,
      });
      toast.success("Gateway added");
    }

    formDialogOpen.value = false;
    resetForm();
    await fetchGateways();
  } catch (e) {
    const msg = e?.data?.errors
      ? Object.values(e.data.errors)[0][0]
      : e?.data?.message || "Failed to save gateway";
    toast.error(msg);
  } finally {
    saving.value = false;
  }
}

function confirmToggle(gateway, value) {
  if (gateway.is_active === value) return;
  if (togglingIds.value.has(gateway.id)) return;
  pendingToggle.value = { gateway, value };
  toggleDialogOpen.value = true;
}

function cancelToggle() {
  toggleDialogOpen.value = false;
}

watch(toggleDialogOpen, (open) => {
  if (!open && !togglePending.value) {
    pendingToggle.value = null;
    gateways.value = [...gateways.value];
  }
});

async function handleToggleConfirm() {
  if (!pendingToggle.value) return;
  const { gateway, value } = pendingToggle.value;
  togglePending.value = true;
  try {
    await toggleActive(gateway, value);
    toggleDialogOpen.value = false;
    pendingToggle.value = null;
  } finally {
    togglePending.value = false;
  }
}

async function toggleActive(gateway, value) {
  if (togglingIds.value.has(gateway.id)) return;

  togglingIds.value = new Set([...togglingIds.value, gateway.id]);

  const previous = gateways.value.map((g) => ({ ...g }));

  if (value) {
    gateways.value = gateways.value.map((g) => ({
      ...g,
      is_active: g.id === gateway.id,
    }));
  } else {
    gateways.value = gateways.value.map((g) =>
      g.id === gateway.id ? { ...g, is_active: false } : g
    );
  }

  try {
    await client(`/api/projects/${props.projectUsername}/payment-gateways/${gateway.id}`, {
      method: "PATCH",
      body: { is_active: value },
    });
  } catch (e) {
    gateways.value = previous;
    toast.error(e?.data?.message || "Failed to update gateway status");
  } finally {
    const next = new Set(togglingIds.value);
    next.delete(gateway.id);
    togglingIds.value = next;
  }
}

function openDeleteDialog(gateway) {
  gatewayToDelete.value = gateway;
  deleteDialogOpen.value = true;
}

async function handleDelete() {
  if (!gatewayToDelete.value) return;
  deletePending.value = true;

  try {
    await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${gatewayToDelete.value.id}`,
      { method: "DELETE" }
    );
    toast.success("Gateway deleted");
    deleteDialogOpen.value = false;
    gatewayToDelete.value = null;
    await fetchGateways();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete gateway");
  } finally {
    deletePending.value = false;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      if (formDialogOpen.value && !saving.value) saveGateway();
    },
  },
});

defineExpose({ openCreateDialog });

onMounted(fetchGateways);
</script>
