<template>
  <div class="flex flex-col gap-y-6">
    <!-- Two-factor -->
    <div class="flex flex-col gap-y-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-start gap-x-3">
        <div class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
          <Icon name="hugeicons:shield-key" class="size-4.5" />
        </div>
        <div>
          <h2 class="text-sm font-medium tracking-tight">Two-factor authentication</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            <span v-if="user.two_factor_confirmed" class="text-green-600 dark:text-green-500">Enabled</span>
            <span v-else>Not enabled for this account.</span>
          </p>
        </div>
      </div>
      <DialogResponsive v-if="canReset2fa && user.two_factor_confirmed" v-model:open="reset2faOpen">
        <template #trigger="{ open }">
          <Button variant="outline-destructive" size="sm" class="shrink-0" @click="open()">
            <Icon name="hugeicons:shield-energy" class="size-4 shrink-0" />
            <span>Reset 2FA</span>
          </Button>
        </template>
        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <div class="text-primary text-lg font-semibold tracking-tight">Reset two-factor?</div>
            <p class="text-body mt-1.5 text-sm tracking-tight">
              This disables 2FA for {{ user.name }}. They will need to set it up again. This action is logged.
            </p>
            <div class="mt-3 flex justify-end gap-2">
              <button class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98" :disabled="busy" @click="reset2faOpen = false">Cancel</button>
              <button class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:opacity-50" :disabled="busy" @click="reset2fa">
                <Spinner v-if="busy" class="size-4 text-white" />
                <span v-else>Reset 2FA</span>
              </button>
            </div>
          </div>
        </template>
      </DialogResponsive>
    </div>

    <!-- Account emails -->
    <div v-if="canSendEmails" class="flex flex-col gap-y-3 rounded-lg border p-4">
      <div>
        <h2 class="text-sm font-medium tracking-tight">Account emails</h2>
        <p class="text-muted-foreground text-sm tracking-tight">Send account recovery emails to this user.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" :disabled="busy" @click="sendPasswordReset">
          <Icon name="hugeicons:key-01" class="size-4 shrink-0" />
          <span>Send password reset</span>
        </Button>
        <Button
          v-if="!user.email_verified_at"
          variant="outline"
          size="sm"
          :disabled="busy"
          @click="resendVerification"
        >
          <Icon name="hugeicons:mail-validation-01" class="size-4 shrink-0" />
          <span>Resend verification</span>
        </Button>
      </div>
    </div>

    <!-- Danger zone: suspend -->
    <div v-if="canSuspend && !isSelf" class="border-destructive/30 flex flex-col gap-y-3 rounded-lg border p-4">
      <div>
        <h2 class="text-destructive text-sm font-medium tracking-tight">Account access</h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          <template v-if="user.suspended_at">
            Suspended<span v-if="user.suspension_reason"> · {{ user.suspension_reason }}</span>.
          </template>
          <template v-else>Suspending signs the user out everywhere and blocks new logins.</template>
        </p>
      </div>

      <div>
        <Button
          v-if="user.suspended_at"
          variant="outline"
          size="sm"
          :disabled="busy"
          @click="unsuspend"
        >
          <Icon name="hugeicons:unlocked" class="size-4 shrink-0" />
          <span>Reactivate account</span>
        </Button>

        <DialogResponsive v-else v-model:open="suspendOpen">
          <template #trigger="{ open }">
            <Button variant="destructive" size="sm" @click="open()">
              <Icon name="hugeicons:user-block-01" class="size-4 shrink-0" />
              <span>Suspend user</span>
            </Button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Suspend {{ user.name }}?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                They will be signed out everywhere and blocked from signing in until reactivated.
              </p>
              <Textarea
                v-model="suspendReason"
                :rows="2"
                placeholder="Reason for suspension (required)"
                class="mt-3 resize-y"
              />
              <div class="mt-3 flex justify-end gap-2">
                <button class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98" :disabled="busy" @click="suspendOpen = false">Cancel</button>
                <button class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:opacity-50" :disabled="busy || !suspendReason.trim()" @click="suspend">
                  <Spinner v-if="busy" class="size-4 text-white" />
                  <span v-else>Suspend</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </div>
    </div>
  </div>
</template>

<script setup>
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const props = defineProps({
  user: { type: Object, required: true },
});

const emit = defineEmits(["refresh"]);

usePageMeta(null, {
  title: computed(() => `${props.user?.name || "User"} · Security`),
});

const { hasPermission, user: me } = usePermission();
const client = useSanctumClient();

const canReset2fa = computed(() => hasPermission("users.reset_2fa"));
const canSendEmails = computed(() => hasPermission("users.send_account_emails"));
const canSuspend = computed(() => hasPermission("users.suspend"));
const isSelf = computed(() => props.user.id === me.value?.id);

const busy = ref(false);
const reset2faOpen = ref(false);
const suspendOpen = ref(false);
const suspendReason = ref("");

async function run(promise, successMsg, fallbackMsg) {
  busy.value = true;
  try {
    await promise();
    toast.success(successMsg);
    return true;
  } catch (err) {
    toast.error(fallbackMsg, {
      description: err?.data?.message || err?.message || "An error occurred",
    });
    return false;
  } finally {
    busy.value = false;
  }
}

async function reset2fa() {
  const ok = await run(
    () => client(`/api/users/${props.user.username}/two-factor`, { method: "DELETE" }),
    "Two-factor reset",
    "Failed to reset 2FA"
  );
  if (ok) {
    reset2faOpen.value = false;
    emit("refresh");
  }
}

async function sendPasswordReset() {
  await run(
    () => client(`/api/users/${props.user.username}/send-password-reset`, { method: "POST" }),
    "Password reset link sent",
    "Failed to send reset link"
  );
}

async function resendVerification() {
  await run(
    () => client(`/api/users/${props.user.username}/resend-verification`, { method: "POST" }),
    "Verification email sent",
    "Failed to resend verification"
  );
}

async function suspend() {
  if (!suspendReason.value.trim()) return;
  const ok = await run(
    () => client(`/api/users/${props.user.username}/suspend`, { method: "POST", body: { reason: suspendReason.value.trim() } }),
    "User suspended",
    "Failed to suspend user"
  );
  if (ok) {
    suspendOpen.value = false;
    suspendReason.value = "";
    emit("refresh");
  }
}

async function unsuspend() {
  const ok = await run(
    () => client(`/api/users/${props.user.username}/unsuspend`, { method: "POST" }),
    "User reactivated",
    "Failed to reactivate user"
  );
  if (ok) emit("refresh");
}
</script>
