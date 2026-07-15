<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/emails" force-destination />

      <div v-if="message" class="flex flex-col gap-y-2">
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
          <h1 class="page-title break-all">{{ primaryRecipient }}</h1>
          <Badge :variant="statusVariant(message.status)" plain>{{ message.status_label }}</Badge>
          <Badge
            v-if="message.bounce_type"
            :variant="message.bounce_type === 'permanent' ? 'destructive' : 'warning'"
            plain
          >
            {{ message.bounce_type === "permanent" ? "Permanent" : "Transient" }}
          </Badge>
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
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-x-2">
            <Icon name="hugeicons:time-schedule" class="text-muted-foreground size-4 shrink-0" />
            <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Email events</h2>
          </div>
          <span v-if="timelineIsDerived" class="text-muted-foreground text-xs tracking-tight">
            Derived from status · exact times and per-event detail arrive with the webhook
          </span>
        </div>

        <ul class="space-y-2">
          <li
            v-for="event in timeline"
            :key="event.id"
            class="bg-card flex flex-col gap-y-1 rounded-lg border p-3"
          >
            <div class="flex flex-wrap items-center gap-2">
              <Badge :variant="statusVariant(event.type)" plain>{{ event.label }}</Badge>
              <Badge
                v-if="event.bounceType"
                :variant="event.bounceType === 'permanent' ? 'destructive' : 'warning'"
                plain
              >
                {{ event.bounceType === "permanent" ? "Permanent" : "Transient" }}
              </Badge>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ event.at ? $dayjs(event.at).format("D MMM YYYY, HH:mm:ss") : "—" }}
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
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-x-2">
            <Icon name="hugeicons:mail-open-01" class="text-muted-foreground size-4 shrink-0" />
            <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Body</h2>
          </div>

          <div v-if="content?.available" class="flex items-center gap-1.5">
            <!-- Simulated dark rendering, defaulting to the app's colour mode.
                 The email itself has no dark styles, so this mirrors how a client
                 like Outlook force-darkens it, not a change to the sent email. -->
            <div
              v-if="bodyTab === 'preview' && content.html"
              class="flex items-center rounded-md border p-0.5 text-xs tracking-tight"
            >
              <button
                class="rounded-[5px] px-2 py-1"
                :class="!previewDark ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                @click="previewDark = false"
              >
                Light
              </button>
              <button
                class="rounded-[5px] px-2 py-1"
                :class="previewDark ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                @click="previewDark = true"
              >
                Dark
              </button>
            </div>

            <button
              :disabled="contentPending"
              title="Re-fetch this email's body from Resend"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-xs tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
              @click="loadContent(true)"
            >
              <Spinner v-if="contentPending" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:refresh" class="size-4 shrink-0" />
              <span class="hidden sm:inline">Refresh</span>
            </button>
          </div>
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
              :key="previewDark ? 'dark' : 'light'"
              sandbox="allow-same-origin"
              :srcdoc="previewSrcdoc"
              title="Email preview"
              class="w-full rounded-lg border"
              :class="previewDark ? 'bg-neutral-950' : 'bg-white'"
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

// Real webhook events when present; otherwise a minimal Sent -> [status] timeline
// derived from the message so the section is never empty for a delivered email.
const timeline = computed(() => {
  const m = message.value;
  if (!m) return [];

  if (m.events?.length) {
    return m.events.map((e) => ({
      id: e.id,
      type: e.type,
      label: e.type_label,
      at: e.occurred_at,
      recipient: e.recipient,
      diagnostic: e.diagnostic,
    }));
  }

  const items = [{ id: "sent", type: "send", label: "Sent", at: m.sent_at }];

  if (m.status && m.status !== "send") {
    items.push({
      id: "status",
      type: m.status,
      label: m.status_label,
      at: m.last_event_at || m.sent_at,
      bounceType: m.status === "bounce" ? m.bounce_type : null,
    });
  }

  return items;
});

