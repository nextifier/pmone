<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/emails" force-destination />

      <div v-if="message" class="flex flex-col gap-y-2">
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
          <h1 class="page-title break-all">{{ primaryRecipient }}</h1>
          <Badge :variant="statusVariant(message.status)" plain>{{ message.status_label }}</Badge>
        </div>
        <p class="page-description">{{ message.subject || "(no subject)" }}</p>
      </div>
    </div>

    <div v-if="pending" class="space-y-3">
      <Skeleton class="h-28 w-full rounded-xl" />
      <Skeleton class="h-40 w-full rounded-xl" />
    </div>

    <template v-else-if="message">
      <!-- Metadata -->
      <div class="grid grid-cols-1 gap-x-2 gap-y-6 rounded-xl border p-4 sm:grid-cols-2 sm:p-5">
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">From</p>
          <p class="text-sm tracking-tight break-all">{{ message.from_address }}</p>
        </div>
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">To</p>
          <p class="text-sm tracking-tight break-all">{{ message.recipients.join(", ") }}</p>
        </div>
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Subject</p>
          <p class="text-sm tracking-tight">{{ message.subject || "(no subject)" }}</p>
        </div>
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">ID</p>
          <div class="flex items-center gap-x-1.5">
            <p class="text-sm tracking-tight break-all">{{ message.message_id }}</p>
            <ButtonCopy :text="message.message_id" />
          </div>
        </div>

        <div v-if="content?.cc?.length" class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">CC</p>
          <p class="text-sm tracking-tight break-all">{{ content.cc.join(", ") }}</p>
        </div>
        <div v-if="content?.bcc?.length" class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">BCC</p>
          <p class="text-sm tracking-tight break-all">{{ content.bcc.join(", ") }}</p>
        </div>
        <div v-if="content?.reply_to?.length" class="space-y-1">
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Reply-To</p>
          <p class="text-sm tracking-tight break-all">{{ content.reply_to.join(", ") }}</p>
        </div>
      </div>

      <!-- Email events -->
      <section class="space-y-3">
        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:time-schedule" class="text-muted-foreground size-4 shrink-0" />
          <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Email events</h2>
        </div>

        <Empty v-if="!message.events?.length" class="border-dashed">
          <EmptyHeader>
            <EmptyMedia variant="stacked">
              <Icon name="hugeicons:inbox" class="size-5" />
            </EmptyMedia>
            <EmptyTitle>No events yet</EmptyTitle>
            <EmptyDescription>
              Nothing has been reported for this email beyond acceptance. Delivery events arrive once
              the Resend webhook is configured.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <ul v-else class="space-y-2">
          <li
            v-for="event in message.events"
            :key="event.id"
            class="bg-card flex flex-col gap-y-1 rounded-lg border p-3"
          >
            <div class="flex flex-wrap items-center gap-2">
              <Badge :variant="statusVariant(event.type)" plain>{{ event.type_label }}</Badge>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ $dayjs(event.occurred_at).format("D MMM YYYY, HH:mm:ss") }}
              </span>
            </div>
            <div v-if="event.recipient" class="text-sm tracking-tight break-all">
              {{ event.recipient }}
            </div>
            <div
              v-if="event.diagnostic"
              class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >
              {{ event.diagnostic }}
            </div>
          </li>
        </ul>
      </section>

      <!-- Body -->
      <section class="space-y-3">
        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:mail-open-01" class="text-muted-foreground size-4 shrink-0" />
          <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Body</h2>
        </div>

        <div v-if="contentPending" class="rounded-xl border p-4 sm:p-5">
          <Skeleton class="h-96 w-full rounded-lg" />
        </div>

        <Empty v-else-if="!content?.available" class="border-dashed">
          <EmptyHeader>
            <EmptyMedia variant="stacked">
              <Icon name="hugeicons:file-not-found" class="size-5" />
            </EmptyMedia>
            <EmptyTitle>Content unavailable</EmptyTitle>
            <EmptyDescription>
              The body for this email is no longer available from Resend.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <Tabs v-else v-model="bodyTab" variant="segmented" class="flex flex-col gap-4">
          <TabsList>
            <TabsIndicator />
            <TabsTrigger value="preview">Preview</TabsTrigger>
            <TabsTrigger value="text">Plain Text</TabsTrigger>
            <TabsTrigger value="html">HTML</TabsTrigger>
          </TabsList>

          <TabsContent value="preview">
            <iframe
              v-if="content.html"
              ref="previewFrame"
              sandbox="allow-same-origin"
              :srcdoc="content.html"
              title="Email preview"
              class="w-full rounded-lg border bg-white"
              :style="{ height: previewHeight }"
              @load="resizePreview"
            />
            <p v-else class="text-muted-foreground rounded-lg border p-4 text-sm tracking-tight">
              This email has no HTML version.
            </p>
          </TabsContent>

          <TabsContent value="text">
            <pre
              v-if="content.text"
              class="overflow-x-auto rounded-lg border p-4 text-sm tracking-tight whitespace-pre-wrap"
              >{{ content.text }}</pre
            >
            <p v-else class="text-muted-foreground rounded-lg border p-4 text-sm tracking-tight">
              This email has no plain text version.
            </p>
          </TabsContent>

          <TabsContent value="html">
            <pre
              v-if="content.html"
              class="overflow-x-auto rounded-lg border p-4 font-mono text-xs tracking-tight whitespace-pre-wrap"
              >{{ content.html }}</pre
            >
            <p v-else class="text-muted-foreground rounded-lg border p-4 text-sm tracking-tight">
              This email has no HTML version.
            </p>
          </TabsContent>
        </Tabs>
      </section>
    </template>
  </div>
