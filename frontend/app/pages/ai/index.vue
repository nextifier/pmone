<template>
  <div class="h-screen-offset relative isolate -mx-4 flex flex-col overflow-hidden">
    <!-- Conversation area -->
    <Conversation class="grow">
      <div class="bg-info/50 mx-auto flex max-w-3xl grow flex-col gap-y-4 p-4">
        <!-- Empty state -->
        <div
          v-if="messages.length === 0 && !streamingContent"
          class="flex grow flex-col items-center justify-center text-center tracking-tight"
        >
          <Icon name="hugeicons:claude" class="text-primary size-12" />
          <h3 class="page-title mt-2">
            <DashboardGreeting />
          </h3>
          <p class="page-description mt-1.5">
            Cek data event, pantau order, atau minta bantuan nulis konten.
          </p>

          <div class="mt-6 flex w-full flex-wrap items-center justify-center gap-2">
            <Suggestion
              v-for="prompt in suggestedPrompts"
              :key="prompt"
              :suggestion="prompt"
              class="tracking-tight"
              @click="handleSend(prompt)"
            />
          </div>
        </div>

        <!-- Messages -->
        <template v-else>
          <!-- Loading skeleton -->
          <div v-if="isLoadingMessages" class="space-y-4">
            <Skeleton
              v-for="i in 3"
              :key="i"
              class="h-16 w-3/4"
              :class="i % 2 === 0 ? 'ml-auto' : ''"
            />
          </div>

          <template v-else>
            <Message v-for="msg in messages" :key="msg.id" :from="msg.role">
              <MessageContent class="text-base tracking-tight">
                <MessageResponse v-if="msg.role === 'assistant'" :content="msg.content" />
                <p v-else class="whitespace-pre-wrap">{{ msg.content }}</p>
              </MessageContent>
            </Message>

            <!-- Tool status indicator -->
            <div v-if="toolStatus" class="flex items-center gap-3">
              <Loader class="text-muted-foreground" />
              <span class="text-muted-foreground text-sm tracking-tight">
                {{ toolStatus }}
              </span>
            </div>

            <!-- Streaming message -->
            <Message v-if="streamingContent" from="assistant">
              <MessageContent class="text-base tracking-tight">
                <MessageResponse :content="streamingContent" mode="streaming" />
              </MessageContent>
            </Message>

            <!-- Submitted state (waiting for first token) -->
            <Loader
              v-if="isStreaming && !streamingContent && !toolStatus"
              class="text-muted-foreground mx-auto"
            />
          </template>
        </template>
      </div>
      <ConversationScrollButton />
    </Conversation>

    <!-- Input area -->
    <div class="px-2 pb-8">
      <PromptInput
        class="bg-card dark:bg-border border-foreground/20 pointer-events-auto mx-auto max-w-3xl rounded-2xl"
        @submit="handlePromptSubmit"
      >
        <PromptInputBody>
          <PromptInputTextarea
            placeholder="How can I help you today?"
            class="p-5! text-base tracking-tight sm:p-6!"
            autofocus
          />
        </PromptInputBody>
        <PromptInputFooter>
          <span />
          <PromptInputSubmit :status="chatStatus" />
        </PromptInputFooter>
      </PromptInput>
    </div>
  </div>
</template>

<script setup lang="ts">
import Conversation from "@/components/ai-elements/conversation/Conversation.vue";
import ConversationScrollButton from "@/components/ai-elements/conversation/ConversationScrollButton.vue";
import Loader from "@/components/ai-elements/loader/Loader.vue";
import Message from "@/components/ai-elements/message/Message.vue";
import MessageContent from "@/components/ai-elements/message/MessageContent.vue";
import MessageResponse from "@/components/ai-elements/message/MessageResponse.vue";
import PromptInput from "@/components/ai-elements/prompt-input/PromptInput.vue";
import PromptInputBody from "@/components/ai-elements/prompt-input/PromptInputBody.vue";
import PromptInputFooter from "@/components/ai-elements/prompt-input/PromptInputFooter.vue";
import PromptInputSubmit from "@/components/ai-elements/prompt-input/PromptInputSubmit.vue";
import PromptInputTextarea from "@/components/ai-elements/prompt-input/PromptInputTextarea.vue";
import Suggestion from "@/components/ai-elements/suggestion/Suggestion.vue";
import type { ChatStatus } from "ai";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: "AI" });

const {
  messages,
  isStreaming,
  streamingContent,
  toolStatus,
  isLoadingMessages,
  fetchConversations,
  sendMessage,
  stopStreaming,
} = useAiChat();

const suggestedPrompts = [
  // Inbox & Inquiry
  "Ada berapa inquiry masuk bulan ini?",
  //   "Inquiry yang belum direspon",
  //   "Inquiry terbanyak dari event mana?",

  // Posts & Konten
  "Artikel dengan views tertinggi minggu ini",
  //   "Artikel yang masih draft",
  //   "Buatkan draft artikel recap event terakhir",

  // Exhibitor & Brand
  //   "Exhibitor yang belum bayar booth",
  "List exhibitor baru yang daftar minggu ini",
  //   "Exhibitor mana yang belum upload dokumen?",
  //   "Berapa total exhibitor per event?",

  // Order & Pembayaran
  //   "Ringkasan order booth bulan ini",
  "Total revenue dari semua order bulan ini",
  //   "Order yang statusnya masih pending",

  // Event
  //   "Event terdekat yang akan berlangsung",
  "Perbandingan jumlah exhibitor antar event",

  // Task
  "Task yang belum selesai minggu ini",
  //   "Siapa yang punya task paling banyak?",

  // Contact & CRM
  "Berapa total kontak baru bulan ini?",
  //   "Kontak yang belum di-assign ke project",

  // Short Link & Analytics
  "Short link dengan klik terbanyak bulan ini",
  //   "Berapa total klik semua short link minggu ini?",

  // Writing & Draft
  //   "Buatkan draft email undangan exhibitor",
  //   "Buatkan draft email follow-up pembayaran booth",
  "Buatkan caption Instagram untuk promosi event",
  //   "Buatkan draft email pengingat deadline dokumen",
];

const chatStatus = computed<ChatStatus>(() => (isStreaming.value ? "streaming" : "ready"));

function handlePromptSubmit({ text }: { text: string; files?: any[] }) {
  if (isStreaming.value) {
    stopStreaming();
    return;
  }
  if (!text.trim()) return;
  handleSend(text);
}

async function handleSend(message: string) {
  try {
    await sendMessage(message);
  } catch (error: any) {
    toast.error(error.message || "Failed to send message.");
  }
}

onMounted(() => {
  fetchConversations();
});
</script>

<style>
/* Make StickToBottom's contentRef stretch so empty state can vertically center */
[role="log"] > div > div > div {
  display: flex;
  flex-direction: column;
  min-height: 100%;
}
</style>
