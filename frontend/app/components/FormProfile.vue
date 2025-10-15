<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-6">
    <div class="space-y-5">
      <!-- Profile and Cover Images -->
      <div v-if="showImages" class="space-y-5">
        <div class="space-y-2">
          <Label>Profile Image</Label>

          <InputFile
            v-if="showInputFile.profile_image"
            v-model="imageFiles.profile_image"
            :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
            :allow-multiple="false"
            :max-files="1"
            class="mt-3"
          />

          <div v-else class="squircle relative isolate aspect-square max-w-40">
            <img
              :src="initialData?.profile_image?.md"
              alt=""
              class="border-border size-full rounded-lg border object-cover"
              loading="lazy"
            />

            <button
              type="button"
              @click="handleDeleteImage('profile_image')"
              class="absolute top-1.5 right-1.5 flex size-8 items-center justify-center rounded-full bg-black/40 text-white shadow-sm ring ring-white/20 backdrop-blur-sm transition hover:bg-black"
            >
              <Icon name="hugeicons:delete-01" class="size-4" />
            </button>
          </div>

          <button
            v-if="deleteFlags.profile_image && initialData?.profile_image"
            type="button"
            @click="handleUndoDeleteImage('profile_image')"
            class="text-primary hover:text-primary/80 mx-auto flex items-center gap-1.5 text-sm font-medium tracking-tight transition"
          >
            <Icon name="hugeicons:undo-02" class="size-4" />
            Undo Delete
          </button>

          <p v-if="errors.tmp_profile_image" class="text-destructive text-sm">
            {{ errors.tmp_profile_image[0] }}
          </p>
        </div>

        <div class="space-y-2">
          <Label>Cover Image</Label>

          <InputFile
            v-if="showInputFile.cover_image"
            v-model="imageFiles.cover_image"
            :accepted-file-types="['image/jpeg', 'image/png', 'image/jpg', 'image/webp']"
            :allow-multiple="false"
            :max-files="1"
            class="mt-3"
          />

          <div v-else class="relative isolate">
            <img
              :src="initialData?.cover_image?.sm"
              alt=""
              class="border-border size-full rounded-lg border object-cover"
              loading="lazy"
            />

            <button
              type="button"
              @click="handleDeleteImage('cover_image')"
              class="absolute top-1.5 right-1.5 flex size-8 items-center justify-center rounded-full bg-black/40 text-white shadow-sm ring ring-white/20 backdrop-blur-sm transition hover:bg-black"
            >
              <Icon name="hugeicons:delete-01" class="size-4" />
            </button>
          </div>

          <button
            v-if="deleteFlags.cover_image && initialData?.cover_image"
            type="button"
            @click="handleUndoDeleteImage('cover_image')"
            class="text-primary hover:text-primary/80 mx-auto flex items-center gap-1.5 text-sm font-medium tracking-tight transition"
          >
            <Icon name="hugeicons:undo-02" class="size-4" />
            Undo Delete
          </button>

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
                  : "Pick your birth date"
              }}
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-auto p-0">
            <CalendarMonthYearSelect
              v-model="form.birth_date"
              :disable-future-dates="true"
              initial-focus
            />
          </PopoverContent>
        </Popover>

        <p v-if="errors.birth_date" class="text-destructive text-sm">
          {{ errors.birth_date[0] }}
        </p>
      </div>

      <div class="space-y-2">
        <Label for="gender">Gender</Label>

        <RadioGroup class="flex flex-wrap gap-2" v-model="form.gender">
          <div
            class="border-border has-data-[state=checked]:border-primary/50 relative flex flex-col items-start gap-4 rounded-md border p-2 shadow-xs outline-none"
          >
            <div class="flex items-center gap-2">
              <RadioGroupItem id="male" value="male" class="after:absolute after:inset-0" />
              <Label for="male">Male</Label>
            </div>
          </div>

          <div
            class="border-border has-data-[state=checked]:border-primary/50 relative flex flex-col items-start gap-4 rounded-md border p-2 shadow-xs outline-none"
          >
            <div class="flex items-center gap-2">
              <RadioGroupItem id="female" value="female" class="after:absolute after:inset-0" />
              <Label for="female">Female</Label>
            </div>
          </div>
        </RadioGroup>

        <p v-if="errors.gender" class="text-destructive text-sm">{{ errors.gender[0] }}</p>
      </div>

      <div class="space-y-2">
        <Label for="bio">Bio</Label>
        <Textarea id="bio" v-model="form.bio" maxlength="1000" />
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
              :model-value="form.roles.includes(role.name)"
              @update:model-value="(checked) => toggleRole(role.name, checked)"
            />
            <Label :for="`role-${role.id}`" class="cursor-pointer font-normal capitalize">
              {{ role.name }}
            </Label>
          </div>
        </div>
        <p v-if="errors.roles" class="text-destructive text-sm">{{ errors.roles[0] }}</p>
      </div>
    </div>

    <div v-if="!showAccountSettings" class="space-y-2">
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
        type="submit"
        :disabled="loading"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter transition disabled:opacity-50"
      >
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
      </button>
    </div>
  </form>

  <div></div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
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

