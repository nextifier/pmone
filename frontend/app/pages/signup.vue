<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-primary mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="page-title">Create an account</h1>

            <p class="page-description mt-1.5">Sign up with your Google account</p>

            <AuthLoginAlternatives class="mt-4" />
          </div>

          <div class="flex items-center text-center">
            <span class="bg-border h-px flex-grow"></span>
            <span class="page-description">or enter your email and password below</span>
            <span class="bg-border h-px flex-grow"></span>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <div class="input-group">
              <label for="name">Name</label>
              <input v-model="form.name" type="text" name="name" id="name" required autofocus />
              <InputErrorMessage v-if="errors?.name" :errors="errors.name" />
            </div>

            <div class="input-group">
              <label for="email">Email</label>
              <input v-model="form.email" type="email" name="email" id="email" required />
              <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
            </div>

            <div class="input-group">
              <label for="password">Password</label>

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

            <div v-if="enablePasswordConfirmation" class="input-group">
              <label for="password_confirmation">Confirm Password</label>

              <div class="relative">
                <input
                  v-model="form.password_confirmation"
                  :type="showPasswordConfirmation ? 'text' : 'password'"
                  name="password_confirmation"
                  class="!pr-12"
                  ref="password_confirmation"
                  id="password_confirmation"
                  required
                />
                <div class="absolute top-1/2 right-2 -translate-y-1/2">
                  <button
                    @click="toggleShowPasswordConfirmation"
                    type="button"
                    tabindex="-1"
                    class="hover:bg-muted flex size-7 items-center justify-center rounded-full transition active:scale-95"
                  >
                    <Icon
                      v-if="!showPasswordConfirmation"
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
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-8 py-2 text-sm font-semibold tracking-tight transition active:scale-95"
            >
              <span>Sign up</span>
              <LoadingSpinner v-if="loading" class="border-primary-foreground h-4" />
            </button>
          </form>

          <div class="grid text-center">
            <p class="text-sm tracking-tight">
              Already have an account?
              <nuxt-link to="/login" class="underline underline-offset-4">Log in</nuxt-link>
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

usePageMeta('signup')

const loading = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const enablePasswordConfirmation = ref(false)
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const sanctumConfig = useSanctumConfig()
const sanctumFetch = useSanctumClient()
const { refreshIdentity } = useSanctumAuth()

const errors = ref()

const submit = async () => {
  try {
    loading.value = true
    errors.value = null

    await sanctumFetch('/register', {
      method: 'POST',
      body: {
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: enablePasswordConfirmation.value
          ? form.password_confirmation
          : form.password
      }
    })

    await refreshIdentity()

    navigateTo(sanctumConfig.redirect.onGuestOnly || '/')
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

const toggleShowPasswordConfirmation = () => {
  showPasswordConfirmation.value = !showPasswordConfirmation.value

  let el = document.querySelector('#password_confirmation')
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
