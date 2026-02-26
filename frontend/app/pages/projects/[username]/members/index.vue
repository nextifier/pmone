<template>
  <div class="flex flex-col gap-y-5">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold tracking-tight">Members</h2>
      <span class="text-muted-foreground text-sm tracking-tight">
        {{ memberCount }} {{ memberCount === 1 ? "member" : "members" }}
      </span>
    </div>

    <!-- Search -->
    <div class="relative">
      <Icon
        name="lucide:search"
        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
      />
      <input
        v-model="search"
        type="text"
        placeholder="Search by name or email..."
        class="border-border bg-background placeholder:text-muted-foreground focus:ring-ring h-9 w-full rounded-md border pr-3 pl-9 text-sm tracking-tight outline-none focus:ring-1"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-sm tracking-tight">Loading users...</span>
      </div>
    </div>

    <!-- User List -->
    <div v-else class="flex flex-col divide-y" v-auto-animate>
      <template v-if="filteredUsers.length > 0">
        <div
          v-for="user in filteredUsers"
          :key="user.id"
          class="hover:bg-muted/50 flex items-center gap-x-3 px-1 py-3 transition"
          :class="{ 'pointer-events-none opacity-50': togglingIds.has(user.id) }"
        >
          <Checkbox
            :modelValue="isMember(user.id)"
            @update:modelValue="toggleMember(user)"
            :disabled="togglingIds.has(user.id)"
          />

          <NuxtLink
            :to="`/${user.username}`"
            class="flex min-w-0 flex-1 items-center gap-x-3"
          >
            <Avatar :model="user" class="size-9 shrink-0" rounded="rounded-full" />

            <div class="flex min-w-0 flex-1 flex-col">
              <span class="truncate text-sm font-medium tracking-tight">{{ user.name }}</span>
              <span class="text-muted-foreground truncate text-xs tracking-tight">{{
                user.email
              }}</span>
            </div>
          </NuxtLink>

          <Spinner v-if="togglingIds.has(user.id)" class="size-4 shrink-0" />
        </div>
      </template>

      <div v-else class="flex flex-col items-center justify-center py-12">
        <Icon name="lucide:search-x" class="text-muted-foreground size-8" />
        <p class="text-muted-foreground mt-2 text-sm tracking-tight">No users found</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Members · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const search = ref("");
const loading = ref(true);
const users = ref([]);
const memberIds = ref(new Set());
const togglingIds = ref(new Set());

const memberCount = computed(() => memberIds.value.size);

const filteredUsers = computed(() => {
  let list = users.value;
  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
    );
  }
  return [...list].sort((a, b) => {
    const aMember = memberIds.value.has(a.id) ? 0 : 1;
    const bMember = memberIds.value.has(b.id) ? 0 : 1;
    return aMember - bMember;
  });
});

const isMember = (userId) => memberIds.value.has(userId);

async function toggleMember(user) {
  togglingIds.value = new Set([...togglingIds.value, user.id]);

  // Optimistic update
  const wasMember = memberIds.value.has(user.id);
  const newSet = new Set(memberIds.value);
  if (wasMember) {
    newSet.delete(user.id);
  } else {
    newSet.add(user.id);
  }
  memberIds.value = newSet;

  try {
    const response = await client(`/api/projects/${route.params.username}/members/toggle`, {
      method: "POST",
      body: { user_id: user.id },
    });

    const firstName = user.name.split(" ")[0];
    if (response.action === "added") {
      toast.success(`${firstName} added to ${props.project?.name}`);
    } else {
      toast.success(`${firstName} removed from ${props.project?.name}`);
    }
  } catch (err) {
    // Rollback
    const rollback = new Set(memberIds.value);
    if (wasMember) {
      rollback.add(user.id);
    } else {
      rollback.delete(user.id);
    }
    memberIds.value = rollback;

    toast.error("Failed to update member", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    const updated = new Set(togglingIds.value);
    updated.delete(user.id);
    togglingIds.value = updated;
  }
}

async function fetchData() {
  loading.value = true;
  try {
    const [eligibleRes, projectRes] = await Promise.all([
      client("/api/projects/eligible-members"),
      client(`/api/projects/${route.params.username}`),
    ]);

    users.value = eligibleRes.data || [];

    const members = projectRes.data?.members || [];
    memberIds.value = new Set(members.map((m) => m.id));
  } catch (err) {
    console.error("Failed to load members data:", err);
    toast.error("Failed to load members");
  } finally {
    loading.value = false;
  }
}

onMounted(fetchData);
</script>
