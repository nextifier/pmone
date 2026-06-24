<template>
  <DialogResponsive
    :open="open"
    dialogMaxWidth="380px"
    @update:open="(v) => emit('update:open', v)"
  >
    <template #default>
      <div class="flex flex-col items-center gap-4 px-6 pb-10 md:py-7">
        <div class="text-center">
          <div class="page-title">QR Code</div>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">Scan at check-in</p>
        </div>

        <ClientOnly>
          <QRCode v-if="open && attendee.qr_token" :url="attendee.qr_token" class="w-full max-w-52" />
        </ClientOnly>

        <div class="w-full space-y-3">
          <div class="text-center">
            <div class="font-semibold tracking-tight">{{ attendee.name || "Unnamed" }}</div>
            <div v-if="contact" class="text-muted-foreground text-sm tracking-tight">
              {{ contact }}
            </div>
          </div>

          <dl class="bg-muted/40 divide-border divide-y rounded-xl border">
            <div
              v-if="ticketLabel"
              class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm"
            >
              <dt class="text-muted-foreground shrink-0 tracking-tight">Ticket</dt>
              <dd class="truncate text-right font-medium tracking-tight">{{ ticketLabel }}</dd>
            </div>
            <div
              v-if="attendee.order?.number"
              class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm"
            >
              <dt class="text-muted-foreground shrink-0 tracking-tight">Order</dt>
              <dd class="truncate text-right font-mono text-xs tracking-tight">
                {{ attendee.order.number }}
              </dd>
            </div>
            <div
              v-if="daySession"
              class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm"
            >
              <dt class="text-muted-foreground shrink-0 tracking-tight">Day / Session</dt>
              <dd class="truncate text-right font-medium tracking-tight">{{ daySession }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm">
              <dt class="text-muted-foreground shrink-0 tracking-tight">Check-in</dt>
              <dd>
                <Badge
                  v-if="checkInTooltip"
                  v-tippy="checkInTooltip"
                  :variant="attendee.is_checked_in ? 'success' : 'muted'"
                  with-icon
                  plain
                >
                  {{ attendee.is_checked_in ? "Checked in" : "Not checked in" }}
                </Badge>
                <Badge
                  v-else
                  :variant="attendee.is_checked_in ? 'success' : 'muted'"
                  with-icon
                  plain
                >
                  {{ attendee.is_checked_in ? "Checked in" : "Not checked in" }}
                </Badge>
              </dd>
            </div>
          </dl>
        </div>

        <div class="flex gap-2">
          <button
            @click="downloadQR('jpg')"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
          >
            <Icon name="hugeicons:jpg-01" class="size-4 shrink-0" />
            Download JPG
          </button>
          <button
            @click="downloadQR('svg')"
            class="bg-muted text-foreground hover:bg-border flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
          >
            <Icon name="hugeicons:svg-01" class="size-4 shrink-0" />
            Download SVG
          </button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import QRCode from "@/components/QRCode.vue";
import { Badge } from "@/components/ui/badge";
import { useQRCode } from "@/composables/useQRCode";
import { toast } from "vue-sonner";

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  attendee: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["update:open"]);

const { $dayjs } = useNuxtApp();

const contact = computed(() => props.attendee.email || props.attendee.phone || "");

const ticketLabel = computed(() => {
  const ticket = props.attendee.ticket;
  if (!ticket?.title) return "";
  return ticket.tier ? `${ticket.title} · ${ticket.tier}` : ticket.title;
});

function dayLabel(d) {
  const label = d?.label;
  let text;
  if (label && typeof label === "object") {
    text = label.en || label.id || Object.values(label)[0] || `Day ${d.day_number}`;
  } else {
    text = label || (d?.day_number ? `Day ${d.day_number}` : "");
  }
  return appendDayDate(text, d?.date);
}

const daySession = computed(() => {
  const a = props.attendee;
  const parts = [];
  if (a.day) parts.push(dayLabel(a.day));
  if (a.session) parts.push(a.session.label);
  return parts.join(" · ");
});

const checkInTooltip = computed(() => {
  const a = props.attendee;
  if (!a.is_checked_in || !a.checked_in_at) return "";
  const rel = $dayjs(a.checked_in_at).fromNow();
  const by = a.checked_in_by_name ? ` by ${a.checked_in_by_name}` : "";
  return `Checked in ${rel}${by}`;
});

const { downloadSVG, downloadJPG } = useQRCode();

const downloadQR = async (format) => {
  try {
    const token = props.attendee.qr_token;
    if (!token) return;
    const base = props.attendee.name || props.attendee.ulid || "attendee";
    if (format === "svg") {
      await downloadSVG(token, `QR-${base}.svg`);
    } else {
      await downloadJPG(token, `QR-${base}.png`);
    }
    toast.success("QR code downloaded!");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error downloading QR code:", err);
  }
};
</script>
