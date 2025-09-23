<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:user-add-01" class="size-5 sm:size-6" />
      <h1 class="page-title">Create New User</h1>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="border-destructive bg-destructive/10 text-destructive rounded-lg border p-4"
    >
      {{ error }}
    </div>

    <!-- Success message -->
    <div
      v-if="success"
      class="rounded-lg border border-green-500 bg-green-100 p-4 text-green-800 dark:bg-green-900 dark:text-green-300"
    >
      {{ success }}
    </div>

    <!-- Form -->
    <div class="bg-card rounded-lg border p-6">
      <form @submit.prevent="createUser" class="space-y-6">
        <!-- Basic Information -->
        <div class="space-y-4">
          <h3 class="text-lg font-medium">Basic Information</h3>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Name -->
            <div>
              <label for="name" class="text-foreground mb-2 block text-sm font-medium">
                Full Name *
              </label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                required
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
                placeholder="Enter full name"
              />
              <p v-if="errors.name" class="text-destructive mt-1 text-xs">{{ errors.name[0] }}</p>
            </div>

            <!-- Username -->
            <div>
              <label for="username" class="text-foreground mb-2 block text-sm font-medium">
                Username
              </label>
              <input
                id="username"
                v-model="form.username"
                type="text"
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
                placeholder="Will be auto-generated if left empty"
              />
              <p v-if="errors.username" class="text-destructive mt-1 text-xs">
                {{ errors.username[0] }}
              </p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Email -->
            <div>
              <label for="email" class="text-foreground mb-2 block text-sm font-medium">
                Email Address *
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
                placeholder="Enter email address"
              />
              <p v-if="errors.email" class="text-destructive mt-1 text-xs">{{ errors.email[0] }}</p>
            </div>

            <!-- Phone -->
            <div>
              <label for="phone" class="text-foreground mb-2 block text-sm font-medium">
                Phone Number
              </label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
                placeholder="Enter phone number"
              />
              <p v-if="errors.phone" class="text-destructive mt-1 text-xs">{{ errors.phone[0] }}</p>
            </div>
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="text-foreground mb-2 block text-sm font-medium">
              Password *
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
              placeholder="Enter password (minimum 8 characters)"
              minlength="8"
            />
            <p v-if="errors.password" class="text-destructive mt-1 text-xs">
              {{ errors.password[0] }}
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Birth Date -->
            <div>
              <label for="birth_date" class="text-foreground mb-2 block text-sm font-medium">
                Birth Date
              </label>
              <input
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                :max="maxBirthDate"
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
              />
              <p v-if="errors.birth_date" class="text-destructive mt-1 text-xs">
                {{ errors.birth_date[0] }}
              </p>
            </div>

            <!-- Gender -->
            <div>
              <label for="gender" class="text-foreground mb-2 block text-sm font-medium">
                Gender
              </label>
              <select
                id="gender"
                v-model="form.gender"
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
              >
                <option value="">Select gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
              <p v-if="errors.gender" class="text-destructive mt-1 text-xs">
                {{ errors.gender[0] }}
              </p>
            </div>
          </div>

          <!-- Bio -->
          <div>
            <label for="bio" class="text-foreground mb-2 block text-sm font-medium"> Bio </label>
            <textarea
              id="bio"
              v-model="form.bio"
              rows="3"
              class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
              placeholder="Enter user bio (optional)"
              maxlength="1000"
            ></textarea>
            <p v-if="errors.bio" class="text-destructive mt-1 text-xs">{{ errors.bio[0] }}</p>
          </div>
        </div>

        <!-- Account Settings -->
        <div class="space-y-4 border-t pt-6">
          <h3 class="text-lg font-medium">Account Settings</h3>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Status -->
            <div>
              <label for="status" class="text-foreground mb-2 block text-sm font-medium">
                Status
              </label>
              <select
                id="status"
                v-model="form.status"
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
              </select>
              <p v-if="errors.status" class="text-destructive mt-1 text-xs">
                {{ errors.status[0] }}
              </p>
            </div>

            <!-- Visibility -->
            <div>
              <label for="visibility" class="text-foreground mb-2 block text-sm font-medium">
                Profile Visibility
              </label>
              <select
                id="visibility"
                v-model="form.visibility"
                class="bg-background border-border focus:border-primary focus:ring-primary w-full rounded-md border px-3 py-2 text-sm transition"
              >
                <option value="public">Public</option>
                <option value="private">Private</option>
              </select>
              <p v-if="errors.visibility" class="text-destructive mt-1 text-xs">
                {{ errors.visibility[0] }}
              </p>
            </div>
          </div>

          <!-- Roles -->
          <div>
            <label class="text-foreground mb-2 block text-sm font-medium"> Roles </label>
            <div class="space-y-2">
              <div v-for="role in roles" :key="role.id" class="flex items-center">
                <input
                  :id="`role-${role.id}`"
                  v-model="form.roles"
                  :value="role.name"
                  type="checkbox"
                  class="border-border text-primary focus:ring-primary h-4 w-4 rounded transition"
                />
                <label
                  :for="`role-${role.id}`"
                  class="text-foreground ml-2 cursor-pointer text-sm font-medium capitalize"
                >
                  {{ role.name }}
                </label>
              </div>
            </div>
            <p v-if="errors.roles" class="text-destructive mt-1 text-xs">{{ errors.roles[0] }}</p>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-between border-t pt-6">
          <NuxtLink
            to="/users"
            class="border-border hover:bg-muted inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm transition"
          >
            <Icon name="hugeicons:arrow-left-02" class="size-4" />
            Back to Users
          </NuxtLink>

          <div class="flex gap-3">
            <button
              type="button"
              @click="resetForm"
              class="border-border hover:bg-muted inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm transition"
            >
              <Icon name="hugeicons:refresh" class="size-4" />
              Reset
            </button>

            <button
              type="submit"
              :disabled="loading"
              class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm transition disabled:opacity-50"
            >
              <Icon name="hugeicons:loading-01" v-if="loading" class="size-4 animate-spin" />
              <Icon name="hugeicons:user-add-01" v-else class="size-4" />
              {{ loading ? 'Creating...' : 'Create User' }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ['sanctum:auth', 'admin-master'],
  layout: 'app'
})

