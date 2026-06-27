<script setup lang="ts">
import { computed } from "vue";
import { useChatDemo, type ScriptTurn } from "@/composables/useChatDemo";
import {
  MessageScroller,
  MessageScrollerButton,
  MessageScrollerContent,
  MessageScrollerItem,
  MessageScrollerProvider,
  MessageScrollerViewport,
} from "@/components/ui/message-scroller";
import {
  Message,
  MessageAvatar,
  MessageContent,
  MessageFooter,
  MessageGroup,
  MessageHeader,
} from "@/components/ui/message";
import {
  Bubble,
  BubbleContent,
  BubbleGroup,
  BubbleReactions,
} from "@/components/ui/bubble";
import { Marker, MarkerContent, MarkerIcon } from "@/components/ui/marker";
import {
  Attachment,
  AttachmentAction,
  AttachmentActions,
  AttachmentContent,
  AttachmentDescription,
  AttachmentGroup,
  AttachmentMedia,
  AttachmentTitle,
  AttachmentTrigger,
} from "@/components/ui/attachment";
import MessageResponse from "@/components/ai-elements/message/MessageResponse.vue";
import ScrollerPlaygroundInner from "@/components/demo/ScrollerPlaygroundInner.vue";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Chat · MessageScroller" });

// Original demo transcript - the assistant replies use markdown so inline code
// and lists render through vue-stream-markdown.
const script: ScriptTurn[] = [
  {
    role: "user",
    text: "I'm building a chat and the scroll keeps jumping every time the assistant streams a reply. How do I stop that?",
  },
  {
    role: "assistant",
    text: "Wrap your message list in `MessageScroller` and turn on `autoScroll`. The viewport pins to the bottom as tokens arrive, so the latest text always lands in place.\n\nThe trick: it only auto-scrolls while the reader is already at the bottom. The moment they scroll up, auto-scroll backs off and their position is kept.",
  },
  {
    role: "user",
    text: "Okay, but sending a brand new message still feels jarring, like the whole thread reloads from the top.",
  },
  {
    role: "assistant",
    text: "Set `scrollAnchor` on the turn that should settle near the top instead of snapping to the very bottom. A few things you get for free:\n\n- a small peek of the previous exchange stays above the anchor\n- the reply starts in view, no disorienting jump\n- context is never lost mid-conversation",
  },
  {
    role: "user",
    text: "And if they've scrolled up to re-read an older answer? I don't want to yank them back down.",
  },
  {
    role: "assistant",
    text: "They stay put. Auto-scroll only runs while the viewport is pinned to the bottom, so scrolling up is a deliberate opt-out.\n\nWhen there's new content they haven't seen, `MessageScrollerButton` fades in. One tap jumps back to the newest message and re-engages auto-scroll.",
  },
  {
    role: "user",
    text: "Last one - does this work with assistive tech?",
  },
  {
    role: "assistant",
    text: "Yes. `MessageScrollerContent` renders as a `log` region with `aria-relevant=\"additions\"`, so new messages are announced as they stream. The scroll button is a real `<button>` with an sr-only label, dropped from the tab order once you're at the bottom.",
  },
];

const { messages, status, isBusy, nextMessageText, sendMessage, reset } =
  useChatDemo(script, 2);

// The currently streaming assistant message (last one while status is streaming).
const streamingId = computed(() =>
  status.value === "streaming"
    ? messages.value[messages.value.length - 1]?.id ?? null
    : null
);

function paragraphs(text: string): string[] {
  return text
    .split(/\n\s*\n/)
    .map((p) => p.trim())
    .filter(Boolean);
}

const menuItems = [
  { icon: "hugeicons:attachment-01", label: "Add Photos & Files" },
  { icon: "hugeicons:image-add-01", label: "Create Image" },
  { icon: "hugeicons:search-area", label: "Deep Research" },
  { icon: "hugeicons:globe-02", label: "Web Search" },
];

