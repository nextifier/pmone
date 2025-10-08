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
            <div class="space-y-2">
              <Label for="name">Full Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                required
                placeholder="Enter full name"
              />
              <p v-if="errors.name" class="text-destructive text-sm">{{ errors.name[0] }}</p>
            </div>

            <!-- Username -->
            <div class="space-y-2">
              <Label for="username">Username</Label>
              <Input
                id="username"
                v-model="form.username"
                type="text"
                placeholder="Will be auto-generated if left empty"
              />
              <p v-if="errors.username" class="text-destructive text-sm">
                {{ errors.username[0] }}
              </p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Email -->
            <div class="space-y-2">
              <Label for="email">Email Address *</Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                required
                placeholder="Enter email address"
              />
              <p v-if="errors.email" class="text-destructive text-sm">{{ errors.email[0] }}</p>
            </div>

            <!-- Phone -->
            <div class="space-y-2">
              <Label for="phone">Phone Number</Label>
              <Input id="phone" v-model="form.phone" type="tel" placeholder="Enter phone number" />
              <p v-if="errors.phone" class="text-destructive text-sm">{{ errors.phone[0] }}</p>
            </div>
          </div>

          <!-- Password -->
          <div class="space-y-2">
            <Label for="password">Password *</Label>
            <Input
              id="password"
              v-model="form.password"
              type="password"
              required
              placeholder="Enter password (minimum 8 characters)"
              minlength="8"
            />
            <p v-if="errors.password" class="text-destructive text-sm">{{ errors.password[0] }}</p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Birth Date -->
            <div class="space-y-2">
              <Label for="birth_date">Birth Date</Label>
              <Popover>
                <PopoverTrigger as-child>
                  <Button
                    variant="outline"
                    :class="
                      cn(
                        'w-[280px] justify-start text-left font-normal',
                        !form.birth_date && 'text-muted-foreground'
                      )
                    "
                  >
                    <CalendarIcon class="mr-2 h-4 w-4" />
                    {{
                      form.birth_date
                        ? df.format(form.birth_date.toDate(getLocalTimeZone()))
                        : "Pick a date"
                    }}
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-auto p-0">
                  <Calendar v-model="form.birth_date" initial-focus />
                </PopoverContent>
              </Popover>

              <p v-if="errors.birth_date" class="text-destructive text-sm">
                {{ errors.birth_date[0] }}
              </p>
            </div>

            <!-- Gender -->
            <div class="space-y-2">
              <Label for="gender">Gender</Label>
              <Select v-model="form.gender">
                <SelectTrigger>
                  <SelectValue placeholder="Select gender" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="male">Male</SelectItem>
                  <SelectItem value="female">Female</SelectItem>
                  <SelectItem value="other">Other</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="errors.gender" class="text-destructive text-sm">{{ errors.gender[0] }}</p>
            </div>
          </div>

          <!-- Bio -->
          <div class="space-y-2">
            <Label for="bio">Bio</Label>
            <Textarea
              id="bio"
              v-model="form.bio"
              rows="3"
              placeholder="Enter user bio (optional)"
              maxlength="1000"
            />
            <p v-if="errors.bio" class="text-destructive text-sm">{{ errors.bio[0] }}</p>
          </div>
        </div>

        <!-- Account Settings -->
        <div class="space-y-4 border-t pt-6">
          <h3 class="text-lg font-medium">Account Settings</h3>

          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Status -->
            <div class="space-y-2">
              <Label for="status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger>
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                  <SelectItem value="suspended">Suspended</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="errors.status" class="text-destructive text-sm">{{ errors.status[0] }}</p>
            </div>

            <!-- Visibility -->
            <div class="space-y-2">
              <Label for="visibility">Profile Visibility</Label>
              <Select v-model="form.visibility">
                <SelectTrigger>
                  <SelectValue placeholder="Select visibility" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="public">Public</SelectItem>
                  <SelectItem value="private">Private</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="errors.visibility" class="text-destructive text-sm">
                {{ errors.visibility[0] }}
              </p>
            </div>
          </div>

          <!-- Roles -->
          <div class="space-y-2">
            <Label>Roles</Label>
            <div class="space-y-2">
              <div v-for="role in roles" :key="role.id" class="flex items-center gap-2">
                <Checkbox
                  :id="`role-${role.id}`"
                  :checked="form.roles.includes(role.name)"
                  @update:checked="toggleRole(role.name)"
                />
                <Label :for="`role-${role.id}`" class="cursor-pointer font-normal capitalize">
                  {{ role.name }}
                </Label>
              </div>
            </div>
            <p v-if="errors.roles" class="text-destructive text-sm">{{ errors.roles[0] }}</p>
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
              {{ loading ? "Creating..." : "Create User" }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

import { DateFormatter, getLocalTimeZone } from "@internationalized/date";
import { CalendarIcon } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { ref } from "vue";

const df = new DateFormatter("en-US", {
  dateStyle: "long",
});

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const title = "Create New User";
const description = "";
const route = useRoute();

useSeoMeta({
  titleTemplate: "%s · %siteName",
  title: title,
  ogTitle: title,
  description: description,
  ogDescription: description,
  ogUrl: useAppConfig().app.url + route.fullPath,
  twitterCard: "summary_large_image",
});

const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

// State
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const errors = ref({});
const roles = ref([]);

// Form data
const form = reactive({
  name: "",
  username: "",
  email: "",
  password: "",
  phone: "",
  birth_date: "",
  gender: "",
  bio: "",
  status: "active",
  visibility: "public",
  roles: ["user"], // Default to user role
});

// Computed
const maxBirthDate = computed(() => {
  return $dayjs().subtract(1, "day").format("YYYY-MM-DD");
});

// Toggle role selection
function toggleRole(roleName) {
  const index = form.roles.indexOf(roleName);
  if (index > -1) {
    form.roles.splice(index, 1);
  } else {
    form.roles.push(roleName);
  }
}

// Load roles
async function loadRoles() {
  try {
    const response = await sanctumFetch("/api/users/roles");
    roles.value = response.data;
  } catch (err) {
    console.error("Error loading roles:", err);
  }
}

// Create user
async function createUser() {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
    // Prepare form data
    const userData = { ...form };

    // Remove empty values
    Object.keys(userData).forEach((key) => {
      if (userData[key] === "" || userData[key] === null) {
        delete userData[key];
      }
    });

    const response = await sanctumFetch("/api/users", {
      method: "POST",
      body: userData,
    });

    if (response.data) {
      success.value = `User "${response.data.name}" created successfully!`;

      // Reset form after successful creation
      setTimeout(() => {
        navigateTo("/users");
      }, 2000);
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      error.value = "Please fix the validation errors below.";
    } else {
      error.value = err.message || "Failed to create user";
    }
    console.error("Error creating user:", err);
  } finally {
    loading.value = false;
  }
}

// Reset form
function resetForm() {
  Object.assign(form, {
    name: "",
    username: "",
    email: "",
    password: "",
    phone: "",
    birth_date: "",
    gender: "",
    bio: "",
    status: "active",
    visibility: "public",
    roles: ["user"],
  });
  errors.value = {};
  error.value = null;
  success.value = null;
}

// Load data on mount
onMounted(async () => {
  await loadRoles();
});
</script>
