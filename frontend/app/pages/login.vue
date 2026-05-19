<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-primary mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="page-title">{{ $t('auth.welcomeBack') }}</h1>

            <p class="page-description mt-1.5">{{ $t('auth.loginWithGoogle') }}</p>

            <AuthLoginAlternatives class="mt-4" />
          </div>

          <div class="flex items-center text-center">
            <span class="bg-border h-px flex-grow"></span>
            <span class="page-description">{{ $t('auth.orEnterCredentials') }}</span>
            <span class="bg-border h-px flex-grow"></span>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <div class="input-group">
              <Label for="email">{{ $t('auth.email') }}</Label>
              <Input v-model="form.email" type="email" name="email" id="email" required autofocus />
              <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
            </div>
            <div class="input-group">
              <div class="flex items-center justify-between gap-3">
                <Label for="password">{{ $t('auth.password') }}</Label>

                <NuxtLink
                  to="/forgot-password"
                  class="text-sm tracking-tight hover:underline"
                  tabindex="-1"
                  >{{ $t('auth.forgotPassword') }}</NuxtLink
                >
              </div>

              <InputPassword
                v-model="form.password"
                name="password"
                id="password"
                required
                :show-label="$t('auth.showPassword')"
                :hide-label="$t('auth.hidePassword')"
              />
              <InputErrorMessage v-if="errors?.password" :errors="errors.password" />
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-8 py-2 text-sm font-semibold tracking-tight ring-2 ring-offset-2 transition active:scale-95"
            >
              <span>{{ $t('auth.login') }}</span>
              <Spinner v-if="loading" class="size-4 text-primary-foreground" />
            </button>
          </form>

          <div class="grid text-center">
            <p class="text-sm tracking-tight">
              {{ $t('auth.dontHaveAccount') }}
              <nuxt-link to="/signup" class="underline underline-offset-4">{{ $t('auth.signUp') }}</nuxt-link>
            </p>
          </div>
        </div>

        <p class="page-description text-center text-xs">
          {{ $t('auth.byContinuing') }}
          <NuxtLink to="/terms" class="underline">{{ $t('auth.terms') }}</NuxtLink>
          {{ $t('auth.and') }}
          <NuxtLink to="/privacy" class="underline">{{ $t('auth.privacyPolicy') }}</NuxtLink>.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:guest"],
});

usePageMeta(null, { title: "Log in" });

const loading = ref(false);
const form = reactive({
  email: "",
  password: "",
  remember: true,
});

const { login } = useSanctumAuth();
const errors = ref();

const submit = async () => {
  try {
    loading.value = true;
    errors.value = null;
    await login(form);
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
