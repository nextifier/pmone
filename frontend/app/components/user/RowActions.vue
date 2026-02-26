<template>
  <div class="flex items-center justify-end gap-1 *:shrink-0">
    <a
      v-if="phone"
      v-tippy="'WhatsApp'"
      :href="whatsappLink"
      target="_blank"
      rel="noopener noreferrer"
      class="hover:bg-muted text-success-foreground inline-flex size-8 items-center justify-center rounded-md"
    >
      <Icon name="hugeicons:whatsapp" class="size-4" />
    </a>
    <a
      v-if="email"
      v-tippy="'Email'"
      :href="`mailto:${email}`"
      class="hover:bg-muted text-info-foreground inline-flex size-8 items-center justify-center rounded-md"
    >
      <Icon name="hugeicons:mail-01" class="size-4" />
    </a>
    <Popover>
      <PopoverTrigger as-child>
        <button
          class="hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md"
        >
          <Icon name="lucide:ellipsis" class="size-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" class="w-40 p-1">
        <div class="flex flex-col">
          <PopoverClose as-child>
            <NuxtLink
              :to="`/${username}`"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="lucide:user-round-search" class="size-4 shrink-0" />
              <span>Profile</span>
            </NuxtLink>
          </PopoverClose>

          <PopoverClose as-child>
            <NuxtLink
              :to="`/${username}/edit`"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="lucide:pencil-line" class="size-4 shrink-0" />
              <span>Edit</span>
            </NuxtLink>
          </PopoverClose>

          <PopoverClose as-child>
            <NuxtLink
              :to="`/${username}/analytics`"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="lucide:chart-no-axes-combined" class="size-4 shrink-0" />
              <span>Analytics</span>
            </NuxtLink>
          </PopoverClose>

          <PopoverClose as-child>
            <button
              :class="[
                'flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight',
                isVerified ? 'hover:bg-muted' : '',
              ]"
              @click="isVerified ? (unverifyDialogOpen = true) : (verifyDialogOpen = true)"
            >
              <Icon
                name="material-symbols:verified"
                :class="['size-4 shrink-0', isVerified ? 'text-muted-foreground' : 'text-info']"
              />
              <span>{{ isVerified ? "Unverify" : "Verify" }}</span>
            </button>
          </PopoverClose>

          <PopoverClose as-child>
            <button
              class="hover:bg-destructive/10 text-destructive flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              @click="deleteDialogOpen = true"
            >
              <Icon name="lucide:trash" class="size-4 shrink-0" />
              <span>Delete</span>
            </button>
          </PopoverClose>
        </div>
      </PopoverContent>
    </Popover>

    <!-- Verify Dialog -->
    <DialogResponsive v-model:open="verifyDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Verify user?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">This will verify this user.</p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="singleVerifyPending"
              @click="verifyDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-info hover:bg-info/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="singleVerifyPending"
              @click="handleVerify"
            >
              <Spinner v-if="singleVerifyPending" class="size-4 text-white" />
              <span v-else>Verify</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Unverify Dialog -->
    <DialogResponsive v-model:open="unverifyDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Unverify user?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">This will unverify this user.</p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="singleUnverifyPending"
              @click="unverifyDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-muted-foreground hover:bg-muted-foreground/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="singleUnverifyPending"
              @click="handleUnverify"
            >
              <Spinner v-if="singleUnverifyPending" class="size-4 text-white" />
              <span v-else>Unverify</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This action can't be undone. This will permanently delete this user.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="singleDeletePending"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="singleDeletePending"
              @click="handleDelete"
            >
              <Spinner v-if="singleDeletePending" class="size-4 text-white" />
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({
  username: { type: String, required: true },
  isVerified: { type: Boolean, default: false },
  phone: { type: String, default: null },
  email: { type: String, default: null },
});

const whatsappLink = computed(() => {
  if (!props.phone) return null;
  const cleanPhone = props.phone.replace(/\D/g, "");
  return `https://wa.me/${cleanPhone}`;
});

const emit = defineEmits(["refresh"]);

const deleteDialogOpen = ref(false);
const verifyDialogOpen = ref(false);
const unverifyDialogOpen = ref(false);
const singleDeletePending = ref(false);
const singleVerifyPending = ref(false);
const singleUnverifyPending = ref(false);

const handleVerify = async () => {
  singleVerifyPending.value = true;
  try {
    const client = useSanctumClient();
    await client(`/api/users/${props.username}/verify`, { method: "POST" });
    emit("refresh");
    verifyDialogOpen.value = false;
    toast.success("User verified successfully");
  } catch (error) {
    console.error("Failed to verify user:", error);
    toast.error("Failed to verify user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    singleVerifyPending.value = false;
  }
};

const handleUnverify = async () => {
  singleUnverifyPending.value = true;
  try {
    const client = useSanctumClient();
    await client(`/api/users/${props.username}/unverify`, { method: "POST" });
    emit("refresh");
    unverifyDialogOpen.value = false;
    toast.success("User unverified successfully");
  } catch (error) {
    console.error("Failed to unverify user:", error);
    toast.error("Failed to unverify user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    singleUnverifyPending.value = false;
  }
};

const handleDelete = async () => {
  singleDeletePending.value = true;
  try {
    const client = useSanctumClient();
    await client(`/api/users/${props.username}`, { method: "DELETE" });
    emit("refresh");
    deleteDialogOpen.value = false;
    toast.success("User deleted successfully");
  } catch (error) {
    console.error("Failed to delete user:", error);
    toast.error("Failed to delete user", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    singleDeletePending.value = false;
  }
};
</script>
