<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex items-center justify-between">
      <div class="space-y-1">
        <h4 class="font-semibold tracking-tight">Brand Members</h4>
        <p class="text-muted-foreground text-sm tracking-tight">
          Users assigned to manage this brand.
        </p>
      </div>
      <Button @click="showAdd = true" size="sm">
        <Icon name="hugeicons:add-01" class="size-4" />
        Add Member
      </Button>
    </div>

    <!-- Members List -->
    <div v-if="brandEvent?.members?.length" class="divide-border divide-y rounded-xl border">
      <div
        v-for="member in brandEvent.members"
        :key="member.id"
        class="flex items-center justify-between p-4"
      >
        <div class="flex items-center gap-x-3">
          <div class="bg-muted flex size-9 items-center justify-center rounded-full">
            <img
              v-if="member.avatar?.sm"
              :src="member.avatar.sm"
              class="size-9 rounded-full object-cover"
            />
            <Icon v-else name="hugeicons:user" class="text-muted-foreground size-4" />
          </div>
          <div>
            <p class="text-sm font-medium tracking-tight">{{ member.name }}</p>
            <p class="text-muted-foreground text-xs">{{ member.email }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-muted-foreground text-xs capitalize">{{ member.role }}</span>
          <Button variant="ghost" size="sm" @click="removeMember(member.id)">
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </div>
      </div>
    </div>

    <div v-else class="flex flex-col items-center justify-center px-4 py-16">
      <div class="flex flex-col items-center justify-center gap-y-4 text-center">
        <div
          class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
        >
          <div class="translate-y-1.5 -rotate-6">
            <Icon name="hugeicons:user" />
          </div>
          <div>
            <Icon name="hugeicons:user-group" />
          </div>
          <div class="translate-y-1.5 rotate-6">
            <Icon name="hugeicons:user" />
          </div>
        </div>

        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tight">Brand Members</h1>
          <p class="text-muted-foreground max-w-md text-sm">No members assigned yet. Add users to manage this brand.</p>
        </div>
      </div>
    </div>

    <!-- Add Member Dialog -->
    <Dialog v-model:open="showAdd">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add Member</DialogTitle>
          <DialogDescription>Add a user by email to manage this brand.</DialogDescription>
        </DialogHeader>
        <form @submit.prevent="addMember" class="space-y-4">
          <div class="space-y-2">
            <Label for="member_email">Email</Label>
            <Input
              id="member_email"
              v-model="newMemberEmail"
              type="email"
              placeholder="user@example.com"
              required
            />
          </div>
          <div class="flex items-center gap-x-2">
            <Checkbox id="send_login_member" v-model="sendLoginEmail" />
            <Label for="send_login_member" class="text-sm font-normal"
              >Send login email to PIC(s)</Label
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button variant="outline" type="button" @click="showAdd = false">Cancel</Button>
            <Button type="submit" :disabled="addingMember">
              <Icon v-if="addingMember" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              Add
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({ brandEvent: Object });
const emit = defineEmits(["refresh"]);
const route = useRoute();
const client = useSanctumClient();

const showAdd = ref(false);
const newMemberEmail = ref("");
const sendLoginEmail = ref(false);
const addingMember = ref(false);

const membersUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/members`
);

async function addMember() {
  addingMember.value = true;
  try {
    await client(membersUrl.value, {
      method: "POST",
      body: {
        email: newMemberEmail.value,
        send_login_email: sendLoginEmail.value,
      },
    });
    toast.success("Member added");
    newMemberEmail.value = "";
    sendLoginEmail.value = false;
    showAdd.value = false;
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to add member");
  } finally {
    addingMember.value = false;
  }
}

async function removeMember(userId) {
  try {
    await client(`${membersUrl.value}/${userId}`, { method: "DELETE" });
    toast.success("Member removed");
    emit("refresh");
  } catch (e) {
    toast.error("Failed to remove member");
  }
}
</script>
