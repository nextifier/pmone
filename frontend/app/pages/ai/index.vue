<template>
  <MessageScrollerProvider :auto-scroll="true">
    <div class="h-screen-offset relative isolate -mx-4 flex flex-col overflow-hidden">
      <!-- Conversation area -->
      <div class="relative min-h-0 flex-1">
        <!-- Empty state -->
        <div
          v-if="messages.length === 0 && !streamingContent"
          class="flex h-full flex-col items-center justify-center px-4 text-center tracking-tight"
        >
          <Icon name="hugeicons:claude" class="text-foreground size-12" />
          <h3 class="page-title mt-2">
            <DashboardGreeting />
          </h3>
          <p class="page-description mt-1.5">
            Cek data event, pantau order, atau minta bantuan nulis konten.
          </p>

          <div class="mt-6 flex w-full max-w-3xl flex-wrap items-center justify-center gap-2">
            <Button
              v-for="prompt in suggestedPrompts"
              :key="prompt"
              variant="outline"
              size="sm"
              class="tracking-tight"
              @click="handleSend(prompt)"
            >
              {{ prompt }}
            </Button>
          </div>
        </div>

        <!-- Messages -->
        <MessageScroller v-else class="size-full">
          <MessageScrollerViewport>
            <MessageScrollerContent
              :aria-busy="isStreaming"
              class="mx-auto flex max-w-3xl flex-col gap-4 p-4"
            >
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
                <MessageScrollerItem
                  v-for="msg in messages"
                  :key="msg.id"
                  :message-id="msg.id"
                  :scroll-anchor="msg.role === 'user'"
                >
                  <Message :align="msg.role === 'user' ? 'end' : 'start'">
                    <MessageContent>
                      <!-- User: plain text -->
                      <Bubble v-if="msg.role === 'user'" variant="muted" align="end">
                        <BubbleContent class="text-base leading-relaxed tracking-tight whitespace-pre-wrap">
                          {{ msg.content }}
                        </BubbleContent>
                      </Bubble>
                      <!-- Assistant: markdown -->
                      <Bubble v-else variant="ghost" align="start">
                        <BubbleContent class="text-base leading-relaxed tracking-tight">
                          <AiMarkdown :content="msg.content" />
                        </BubbleContent>
                      </Bubble>
                    </MessageContent>
                  </Message>
                </MessageScrollerItem>

                <!-- Tool status indicator -->
                <Marker v-if="toolStatus" variant="border" class="mr-auto">
                  <MarkerIcon>
                    <Spinner />
                  </MarkerIcon>
                  <MarkerContent class="shimmer">{{ toolStatus }}</MarkerContent>
                </Marker>

                <!-- Streaming message -->
                <MessageScrollerItem v-if="streamingContent" message-id="streaming">
                  <Message align="start">
                    <MessageContent>
                      <Bubble variant="ghost" align="start">
                        <BubbleContent class="text-base leading-relaxed tracking-tight">
                          <AiMarkdown :content="streamingContent" streaming />
                        </BubbleContent>
                      </Bubble>
                    </MessageContent>
                  </Message>
                </MessageScrollerItem>

                <!-- Submitted state (waiting for first token) -->
                <Spinner
                  v-if="isStreaming && !streamingContent && !toolStatus"
                  class="text-muted-foreground mx-auto"
                />
              </template>
            </MessageScrollerContent>
          </MessageScrollerViewport>
          <MessageScrollerButton />
        </MessageScroller>
      </div>

      <!-- Input area -->
      <div class="px-2 pb-8">
        <form class="mx-auto max-w-3xl" @submit.prevent="onSubmit">
          <InputGroup
            class="bg-card dark:bg-border border-foreground/20 rounded-2xl"
          >
            <InputGroupTextarea
              v-model="input"
              placeholder="How can I help you today?"
              class="min-h-14 p-5! text-base tracking-tight sm:p-6!"
              autofocus
              @keydown="onKeydown"
            />
            <InputGroupAddon align="block-end" class="pt-1">
              <InputGroupButton
                type="submit"
                variant="default"
                size="icon-sm"
                class="ml-auto"
                :aria-label="isStreaming ? 'Stop' : 'Send'"
                :disabled="!isStreaming && !input.trim()"
              >
                <Icon :name="isStreaming ? 'hugeicons:stop' : 'hugeicons:sent-02'" />
              </InputGroupButton>
            </InputGroupAddon>
          </InputGroup>
        </form>
      </div>
    </div>
  </MessageScrollerProvider>
</template>

<script setup lang="ts">
import {
  MessageScroller,
  MessageScrollerButton,
  MessageScrollerContent,
  MessageScrollerItem,
  MessageScrollerProvider,
  MessageScrollerViewport,
} from "@/components/ui/message-scroller";
import { Message, MessageContent } from "@/components/ui/message";
import { Bubble, BubbleContent } from "@/components/ui/bubble";
import { Marker, MarkerContent, MarkerIcon } from "@/components/ui/marker";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupTextarea,
} from "@/components/ui/input-group";
import AiMarkdown from "@/components/ai/AiMarkdown.vue";
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

const input = ref("");

const suggestedPrompts = [
  "Ada berapa inquiry masuk bulan ini?",
  "Artikel dengan views tertinggi minggu ini",
  "List exhibitor baru yang daftar minggu ini",
  "Total revenue dari semua order bulan ini",
  "Perbandingan jumlah exhibitor antar event",
  "Task yang belum selesai minggu ini",
  "Berapa total kontak baru bulan ini?",
  "Short link dengan klik terbanyak bulan ini",
  "Buatkan caption Instagram untuk promosi event",
];

function onSubmit() {
  if (isStreaming.value) {
    stopStreaming();
    return;
  }
  const text = input.value.trim();
  if (!text) return;
  input.value = "";
  handleSend(text);
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
    event.preventDefault();
    onSubmit();
  }
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
