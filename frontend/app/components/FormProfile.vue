<template>
  <form @submit.prevent="handleSubmit" autocomplete="off" class="grid gap-y-6">
    <div class="space-y-5">
      <!-- Profile and Cover Images -->
      <div v-if="showImages" class="space-y-5">
        <div class="space-y-2">
          <Label>Profile Image</Label>
          <InputFile
            v-model="profileImageFiles"
            :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
            max-file-size="5MB"
            :allow-multiple="false"
            :max-files="1"
          />
          <p v-if="errors.tmp_profile_image" class="text-destructive text-sm">
            {{ errors.tmp_profile_image[0] }}
          </p>
        </div>

        <div class="space-y-2">
          <Label>Cover Image</Label>
          <InputFile
            v-model="coverImageFiles"
            :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
            max-file-size="5MB"
            :allow-multiple="false"
            :max-files="1"
          />
          <p v-if="errors.tmp_cover_image" class="text-destructive text-sm">
            {{ errors.tmp_cover_image[0] }}
          </p>
        </div>
      </div>

      <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Personal Information</h3>

      <div class="space-y-2">
        <Label for="name">Full Name *</Label>
        <Input id="name" v-model="form.name" type="text" required />
        <p v-if="errors.name" class="text-destructive text-sm">{{ errors.name[0] }}</p>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between gap-x-2">
          <Label for="username">Username</Label>
          <p class="text-muted-foreground line-clamp-1 text-xs tracking-tight">
            {{ isCreate ? "Will be auto-generated if left empty." : "" }}
          </p>
        </div>
        <Input id="username" v-model="form.username" type="text" :required="!isCreate" />

        <p v-if="errors.username" class="text-destructive text-sm">
          {{ errors.username[0] }}
        </p>
      </div>

      <div class="space-y-2">
        <Label for="email">Email Address *</Label>
        <Input id="email" v-model="form.email" type="email" required />
        <p v-if="errors.email" class="text-destructive text-sm">{{ errors.email[0] }}</p>
      </div>

      <div class="space-y-2">
        <Label for="phone">Phone Number</Label>
        <InputPhone v-model="form.phone" id="phone" />
        <p v-if="errors.phone" class="text-destructive text-sm">{{ errors.phone[0] }}</p>
      </div>

      <div v-if="showPassword" class="space-y-2">
        <div class="flex items-center justify-between gap-x-2">
          <Label for="password">Password {{ isCreate ? "*" : "" }}</Label>
          <p class="text-muted-foreground text-xs tracking-tight">
            {{ isCreate ? "Minimum 8 characters." : "Leave empty to keep current password." }}
          </p>
        </div>
        <Input
          id="password"
          v-model="form.password"
          type="password"
          :required="isCreate"
          minlength="8"
        />

        <p v-if="errors.password" class="text-destructive text-sm">{{ errors.password[0] }}</p>
      </div>

      <div class="space-y-2">
        <Label for="birth_date">Birth Date</Label>
        <Popover>
          <PopoverTrigger as-child>
            <Button
              variant="outline"
              :class="
                cn(
                  'w-full justify-start text-left font-normal',
                  !form.birth_date && 'text-muted-foreground'
                )
              "
            >
              <Icon name="hugeicons:calendar-04" class="size-4" />
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

      <div class="space-y-2">
        <Label for="gender">Gender</Label>
        <Select v-model="form.gender">
          <SelectTrigger class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="male">Male</SelectItem>
            <SelectItem value="female">Female</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.gender" class="text-destructive text-sm">{{ errors.gender[0] }}</p>
      </div>

      <div class="space-y-2">
        <Label for="bio">Bio</Label>
        <Textarea id="bio" v-model="form.bio" rows="5" maxlength="1000" />
        <p v-if="errors.bio" class="text-destructive text-sm">{{ errors.bio[0] }}</p>
      </div>
    </div>

    <div v-if="showAccountSettings" class="space-y-5">
      <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Account Settings</h3>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-2">
          <Label for="status">Status</Label>
          <Select v-model="form.status">
            <SelectTrigger class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
              <SelectItem value="suspended">Suspended</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.status" class="text-destructive text-sm">{{ errors.status[0] }}</p>
        </div>

        <div class="space-y-2">
          <Label for="visibility">Profile Visibility</Label>
          <Select v-model="form.visibility">
            <SelectTrigger class="w-full">
              <SelectValue />
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

      <div v-if="showRoles" class="space-y-2">
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

    <div v-else class="space-y-2">
      <Label for="visibility">Profile Visibility</Label>
      <Select v-model="form.visibility">
        <SelectTrigger class="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="public">Public</SelectItem>
          <SelectItem value="private">Private</SelectItem>
        </SelectContent>
      </Select>
      <p v-if="errors.visibility" class="text-destructive text-sm">
        {{ errors.visibility[0] }}
      </p>
      <p class="text-muted-foreground text-xs">
        Public profiles can be viewed by anyone. Private profiles are only visible to you.
      </p>
    </div>

    <div class="flex justify-end gap-x-3">
      <button
        v-if="showReset"
        type="button"
        @click="handleReset"
        class="border-border text-primary hover:bg-muted flex items-center gap-x-1.5 rounded-lg border px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:opacity-50"
      >
        <Icon name="hugeicons:refresh" class="size-4" />
        Reset
      </button>

      <button
        type="submit"
        :disabled="loading"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:opacity-50"
      >
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { DateFormatter, getLocalTimeZone } from "@internationalized/date";

