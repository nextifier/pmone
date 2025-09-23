<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-primary mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="page-title">Welcome back</h1>

            <p class="page-description mt-1.5">Log in with your Google account</p>

            <AuthLoginAlternatives class="mt-4" />
          </div>

          <div class="flex items-center text-center">
            <span class="bg-border h-px flex-grow"></span>
            <span class="page-description">or enter your email and password below</span>
            <span class="bg-border h-px flex-grow"></span>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <div class="input-group">
              <label for="email">Email</label>
              <input v-model="form.email" type="email" name="email" id="email" required autofocus />
              <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
            </div>
            <div class="input-group">
              <div class="flex items-center justify-between gap-3">
                <label for="password">Password</label>

                <NuxtLink
                  to="/forgot-password"
                  class="text-sm tracking-tight hover:underline"
                  tabindex="-1"
                  >Forgot your password?</NuxtLink
                >
              </div>

              <div class="relative">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  name="password"
                  class="!pr-12"
                  ref="password"
                  id="password"
                  required
                />
                <div class="absolute top-1/2 right-2 -translate-y-1/2">
                  <button
                    @click="toggleShowPassword"
                    type="button"
                    tabindex="-1"
                    class="hover:bg-muted flex size-7 items-center justify-center rounded-full transition active:scale-95"
                  >
                    <Icon
                      v-if="!showPassword"
                      name="lucide:eye"
                      class="size-4 shrink-0"
                      v-tippy="'Show password'"
                    />
                    <Icon
                      v-else
                      name="lucide:eye-off"
                      class="size-4 shrink-0"
                      v-tippy="'Hide password'"
                    />
                  </button>
                </div>
              </div>
              <InputErrorMessage v-if="errors?.password" :errors="errors.password" />
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-8 py-2 text-sm font-semibold tracking-tight transition active:scale-95"
            >
              <span>Log in</span>
              <LoadingSpinner v-if="loading" class="border-primary-foreground h-4" />
            </button>
          </form>

          <div class="grid text-center">
            <p class="text-sm tracking-tight">
              Don't have an account?
              <nuxt-link to="/signup" class="underline underline-offset-4">Sign up</nuxt-link>
            </p>
          </div>
        </div>

        <p class="page-description text-center text-xs">
          By continuing, you agree to our
          <NuxtLink to="/terms" class="underline">Terms</NuxtLink>
          and
          <NuxtLink to="/privacy" class="underline">Privacy Policy</NuxtLink>.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from 'vue-sonner'

definePageMeta({
  middleware: ['sanctum:guest']
})

usePageMeta('login')

const loading = ref(false)
const showPassword = ref(false)
const form = reactive({
  email: '',
  password: '',
  remember: true
})

const { login } = useSanctumAuth()
const errors = ref()

const submit = async () => {
  try {
    loading.value = true
    errors.value = null
    await login(form)
  } catch (error) {
    if (error.response?.status === 422) {
      toast.error(error.response?._data.message)
      errors.value = error.response?._data.errors
    }
  } finally {
    loading.value = false
  }
}

const toggleShowPassword = () => {
  showPassword.value = !showPassword.value

  let el = document.querySelector('#password')
  el.focus()

  // Move cursor to the end of input after focusing
  setTimeout(() => {
    if (typeof el.selectionStart == 'number') {
      el.selectionStart = el.selectionEnd = el.value.length
    } else if (typeof el.createTextRange != 'undefined') {
      el.focus()
      var range = el.createTextRange()
      range.collapse(false)
      range.select()
    }
  }, 0)
}
</script>
