<template>
  <!-- Back + New Chat -->
  <SidebarGroup>
    <SidebarMenu>
      <SidebarMenuItem>
        <ButtonBack v-slot="{ goBack }" destination="/dashboard" :force-destination="true">
          <SidebarMenuButton
            tooltip="Back to Dashboard"
            @click="
              goBack();
              setOpenMobile(false);
            "
          >
            <Icon name="hugeicons:arrow-left-02" class="size-4.5! shrink-0" />
            <span class="tracking-tight">Back</span>
            <KbdGroup class="ml-auto">
              <Kbd>B</Kbd>
            </KbdGroup>
          </SidebarMenuButton>
        </ButtonBack>
      </SidebarMenuItem>
      <SidebarMenuItem>
        <SidebarMenuButton
          tooltip="New Chat"
          @click="
            newConversation();
            setOpenMobile(false);
          "
          class="bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary active:text-primary-foreground tracking-tight"
        >
          <Icon name="hugeicons:add-01" class="size-4.5! shrink-0" />
          <span>New Chat</span>
          <KbdGroup class="ml-auto">
            <Kbd class="bg-primary-foreground/15 bg-primary-foreground/8 text-primary-foreground"
              >N</Kbd
            >
          </KbdGroup>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>

  <!-- Conversations -->
  <SidebarGroup>
    <SidebarGroupLabel class="text-muted-foreground tracking-tight">
      Recent Chats
    </SidebarGroupLabel>
    <SidebarMenu>
      <template v-if="isLoadingConversations || !hasFetchedConversations">
        <SidebarMenuItem v-for="i in 3" :key="i">
          <div class="flex h-8 items-center px-2">
            <Skeleton class="h-4 w-full" />
          </div>
        </SidebarMenuItem>
      </template>
      <p
        v-else-if="conversations.length === 0"
        class="text-muted-foreground px-3 py-4 text-center text-xs tracking-tight sm:text-sm"
      >
        No conversations yet
      </p>
      <SidebarMenuItem
        v-for="conv in conversations"
        :key="conv.id"
        v-else
        class="group/conv tracking-tight"
      >
        <SidebarMenuButton
          :is-active="conv.id === activeConversationId"
          :tooltip="conv.title"
          @click="
            loadConversation(conv.id);
            setOpenMobile(false);
          "
        >
          <span>{{ conv.title }}</span>
        </SidebarMenuButton>
        <SidebarMenuAction
          class="text-destructive hover:text-destructive opacity-0 transition-opacity group-hover/conv:opacity-100"
          @click.stop="openDeleteDialog(conv)"
        >
          <Icon name="hugeicons:delete-02" class="size-3.5" />
        </SidebarMenuAction>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>

  <!-- Delete Confirmation Dialog -->
  <DialogResponsive v-model:open="deleteDialogOpen">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-6">
        <div class="text-foreground text-lg font-semibold tracking-tight">Delete Conversation?</div>
        <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
          Are you sure you want to delete
          <strong>{{ conversationToDelete?.title }}</strong
          >? This action can't be undone.
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" @click="deleteDialogOpen = false"> Cancel </Button>
          <Button variant="destructive" :disabled="deleteLoading" @click="confirmDelete">
            <Spinner v-if="deleteLoading" class="size-4" />
            <span v-else>Delete</span>
          </Button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup lang="ts">
import { useSidebar } from "@/components/ui/sidebar/utils";
import { toast } from "vue-sonner";

const route = useRoute();
const { setOpenMobile } = useSidebar();

const {
  conversations,
  activeConversationId,
  isLoadingConversations,
  hasFetchedConversations,
  loadConversation,
  newConversation,
  deleteConversation,
} = useAiChat();

// Delete confirmation
const deleteDialogOpen = ref(false);
const deleteLoading = ref(false);
const conversationToDelete = ref<{ id: string; title: string } | null>(null);

function openDeleteDialog(conv: { id: string; title: string }) {
  conversationToDelete.value = conv;
  deleteDialogOpen.value = true;
}

async function confirmDelete() {
  if (!conversationToDelete.value) return;
  deleteLoading.value = true;
  try {
    await deleteConversation(conversationToDelete.value.id);
    toast.success("Conversation deleted.");
    deleteDialogOpen.value = false;
    conversationToDelete.value = null;
  } catch {
    toast.error("Failed to delete conversation.");
  } finally {
    deleteLoading.value = false;
  }
}

// Keyboard shortcuts
defineShortcuts({
  n: {
    handler: () => newConversation(),
    whenever: [computed(() => route.path === "/ai")],
  },
});
</script>