const df = new DateFormatter("en-US", {
  dateStyle: "long",
});

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
  roles: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  isCreate: {
    type: Boolean,
    default: false,
  },
  showPassword: {
    type: Boolean,
    default: true,
  },
  showAccountSettings: {
    type: Boolean,
    default: false,
  },
  showRoles: {
    type: Boolean,
    default: false,
  },
  showImages: {
    type: Boolean,
    default: true,
  },
  showReset: {
    type: Boolean,
    default: true,
  },
  submitText: {
    type: String,
    default: "Submit",
  },
  submitLoadingText: {
    type: String,
    default: "Submitting..",
  },
});

const emit = defineEmits(["submit", "reset"]);

// Uploaded files
const profileImageFiles = ref([]);
const coverImageFiles = ref([]);

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
  roles: [],
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

// Populate form with initial data
async function populateForm(data) {
  if (!data || Object.keys(data).length === 0) return;

  form.name = data.name || "";
  form.username = data.username || "";
  form.email = data.email || "";
  form.phone = data.phone || "";
  form.gender = data.gender || "";
  form.bio = data.bio || "";
  form.status = data.status || "active";
  form.visibility = data.visibility || "public";
  form.roles = data.roles || [];

  // Handle birth_date
  if (data.birth_date) {
    const { parseDate } = await import("@internationalized/date");
    try {
      form.birth_date = parseDate(data.birth_date);
    } catch {
      form.birth_date = "";
    }
  } else {
    form.birth_date = "";
  }

  // Clear file uploads in edit mode
  profileImageFiles.value = [];
  coverImageFiles.value = [];
}

// Watch for initialData changes
watch(
  () => props.initialData,
  (newData) => {
    populateForm(newData);
  },
  { immediate: true, deep: true }
);

// Handle submit
function handleSubmit() {
  const payload = {
    ...form,
    birth_date: form.birth_date
      ? form.birth_date.toDate(getLocalTimeZone()).toISOString().split("T")[0]
      : null,
  };

  // Add file uploads if images are shown
  if (props.showImages) {
    // Only include tmp_profile_image if there's a new upload (starts with 'tmp-')
    // or if user removed the image (empty array)
    const profileValue = profileImageFiles.value?.[0];
    if (!profileValue) {
      // User removed image
      payload.tmp_profile_image = null;
    } else if (profileValue.startsWith("tmp-")) {
      // User uploaded new image
      payload.tmp_profile_image = profileValue;
    }
    // If profileValue is URL (existing image), don't include in payload

    // Same for cover image
    const coverValue = coverImageFiles.value?.[0];
    if (!coverValue) {
      // User removed image
      payload.tmp_cover_image = null;
    } else if (coverValue.startsWith("tmp-")) {
      // User uploaded new image
      payload.tmp_cover_image = coverValue;
    }
    // If coverValue is URL (existing image), don't include in payload
  }

  // Remove password if empty and not creating
  if (!props.isCreate && !payload.password) {
    delete payload.password;
  }

  emit("submit", payload);
}

// Handle reset
function handleReset() {
  populateForm(props.initialData);
  profileImageFiles.value = [];
  coverImageFiles.value = [];
  emit("reset");
}

// Expose form data
defineExpose({
  form,
  profileImageFiles,
  coverImageFiles,
});
</script>