const deleteFlags = ref({
  profile_image: false,
  cover_image: false,
});

// Uploaded files
const imageFiles = ref({
  profile_image: [],
  cover_image: [],
});

// Computed: Show input file when no existing image OR user clicked delete
const showInputFile = computed(() => ({
  profile_image: !props.initialData?.profile_image || deleteFlags.value.profile_image,
  cover_image: !props.initialData?.cover_image || deleteFlags.value.cover_image,
}));

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
function toggleRole(roleName, checked) {
  const index = form.roles.indexOf(roleName);
  const isChecked = checked === true || checked === "indeterminate";

  if (isChecked) {
    if (index === -1) {
      form.roles.push(roleName);
    }
  } else {
    if (index > -1) {
      form.roles.splice(index, 1);
    }
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

  // Clear roles array first, then populate with new values
  form.roles.splice(0, form.roles.length);

  if (Array.isArray(data.roles)) {
    const roleNames = data.roles.map((role) => (typeof role === "string" ? role : role.name));
    form.roles.push(...roleNames);
  }

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

  // Clear file uploads and reset delete flags
  imageFiles.value.profile_image = [];
  imageFiles.value.cover_image = [];
  deleteFlags.value.profile_image = false;
  deleteFlags.value.cover_image = false;
}

// Watch for initialData changes
watch(
  () => props.initialData,
  (newData) => {
    populateForm(newData);
  },
  { immediate: true, deep: true }
);

// Generic handler for deleting images
function handleDeleteImage(type) {
  deleteFlags.value[type] = true;
  imageFiles.value[type] = [];
}

// Generic handler for undoing image deletion
function handleUndoDeleteImage(type) {
  deleteFlags.value[type] = false;
  imageFiles.value[type] = [];
}

// Handle submit
function handleSubmit() {
  const payload = {
    ...form,
    birth_date: form.birth_date
      ? `${form.birth_date.year}-${String(form.birth_date.month).padStart(2, "0")}-${String(form.birth_date.day).padStart(2, "0")}`
      : null,
  };

  // Add file uploads if images are shown
  if (props.showImages) {
    // Handle profile image
    const profileValue = imageFiles.value.profile_image?.[0];
    if (profileValue && profileValue.startsWith("tmp-")) {
      // User uploaded new image
      payload.tmp_profile_image = profileValue;
    } else if (deleteFlags.value.profile_image && !profileValue) {
      // User clicked delete and didn't upload new image
      payload.delete_profile_image = true;
    }

    // Handle cover image
    const coverValue = imageFiles.value.cover_image?.[0];
    if (coverValue && coverValue.startsWith("tmp-")) {
      // User uploaded new image
      payload.tmp_cover_image = coverValue;
    } else if (deleteFlags.value.cover_image && !coverValue) {
      // User clicked delete and didn't upload new image
      payload.delete_cover_image = true;
    }
  }

  // Remove password if empty and not creating
  if (!props.isCreate && !payload.password) {
    delete payload.password;
  }

  emit("submit", payload);
}

// Expose form data and methods
defineExpose({
  form,
  imageFiles,
  handleSubmit,
});

defineShortcuts({
  meta_s: {
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
