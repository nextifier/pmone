<template>
  <div class="mx-auto max-w-md space-y-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:edit-user-02" class="size-5 sm:size-6" />
      <h1 class="page-title">Edit Profile</h1>
    </div>

    <form @submit.prevent="handleSubmit" class="mt-8 grid gap-6">
      <div class="input-group">
        <label for="name"> Full Name </label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          :class="{ 'border-destructive': errors?.name }"
          required
        />
        <InputErrorMessage v-if="errors?.name" :errors="errors.name" />
      </div>

      <div class="input-group">
        <label for="username"> Username </label>
        <input
          id="username"
          v-model="form.username"
          type="text"
          :class="{ 'border-destructive': errors?.username }"
          required
        />
        <InputErrorMessage v-if="errors?.username" :errors="errors.username" />
        <p class="text-muted-foreground text-xs">
          Username can only contain letters, numbers, dots, and underscores.
        </p>
      </div>

      <div class="input-group">
        <label for="email"> Email Address </label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          :class="{ 'border-destructive': errors?.email }"
          required
        />
        <InputErrorMessage v-if="errors?.email" :errors="errors.email" />
      </div>

      <div class="input-group">
        <label for="phone"> Phone Number </label>
        <input
          id="phone"
          v-model="form.phone"
          type="tel"
          :class="{ 'border-destructive': errors?.phone }"
        />
        <InputErrorMessage v-if="errors?.phone" :errors="errors.phone" />
      </div>

      <div class="input-group">
        <label for="birth_date"> Birth Date </label>
        <input
          id="birth_date"
          v-model="form.birth_date"
          type="date"
          :class="{ 'border-destructive': errors?.birth_date }"
        />
        <InputErrorMessage v-if="errors?.birth_date" :errors="errors.birth_date" />
      </div>

      <div class="input-group">
        <label for="gender"> Gender </label>
        <select
          id="gender"
          v-model="form.gender"
          :class="[
            'border-input placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            { 'border-destructive': errors?.gender }
          ]"
        >
          <option value="">Select gender</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <!-- <option value="other">Other</option> -->
        </select>
        <InputErrorMessage v-if="errors?.gender" :errors="errors.gender" />
      </div>

      <!-- Bio -->
      <div class="input-group">
        <label for="bio"> Bio </label>
        <textarea
          id="bio"
          v-model="form.bio"
          rows="4"
          :class="[
            'border-input placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[60px] w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            { 'border-destructive': errors?.bio }
          ]"
          placeholder="Tell us about yourself..."
        ></textarea>
        <InputErrorMessage v-if="errors?.bio" :errors="errors.bio" />
        <p class="text-muted-foreground text-xs">Maximum 1000 characters</p>
      </div>

      <!-- Visibility -->
      <div class="input-group">
        <label for="visibility"> Profile Visibility </label>
        <select
          id="visibility"
          v-model="form.visibility"
          :class="[
            'border-input placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            { 'border-destructive': errors?.visibility }
          ]"
        >
          <option value="public">Public</option>
          <option value="private">Private</option>
        </select>
        <InputErrorMessage v-if="errors?.visibility" :errors="errors.visibility" />
        <p class="text-muted-foreground text-xs">
          Public profiles can be viewed by anyone. Private profiles are only visible to you.
        </p>
      </div>

      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="isSubmitting"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-3 text-sm font-semibold tracking-tight transition active:scale-98"
        >
          <span>{{ isSubmitting ? 'Updating..' : 'Update Profile' }}</span>

          <LoadingSpinner v-if="isSubmitting" class="border-primary-foreground size-4" />
        </button>
      </div>

      <div
        v-if="message"
        class="flex items-center gap-x-1.5 text-sm tracking-tight text-green-700 dark:text-green-500"
      >
        <Icon name="lucide:check" class="size-4" />
        <span>{{ message }}</span>
      </div>
    </form>
  </div>
</template>

<script setup>
import { toast } from 'vue-sonner'

definePageMeta({
  middleware: ['sanctum:auth', 'sanctum-verified'],
  layout: 'app'
})

usePageMeta('settingsProfile')

const sanctumFetch = useSanctumClient()
const { user } = useSanctumAuth()

// Form state
const form = reactive({
  name: '',
  username: '',
  email: '',
  phone: '',
  birth_date: '',
  gender: '',
  bio: '',
  visibility: 'public'
})

const message = ref()
const errors = ref()
const isSubmitting = ref(false)

// Load current user data into form
const loadUserData = () => {
  if (user.value) {
    form.name = user.value.name || ''
    form.username = user.value.username || ''
    form.email = user.value.email || ''
    form.phone = user.value.phone || ''
    form.birth_date = user.value.birth_date || ''
    form.gender = user.value.gender || ''
    form.bio = user.value.bio || ''
    form.visibility = user.value.visibility || 'public'
  }
}

// Load user data when component mounts
onMounted(() => {
  loadUserData()
})

// Watch for user changes and reload form data
watch(
  user,
  () => {
    loadUserData()
  },
  { deep: true }
)

// Submit handler
const handleSubmit = async () => {
  try {
    errors.value = null
    isSubmitting.value = true

    const response = await sanctumFetch('/api/user/profile/update', {
      method: 'PUT',
      body: form
    })

    // Show success message
    toast.success(response?.message)
    message.value = response?.message

    // Update local user data with response
    if (response.data && user.value) {
      Object.assign(user.value, response.data)
    }
  } catch (error) {
    if (error.response?.status === 422) {
      // For validation errors, show the first validation error message
      const validationErrors = error.response?._data.errors
      if (validationErrors) {
        // Get the first error message from the first field that has an error
        const firstErrorField = Object.keys(validationErrors)[0]
        const firstErrorMessage = validationErrors[firstErrorField][0]
        toast.error(firstErrorMessage)
      } else {
        toast.error(error.response?._data.message)
      }
      errors.value = validationErrors
    } else {
      toast.error('Failed to update profile. Please try again.')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>
