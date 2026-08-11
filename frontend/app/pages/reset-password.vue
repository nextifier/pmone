<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-foreground mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="page-title">Reset Password</h1>

            <p class="page-description mt-1.5">Enter your new password.</p>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <Field :data-invalid="!!errors?.email">
              <FieldLabel for="email">Email</FieldLabel>
              <Input v-model="form.email" type="email" name="email" id="email" required disabled :aria-invalid="!!errors?.email" />
              <FieldError v-if="errors?.email" :errors="errors.email" />
            </Field>

            <Field :data-invalid="!!errors?.password">
              <FieldLabel for="password">New Password</FieldLabel>

              <InputPassword
                :aria-invalid="!!errors?.password"
                v-model="form.password"
                name="password"
                id="password"
                required
              />
              <FieldError v-if="errors?.password" :errors="errors.password" />
            </Field>

            <Field>
              <FieldLabel for="password_confirmation">Confirm New Password</FieldLabel>

              <InputPassword
                v-model="form.password_confirmation"
                name="password_confirmation"
                id="password_confirmation"
                required
              />
            </Field>

            <button
              type="submit"
              :disabled="loading"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-8 py-2 text-sm font-semibold tracking-tight ring-2 ring-offset-2 transition active:scale-95"
            >
              <span>Reset password</span>
              <Spinner v-if="loading" class="size-4 text-primary-foreground" />
            </button>
          </form>
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

usePageMeta(null, { title: "Reset Password" });

const loading = ref(false);
const route = useRoute();
const form = reactive({
  email: route.query.email,
  password: "",
  password_confirmation: "",
  token: route.query.token,
});

const sanctumFetch = useSanctumClient();

const errors = ref();

const submit = async () => {
  try {
    loading.value = true;
    errors.value = null;

    const response = await sanctumFetch("/reset-password", {
      method: "POST",
      body: form,
    });

    await navigateTo({ path: "/login", query: { reset: "true" } });
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
