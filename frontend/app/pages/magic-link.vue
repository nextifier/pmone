<template>
  <div class="min-h-screen-offset flex flex-col items-center justify-center py-6">
    <div class="container">
      <div class="mx-auto grid w-full max-w-sm gap-6">
        <!-- <Logo class="text-primary mx-auto h-7" /> -->

        <div class="grid gap-6">
          <div class="grid text-center">
            <h1 class="text-primary text-xl font-semibold tracking-tighter">
              Continue without password
            </h1>

            <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
              No need to create or remember your password. Enter your email and we'll send you a
              link to log in.
            </p>
          </div>

          <form @submit.prevent="submit" class="grid gap-6">
            <div class="input-group">
              <label for="email">Email</label>
              <input
                v-model="form.email"
                type="email"
                name="email"
                id="email"
                required
                :autofocus="true"
                :disabled="emailSent"
              />
              <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
            </div>

            <button
              type="submit"
              :disabled="loading || emailSent"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex h-10 items-center justify-center gap-x-2 rounded-lg px-8 py-2 text-sm font-semibold tracking-tight transition active:scale-95"
            >
              <span>Send magic link</span>
              <LoadingSpinner v-if="loading" class="border-primary-foreground h-4" />
            </button>

            <p
              v-if="status"
              class="text-center text-sm tracking-tight text-green-600 dark:text-green-500"
            >
              {{ status }}
            </p>
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

usePageMeta('magicLink')

const loading = ref(false)

const form = reactive({
  email: ''
})

const emailSent = ref(false)
const status = ref()

const sanctumFetch = useSanctumClient()

const errors = ref()

const submit = async () => {
  try {
    loading.value = true
    errors.value = null

    const response = await sanctumFetch('/auth/magic-link', {
      method: 'POST',
      body: form
    })

    toast(response?.message)

    emailSent.value = true
  } catch (error) {
    if (error.response?.status) {
      toast.error(error.response?._data.message)
      errors.value = error.response?._data.errors
    }
  } finally {
    loading.value = false
  }
}
</script>
