<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-primary mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="page-title">Reset Password</h1>

            <p class="page-description mt-1.5">Enter your new password.</p>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <div class="input-group">
              <label for="email">Email</label>
              <input v-model="form.email" type="email" name="email" id="email" required disabled />
              <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
            </div>

            <div class="input-group">
              <label for="password">New Password</label>

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

            <div class="input-group">
              <label for="password_confirmation">Confirm New Password</label>

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
              <span>Reset password</span>
              <LoadingSpinner v-if="loading" class="border-primary-foreground h-4" />
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from 'vue-sonner'

definePageMeta({
  middleware: ['sanctum:guest']
})

usePageMeta('resetPassword')

const loading = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const route = useRoute()
const form = reactive({
  email: route.query.email,
  password: '',
  password_confirmation: '',
  token: route.query.token
})

const sanctumFetch = useSanctumClient()

const errors = ref()

const submit = async () => {
  try {
    loading.value = true
    errors.value = null

    const response = await sanctumFetch('/reset-password', {
      method: 'POST',
      body: form
    })

    await navigateTo({ path: '/login', query: { reset: 'true' } })
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
