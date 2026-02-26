<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-primary mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="page-title">{{ $t('auth.forgetYourPassword') }}</h1>

            <p class="page-description mt-1.5">
              {{ $t('auth.forgotPasswordDescription') }}
            </p>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <div class="input-group">
              <Label for="email">{{ $t('auth.email') }}</Label>
              <Input
                v-model="form.email"
                type="email"
                name="email"
                id="email"
                required
                :autofocus="true"
                :disabled="resetEmailSent"
              />
              <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
            </div>

            <button
              type="submit"
              :disabled="loading || resetEmailSent"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-8 py-2 text-sm font-semibold tracking-tight ring-2 ring-offset-2 transition active:scale-95"
            >
              <span>{{ $t('auth.sendResetLink') }}</span>
              <LoadingSpinner v-if="loading" class="border-primary-foreground h-4" />
            </button>

            <p
              v-if="status"
              class="text-center text-sm tracking-tight text-green-600 dark:text-green-500"
            >
              {{ status }}
            </p>
          </form>

          <div class="grid text-center">
            <p class="text-sm tracking-tight">
              {{ $t('auth.rememberedPassword') }}
              <nuxt-link to="/login" class="underline underline-offset-4">{{ $t('auth.login') }}</nuxt-link>
            </p>
          </div>

          <div class="flex items-center text-center">
            <span class="bg-border h-px flex-grow"></span>
            <span class="page-description">{{ $t('auth.or') }}</span>
            <span class="bg-border h-px flex-grow"></span>
          </div>

          <NuxtLink
            :to="`/magic-link`"
            class="border-border bg-muted hover:bg-muted/80 text-primary flex items-center justify-center gap-3 rounded-lg border px-6 py-3 text-sm font-semibold tracking-tight transition active:scale-98"
          >
            <Icon name="hugeicons:mail-lock-02" class="size-5 shrink-0" />
            <span>{{ $t('auth.continueWithoutPassword') }}</span>
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:guest"],
});

usePageMeta(null, { title: "Forgot Password" });

const loading = ref(false);

const form = reactive({
  email: "",
});

const resetEmailSent = ref(false);
const status = ref();

const sanctumFetch = useSanctumClient();

const errors = ref();

const submit = async () => {
  try {
    loading.value = true;
    errors.value = null;

    const response = await sanctumFetch("/forgot-password", {
      method: "POST",
      body: form,
    });

    toast(response?.message);

    resetEmailSent.value = true;
  } catch (error) {
    if (error.response?.status === 422) {
      toast.error(error.response?._data.message);
      errors.value = error.response?._data.errors;
    }
  } finally {
    loading.value = false;
  }
};
</script>