</template>

<script setup>
import ButtonCopy from "@/components/ui/button-copy/ButtonCopy.vue";
import { Badge } from "@/components/ui/badge";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["emails.view"],
  layout: "app",
});

defineOptions({ name: "email-detail" });

const route = useRoute();
const { $dayjs } = useNuxtApp();

const messageId = computed(() => route.params.id);

const { data: response, pending } = await useLazySanctumFetch(
  () => `/api/emails/messages/${messageId.value}`,
  { key: () => `email-${messageId.value}` },
);

const message = computed(() => response.value?.data ?? null);
const primaryRecipient = computed(() => message.value?.recipients?.[0] ?? "Email");

usePageMeta(null, {
  title: computed(() =>
    message.value ? `${message.value.subject || primaryRecipient.value} · Emails` : "Email",
  ),
});

const statusVariant = (status) =>
  ({
    send: "muted",
    delivery: "success",
    open: "info",
    click: "info",
    delivery_delay: "warning",
    reject: "destructive",
    bounce: "destructive",
    complaint: "destructive",
  })[status] ?? "muted";

/* ---------------------------------------------------------------------- body */

const bodyTab = ref("preview");
const content = ref(null);
const contentPending = ref(false);

// The preview iframe grows to fit its content so the whole page scrolls, rather
// than trapping a scroll inside a fixed-height frame (which does not respond to
// touch on mobile). Reading scrollHeight needs allow-same-origin; scripts stay
// disabled, so the email HTML still cannot run anything.
const previewFrame = ref(null);
const previewHeight = ref("600px");

const resizePreview = () => {
  const measure = () => {
    const doc = previewFrame.value?.contentDocument;
    const height = doc?.documentElement?.scrollHeight || doc?.body?.scrollHeight;
    if (height) {
      previewHeight.value = `${height + 24}px`;
    }
  };

  measure();
  // Re-measure once late-loading images and fonts have settled.
  setTimeout(measure, 300);
};

// Fetched client-side only: the body lives at Resend, not in our tables, and a
// lazy fetch that resolves only in the client payload would shift Vue ids and
// break hydration if run during SSR.
onMounted(async () => {
  contentPending.value = true;

  try {
    const client = useSanctumClient();
    const res = await client(`/api/emails/messages/${messageId.value}/content`);
    content.value = res.data;
  } catch {
    content.value = { available: false };
  } finally {
    contentPending.value = false;
  }
});
</script>
