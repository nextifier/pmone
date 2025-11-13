<template>
  <div class="mx-auto flex h-full max-w-md items-center justify-center pt-4 pb-16">
    <div class="border-border grid gap-6 rounded-xl border p-6">
      <div class="flex flex-col items-center gap-y-4 text-center">
        <Icon name="hugeicons:checkmark-badge-02" class="text-primary size-9" />

        <div class="flex flex-col items-center gap-y-2 text-center">
          <h1 class="text-primary text-xl font-semibold tracking-tighter sm:text-2xl">
            Verify Email
          </h1>

          <p class="text-muted-foreground tracking-tight">
            Please verify your email by clicking the link we sent to your email.
          </p>
        </div>

        <span class="bg-border h-px w-full"></span>

        <div class="flex flex-col items-center gap-y-4 text-center">
          <p class="text-muted-foreground text-sm tracking-tight">
            Didn't receive it? We can send another.
          </p>

          <button
            type="button"
            @click="sendEmailNotification"
            :disabled="loading || verificationEmailSent"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-6 py-2 text-sm font-semibold tracking-tight ring-2 ring-offset-2 transition active:scale-95"
          >
            <span>Resend verification email</span>
            <LoadingSpinner v-if="loading" class="border-primary-foreground h-4" />
          </button>

          <p
            v-if="successMessage"
            class="text-center text-sm tracking-tight text-green-600 dark:text-green-500"
          >
            {{ successMessage }}
          </p>

          <p
            v-if="errorMessage"
            class="text-center text-sm tracking-tight text-red-600 dark:text-red-500"
          >
            {{ errorMessage }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("verifyEmail");

const verificationEmailSent = ref(false);
const successMessage = ref("");
const errorMessage = ref("");
const loading = ref(false);

const sanctumFetch = useSanctumClient();

// const errors = ref();

const sendEmailNotification = async () => {
  try {
    loading.value = true;
    successMessage.value = "";
    errorMessage.value = "";

    const response = await sanctumFetch.raw("/email/verification-notification", { method: "POST" });

    if (response.type === "opaqueredirect") {
      await navigateTo(response.headers.get("Location"));
    }

    successMessage.value = "Done! Check your inbox for the verification email.";
    toast.success(successMessage.value);
    verificationEmailSent.value = true;
  } catch {
    errorMessage.value = "Failed to send the verification email. Please try again.";
    toast.error(errorMessage.value);
  } finally {
    loading.value = false;
  }
};

//   const submit = async () => {
//     try {
//       loading.value = true;
//       errors.value = null;

//       const response = await sanctumFetch("/forgot-password", {
//         method: "POST",
//         body: form,
//       });

//       status.value = response?.status;
//       toast(response?.status);

//       resetEmailSent.value = true;
//     } catch (error) {
//       if (error.response?.status === 422) {
//         toast.error(error.response?._data.message);
//         errors.value = error.response?._data.errors;
//       }
//     } finally {
//       loading.value = false;
//     }
//   };
</script>