const title = 'Create New User'
const description = ''
const route = useRoute()

useSeoMeta({
  titleTemplate: '%s · %siteName',
  title: title,
  ogTitle: title,
  description: description,
  ogDescription: description,
  ogUrl: useAppConfig().app.url + route.fullPath,
  twitterCard: 'summary_large_image'
})

const sanctumFetch = useSanctumClient()
const { $dayjs } = useNuxtApp()

// State
const loading = ref(false)
const error = ref(null)
const success = ref(null)
const errors = ref({})
const roles = ref([])

// Form data
const form = reactive({
  name: '',
  username: '',
  email: '',
  password: '',
  phone: '',
  birth_date: '',
  gender: '',
  bio: '',
  status: 'active',
  visibility: 'public',
  roles: ['user'] // Default to user role
})

// Computed
const maxBirthDate = computed(() => {
  return $dayjs().subtract(1, 'day').format('YYYY-MM-DD')
})

// Load roles
async function loadRoles() {
  try {
    const response = await sanctumFetch('/api/users/roles')
    roles.value = response.data
  } catch (err) {
    console.error('Error loading roles:', err)
  }
}

// Create user
async function createUser() {
  loading.value = true
  error.value = null
  success.value = null
  errors.value = {}

  try {
    // Prepare form data
    const userData = { ...form }

    // Remove empty values
    Object.keys(userData).forEach((key) => {
      if (userData[key] === '' || userData[key] === null) {
        delete userData[key]
      }
    })

    const response = await sanctumFetch('/api/users', {
      method: 'POST',
      body: userData
    })

    if (response.data) {
      success.value = `User "${response.data.name}" created successfully!`

      // Reset form after successful creation
      setTimeout(() => {
        navigateTo('/users')
      }, 2000)
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors
      error.value = 'Please fix the validation errors below.'
    } else {
      error.value = err.message || 'Failed to create user'
    }
    console.error('Error creating user:', err)
  } finally {
    loading.value = false
  }
}

// Reset form
function resetForm() {
  Object.assign(form, {
    name: '',
    username: '',
    email: '',
    password: '',
    phone: '',
    birth_date: '',
    gender: '',
    bio: '',
    status: 'active',
    visibility: 'public',
    roles: ['user']
  })
  errors.value = {}
  error.value = null
  success.value = null
}

// Load data on mount
onMounted(async () => {
  await loadRoles()
})
</script>