const timelineIsDerived = computed(() => !!message.value && !message.value.events?.length);

/* ---------------------------------------------------------------------- body */

const bodyTab = ref("preview");
const content = ref(null);
const contentPending = ref(false);

// Preview theme, defaulting to the app's colour mode (set on mount). The sent
// email has no dark styles of its own, so "Dark" simulates the force-darkening
// a client like Outlook applies rather than altering the real email.
const colorMode = useColorMode();
const previewDark = ref(false);

// The iframe is a separate document, so the app's custom scrollbar styles do not
// reach inside it. Inject a matching thin scrollbar. The grey is ~mid-tone, which
// is stable under the dark-mode invert filter (its inverse is still the same grey).
const BASE_PREVIEW_STYLE =
  '<style>::-webkit-scrollbar{width:6px;height:6px}' +
  '::-webkit-scrollbar-track{background:transparent}' +
  '::-webkit-scrollbar-thumb{background:rgba(128,128,128,.5);border-radius:6px}' +
  'html{scrollbar-width:thin;scrollbar-color:rgba(128,128,128,.5) transparent}</style>';

// The backdrop is set to a light colour on purpose: the invert filter flips it,
// so #f5f5f5 renders as ~#0a0a0a and fills any area the email does not cover
// (e.g. the padding below its content) with dark instead of an inverted-white
// strip. #0a0a0a here would invert to white and show as a bright band. Images are
// inverted a second time so photos and logos keep their real colours.
const DARK_PREVIEW_STYLE =
  '<style>html,body{background:#f5f5f5!important}' +
  'html{filter:invert(1) hue-rotate(180deg)}' +
  'img,video,[style*="background-image"]{filter:invert(1) hue-rotate(180deg)}</style>';

const previewSrcdoc = computed(() => {
  const html = content.value?.html;
  if (!html) return html;

  const inject = BASE_PREVIEW_STYLE + (previewDark.value ? DARK_PREVIEW_STYLE : "");

  return /<\/head>/i.test(html)
    ? html.replace(/<\/head>/i, `${inject}</head>`)
    : `${inject}${html}`;
});

// The preview iframe grows to fit its content so the whole page scrolls, rather
// than trapping a scroll inside a fixed-height frame (which does not respond to
// touch on mobile). Reading scrollHeight needs allow-same-origin; scripts stay
// disabled, so the email HTML still cannot run anything.
const previewFrame = ref(null);
const previewHeight = ref("600px");

const resizePreview = () => {
  const measure = () => {
    const doc = previewFrame.value?.contentDocument;
    // body.scrollHeight is the email's own content height. documentElement's is
    // floored to the iframe's current height, so once the frame is tall it stays
    // tall and leaves a large empty band below a shorter email.
    const height = doc?.body?.scrollHeight || doc?.documentElement?.scrollHeight;
    if (height) {
      // +4 covers the iframe's own border box and sub-pixel rounding so the
      // content never triggers a thin internal scrollbar; too small to read as a gap.
      previewHeight.value = `${height + 4}px`;
    }
  };

  measure();
  // Re-measure once late-loading images and fonts have settled.
  setTimeout(measure, 300);
};

// Fetched client-side only: the body lives at Resend, not in our tables, and a
// lazy fetch that resolves only in the client payload would shift Vue ids and
// break hydration if run during SSR. `fresh` bypasses the server-side cache so
// a body that was unavailable (or has changed) can be re-pulled on demand.
const loadContent = async (fresh = false) => {
  contentPending.value = true;

  try {
    const client = useSanctumClient();
    const res = await client(
      `/api/emails/messages/${messageId.value}/content${fresh ? "?fresh=1" : ""}`,
    );
    content.value = res.data;
  } catch {
    content.value = { available: false };
  } finally {
    contentPending.value = false;
  }
};

onMounted(() => {
  previewDark.value = colorMode.value === "dark";
  loadContent();
});
</script>