const bubbleVariants = [
  "default",
  "secondary",
  "muted",
  "tinted",
  "outline",
  "ghost",
  "destructive",
] as const;
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">
        Chat components
      </h1>
      <p
        class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg"
      >
        MessageScroller, Message, Bubble, Attachment, and Marker - ported from
        shadcn/ui to Vue. The demo streams a scripted reply, pins to the bottom
        while you're caught up, and anchors each new turn near the top.
      </p>
    </div>

    <!-- New Chat demo -->
    <MessageScrollerProvider :auto-scroll="true">
      <div class="relative mx-auto flex w-full max-w-sm flex-col gap-4">
        <Card class="flex h-[34rem] w-full flex-col gap-0 overflow-hidden py-0">
          <CardHeader class="gap-1 border-b px-4 py-3">
            <CardTitle class="text-base tracking-tighter">New Chat</CardTitle>
            <CardDescription>How can I help you today?</CardDescription>
            <CardAction>
              <Button
                v-tippy="'Reset'"
                variant="outline"
                size="icon"
                aria-label="Reset conversation"
                :disabled="isBusy"
                @click="reset"
              >
                <Icon name="hugeicons:refresh" />
              </Button>
            </CardAction>
          </CardHeader>

          <CardContent class="min-h-0 flex-1 overflow-hidden p-0">
            <Empty v-if="messages.length === 0" class="h-full">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Icon name="hugeicons:bubble-chat" class="size-6" />
                </EmptyMedia>
                <EmptyTitle>Morning!</EmptyTitle>
                <EmptyDescription>
                  What are we working on today? Press send to start a new
                  conversation.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>

            <MessageScroller v-else>
              <MessageScrollerViewport>
                <MessageScrollerContent :aria-busy="isBusy" class="gap-4 p-4">
                  <MessageScrollerItem
                    v-for="message in messages"
                    :key="message.id"
                    :message-id="message.id"
                    :scroll-anchor="message.role === 'user'"
                  >
                    <div class="message-reveal">
                      <Message :align="message.role === 'user' ? 'end' : 'start'">
                        <MessageContent>
                          <!-- User: plain text bubble -->
                          <Bubble
                            v-if="message.role === 'user'"
                            variant="muted"
                            align="end"
                          >
                            <BubbleContent class="space-y-2 leading-relaxed">
                              <p
                                v-for="(p, i) in paragraphs(message.text)"
                                :key="i"
                                class="whitespace-pre-wrap"
                              >
                                {{ p }}
                              </p>
                            </BubbleContent>
                          </Bubble>
                          <!-- Assistant: markdown via vue-stream-markdown -->
                          <Bubble v-else variant="ghost" align="start">
                            <BubbleContent class="leading-relaxed">
                              <ClientOnly>
                                <MessageResponse
                                  :content="message.text"
                                  :mode="message.id === streamingId ? 'streaming' : 'static'"
                                />
                                <template #fallback>
                                  <p
                                    v-for="(p, i) in paragraphs(message.text)"
                                    :key="i"
                                    class="whitespace-pre-wrap"
                                  >
                                    {{ p }}
                                  </p>
                                </template>
                              </ClientOnly>
                            </BubbleContent>
                          </Bubble>
                        </MessageContent>
                      </Message>
                    </div>
                  </MessageScrollerItem>
                </MessageScrollerContent>
              </MessageScrollerViewport>
              <MessageScrollerButton />
            </MessageScroller>
          </CardContent>

          <CardFooter class="flex-col gap-2 border-t px-4 py-3">
            <form class="w-full" @submit.prevent="sendMessage">
              <InputGroup>
                <div class="h-14 w-full px-3 py-2.5">
                  <span
                    class="line-clamp-2 text-sm tracking-tight opacity-60 data-[status=ready]:opacity-100"
                    :data-status="status"
                  >
                    <template v-if="nextMessageText">{{ nextMessageText }}</template>
                    <span v-else class="text-muted-foreground">
                      No messages queued. Reset the conversation.
                    </span>
                  </span>
                </div>
                <InputGroupAddon align="block-end" class="pt-1">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <InputGroupButton
                        aria-label="Add files"
                        type="button"
                        size="icon-sm"
                        variant="outline"
                      >
                        <Icon name="hugeicons:add-01" />
                      </InputGroupButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" side="top" class="w-44">
                      <DropdownMenuItem
                        v-for="item in menuItems"
                        :key="item.label"
                      >
                        <Icon :name="item.icon" />
                        {{ item.label }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                  <InputGroupButton
                    type="submit"
                    variant="default"
                    size="icon-sm"
                    class="ml-auto"
                    :disabled="!nextMessageText || isBusy"
                  >
                    <Icon name="hugeicons:arrow-up-02" />
                    <span class="sr-only">Send</span>
                  </InputGroupButton>
                </InputGroupAddon>
              </InputGroup>
            </form>
          </CardFooter>
        </Card>
        <p class="text-muted-foreground px-0.5 text-center text-xs tracking-tight">
          Demo is read only. Press send to send messages.
        </p>
      </div>
    </MessageScrollerProvider>

    <!-- Component showcase -->
    <div class="mx-auto mt-16 grid w-full max-w-3xl gap-12">
      <!-- Message rows -->
      <section class="space-y-4">
        <h2 class="text-lg font-semibold tracking-tighter">Message rows</h2>
        <MessageGroup class="gap-4 rounded-xl border p-4">
          <Message align="start">
            <MessageAvatar>
              <Icon name="hugeicons:ai-chat-02" class="size-4" />
            </MessageAvatar>
            <MessageContent>
              <MessageHeader>
                <span class="font-medium text-foreground">Assistant</span>
                <span>10:24</span>
              </MessageHeader>
              <Bubble variant="secondary">
                <BubbleContent>Here's the layout with avatar, header, and footer.</BubbleContent>
              </Bubble>
              <MessageFooter>Delivered</MessageFooter>
            </MessageContent>
          </Message>

          <Message align="end">
            <MessageAvatar>
              <span class="text-xs font-medium">SC</span>
            </MessageAvatar>
            <MessageContent>
              <MessageHeader class="justify-end">
                <span>10:25</span>
              </MessageHeader>
              <Bubble variant="default" align="end">
                <BubbleContent>Looks great - reversed for the active user.</BubbleContent>
              </Bubble>
              <MessageFooter>Read</MessageFooter>
            </MessageContent>
          </Message>
        </MessageGroup>
      </section>

      <!-- Bubble variants + group + reactions -->
      <section class="space-y-4">
        <h2 class="text-lg font-semibold tracking-tighter">Bubble</h2>
        <div class="grid gap-6 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <Bubble
              v-for="variant in bubbleVariants"
              :key="variant"
              :variant="variant"
            >
              <BubbleContent>{{ variant }} bubble surface</BubbleContent>
            </Bubble>
          </div>
          <div class="space-y-6">
            <BubbleGroup>
              <Bubble variant="secondary">
                <BubbleContent>Grouped bubbles</BubbleContent>
              </Bubble>
              <Bubble variant="secondary">
                <BubbleContent>stack tightly together</BubbleContent>
              </Bubble>
            </BubbleGroup>
            <div class="pt-2">
              <Bubble variant="default" class="mb-6">
                <BubbleContent>With reactions</BubbleContent>
                <BubbleReactions side="bottom" align="end">
                  <span
                    class="flex items-center gap-1 rounded-full border bg-background px-1.5 py-0.5 text-xs shadow-sm"
                  >
                    👍 2
                  </span>
                </BubbleReactions>
              </Bubble>
            </div>
          </div>
        </div>
      </section>

      <!-- Marker -->
      <section class="space-y-4">
        <h2 class="text-lg font-semibold tracking-tighter">Marker</h2>
        <div class="space-y-3 rounded-xl border p-4">
          <Marker><MarkerContent>Today</MarkerContent></Marker>
          <Marker variant="separator"><MarkerContent>Yesterday</MarkerContent></Marker>
          <Marker variant="border">
            <MarkerIcon><Icon name="hugeicons:loading-03" class="animate-spin" /></MarkerIcon>
            <MarkerContent class="shimmer">Assistant is thinking…</MarkerContent>
          </Marker>
        </div>
      </section>

      <!-- Attachment -->
      <section class="space-y-4">
        <h2 class="text-lg font-semibold tracking-tighter">Attachment</h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <!-- Full-card trigger, actions stay separately clickable -->
          <Attachment class="cursor-pointer">
            <AttachmentTrigger aria-label="Open proposal.pdf" />
            <AttachmentMedia variant="icon">
              <Icon name="hugeicons:pdf-01" />
            </AttachmentMedia>
            <AttachmentContent>
              <AttachmentTitle>proposal.pdf</AttachmentTitle>
              <AttachmentDescription>2.4 MB</AttachmentDescription>
            </AttachmentContent>
            <AttachmentActions>
              <AttachmentAction aria-label="Download">
                <Icon name="hugeicons:download-01" />
              </AttachmentAction>
            </AttachmentActions>
          </Attachment>

          <Attachment state="uploading">
            <AttachmentMedia variant="icon">
              <Icon name="hugeicons:image-01" />
            </AttachmentMedia>
            <AttachmentContent>
              <AttachmentTitle>cover-art.png</AttachmentTitle>
              <AttachmentDescription>Uploading…</AttachmentDescription>
            </AttachmentContent>
          </Attachment>

          <Attachment state="error" size="sm">
            <AttachmentMedia variant="icon">
              <Icon name="hugeicons:alert-02" />
            </AttachmentMedia>
            <AttachmentContent>
              <AttachmentTitle>broken.zip</AttachmentTitle>
              <AttachmentDescription>Upload failed</AttachmentDescription>
            </AttachmentContent>
          </Attachment>

          <Attachment size="xs" orientation="vertical">
            <AttachmentMedia variant="icon">
              <Icon name="hugeicons:music-note-01" />
            </AttachmentMedia>
            <AttachmentContent>
              <AttachmentTitle>track.mp3</AttachmentTitle>
              <AttachmentDescription>3:42</AttachmentDescription>
            </AttachmentContent>
          </Attachment>
        </div>

        <p class="text-muted-foreground text-sm tracking-tight">
          Horizontal group with edge fade (scroll sideways):
        </p>
        <AttachmentGroup class="pb-1">
          <Attachment
            v-for="n in 8"
            :key="n"
            size="sm"
            class="w-48"
          >
            <AttachmentMedia variant="icon">
              <Icon name="hugeicons:file-01" />
            </AttachmentMedia>
            <AttachmentContent>
              <AttachmentTitle>file-{{ n }}.txt</AttachmentTitle>
              <AttachmentDescription>{{ n * 12 }} KB</AttachmentDescription>
            </AttachmentContent>
          </Attachment>
        </AttachmentGroup>
      </section>

      <!-- MessageScroller playground -->
      <section class="space-y-4">
        <h2 class="text-lg font-semibold tracking-tighter">
          MessageScroller playground
        </h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          Jump-to-message, scroll controls, prepend-preserve, and live
          visibility tracking (defaultScrollPosition = start).
        </p>
        <MessageScrollerProvider default-scroll-position="start">
          <ScrollerPlaygroundInner />
        </MessageScrollerProvider>
      </section>
    </div>
  </div>
</template>

<style scoped>
.message-reveal {
  animation: message-in 0.26s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes message-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .message-reveal {
    animation: none;
  }
}
</style>
