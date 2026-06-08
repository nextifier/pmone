const STORAGE_KEY = "pmone.booking.v3";
const TTL_MS = 24 * 60 * 60 * 1000;

const defaultGuest = () => ({
  name: "",
  email: "",
  phone: "",
  identity_type: "nik",
  identity_number: "",
  nationality: "Indonesia",
  company: "",
  special_request: "",
});

const defaultState = () => ({
  hotelId: null,
  eventId: null,
  eventSlug: null,
  hotelSlug: null,
  checkIn: null,
  checkOut: null,
  guestCount: 1,
  rooms: {},
  roomNotes: {},
  transfers: {},
  transferNotes: {},
  guest: defaultGuest(),
  acceptTerms: false,
  currentStep: 1,
  promoCode: null,
  promoPreview: null,
});

function loadFromStorage() {
  if (!import.meta.client) return null;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed?.savedAt || Date.now() - parsed.savedAt > TTL_MS) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
    return parsed.data;
  } catch {
    return null;
  }
}

function saveToStorage(data) {
  if (!import.meta.client) return;
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ savedAt: Date.now(), data }));
  } catch {
    // quota exceeded or disabled — ignore
  }
}

let persistTimer = null;
function persistDebounced(state) {
  if (persistTimer) clearTimeout(persistTimer);
  persistTimer = setTimeout(() => saveToStorage(state), 300);
}

export const useBookingStore = defineStore("booking", {
  state: () => ({
    ...defaultState(),
    hydrated: false,
    pricingAggregate: {},
    pricingLoading: false,
    availability: {},
    roomPreviews: {},
    checkingAvailability: false,
    lastAvailabilityCheck: null,
  }),

  getters: {
    nights(state) {
      if (!state.checkIn || !state.checkOut) return 0;
      const ci = new Date(state.checkIn);
      const co = new Date(state.checkOut);
      const ms = co.getTime() - ci.getTime();
      return Math.max(0, Math.round(ms / 86400000));
    },
    totalRoomsSelected(state) {
      return Object.values(state.rooms).reduce((sum, qty) => sum + (Number(qty) || 0), 0);
    },
    hasBookingSelection(state) {
      return !!(state.hotelId && state.checkIn && state.checkOut && this.totalRoomsSelected > 0);
    },
    canProceedStep1() {
      return this.nights > 0 && this.totalRoomsSelected > 0;
    },
    canProceedStep2() {
      return this.canProceedStep1;
    },
    canProceedStep3(state) {
      const g = state.guest;
      return (
        this.canProceedStep1 &&
        !!g.name?.trim() &&
        !!g.email?.trim() &&
        !!g.phone?.trim() &&
        !!g.identity_number?.trim()
      );
    },
    canProceedStep4(state) {
      return this.canProceedStep3 && state.acceptTerms;
    },
  },

  actions: {
    hydrate() {
      if (this.hydrated) return;
      const loaded = loadFromStorage();
      if (loaded) {
        Object.assign(this, {
          hotelId: loaded.hotelId ?? null,
          eventId: loaded.eventId ?? null,
          eventSlug: loaded.eventSlug ?? null,
          hotelSlug: loaded.hotelSlug ?? null,
          checkIn: loaded.checkIn ?? null,
          checkOut: loaded.checkOut ?? null,
          guestCount: loaded.guestCount ?? 1,
          rooms: { ...(loaded.rooms || {}) },
          roomNotes: { ...(loaded.roomNotes || {}) },
          transfers: { ...(loaded.transfers || {}) },
          transferNotes: { ...(loaded.transferNotes || {}) },
          guest: { ...defaultGuest(), ...(loaded.guest || {}) },
          acceptTerms: !!loaded.acceptTerms,
          currentStep: loaded.currentStep ?? 1,
          promoCode: loaded.promoCode ?? null,
          promoPreview: loaded.promoPreview ?? null,
        });
      }
      this.hydrated = true;
      this.$subscribe((_, state) => {
        const snapshot = {
          hotelId: state.hotelId,
          eventId: state.eventId,
          eventSlug: state.eventSlug,
          hotelSlug: state.hotelSlug,
          checkIn: state.checkIn,
          checkOut: state.checkOut,
          guestCount: state.guestCount,
          rooms: { ...state.rooms },
          roomNotes: { ...state.roomNotes },
          transfers: { ...state.transfers },
          transferNotes: { ...state.transferNotes },
          guest: { ...state.guest },
          acceptTerms: state.acceptTerms,
          currentStep: state.currentStep,
          promoCode: state.promoCode,
          promoPreview: state.promoPreview,
        };
        persistDebounced(snapshot);
      });
    },

    setHotelContext({ hotelId, eventId, eventSlug, hotelSlug }) {
      const changed =
        this.hotelId !== hotelId ||
        this.eventId !== eventId ||
        this.eventSlug !== eventSlug ||
        this.hotelSlug !== hotelSlug;
      if (changed) {
        this.hotelId = hotelId;
        this.eventId = eventId ?? null;
        this.eventSlug = eventSlug;
        this.hotelSlug = hotelSlug;
        this.rooms = {};
        this.roomNotes = {};
        this.transfers = {};
        this.transferNotes = {};
        this.pricingAggregate = {};
        this.availability = {};
        this.roomPreviews = {};
        this.currentStep = 1;
      }
    },

    setDates({ checkIn, checkOut }) {
      this.checkIn = checkIn;
      this.checkOut = checkOut;
    },

    setRoomQty(roomId, qty) {
      const n = Math.max(0, Number(qty) || 0);
      if (n === 0) {
        const next = { ...this.rooms };
        delete next[roomId];
        this.rooms = next;
        const nextNotes = { ...this.roomNotes };
        delete nextNotes[roomId];
        this.roomNotes = nextNotes;
      } else {
        this.rooms = { ...this.rooms, [roomId]: n };
      }
    },

    setRoomNotes(roomId, notes) {
      const value = typeof notes === "string" ? notes : "";
      if (!value) {
        const next = { ...this.roomNotes };
        delete next[roomId];
        this.roomNotes = next;
      } else {
        this.roomNotes = { ...this.roomNotes, [roomId]: value };
      }
    },

    toggleTransfer(transferId, on) {
      if (on) {
        this.transfers = { ...this.transfers, [transferId]: true };
      } else {
        const next = { ...this.transfers };
        delete next[transferId];
        this.transfers = next;
        const nextNotes = { ...this.transferNotes };
        delete nextNotes[transferId];
        this.transferNotes = nextNotes;
      }
    },

    setTransferNotes(transferId, notes) {
      const value = typeof notes === "string" ? notes : "";
      if (!value) {
        const next = { ...this.transferNotes };
        delete next[transferId];
        this.transferNotes = next;
      } else {
        this.transferNotes = { ...this.transferNotes, [transferId]: value };
      }
    },

    setGuest(patch) {
      this.guest = { ...this.guest, ...patch };
    },

    setPromo(code, preview = null) {
      this.promoCode = code ? String(code).trim().toUpperCase() : null;
      this.promoPreview = preview;
    },

    clearPromo() {
      this.promoCode = null;
      this.promoPreview = null;
    },

    setAcceptTerms(v) {
      this.acceptTerms = !!v;
    },

    setStep(step) {
      const n = Math.max(1, Math.min(4, Number(step) || 1));
      this.currentStep = n;
    },

    setGuestCount(n) {
      this.guestCount = Math.max(1, Math.min(10, Number(n) || 1));
    },

    reset() {
      Object.assign(this, defaultState());
      this.pricingAggregate = {};
      this.availability = {};
      this.roomPreviews = {};
      if (import.meta.client) {
        try {
          localStorage.removeItem(STORAGE_KEY);
        } catch {
          // ignore
        }
      }
    },

    async fetchPricingAggregate({ eventSlug, hotelSlug, startDate, endDate }) {
      this.pricingLoading = true;
      try {
        const res = await $fetch(
          `/api/hotels/${eventSlug}/${hotelSlug}/daily-availability-aggregate`,
          { query: { start_date: startDate, end_date: endDate } }
        );
        const merged = { ...this.pricingAggregate };
        for (const day of res?.data ?? []) {
          merged[day.date] = {
            rate: day.min_rate,
            min_rate: day.min_rate,
            max_rate: day.max_rate,
            available: day.total_available,
            rooms_count: day.rooms_count,
          };
        }
        this.pricingAggregate = merged;
      } finally {
        this.pricingLoading = false;
      }
    },

    async fetchRoomAvailability({ hotelId, rooms, checkIn, checkOut }) {
      if (!hotelId || !checkIn || !checkOut || !rooms?.length) return;
      this.checkingAvailability = true;
      try {
        const results = await Promise.all(
          rooms.map((room) =>
            $fetch("/api/hotels/availability", {
              method: "POST",
              body: {
                hotel_id: hotelId,
                event_slug: this.eventSlug,
                room_type_id: room.id,
                check_in_date: checkIn,
                check_out_date: checkOut,
                qty: 1,
              },
            })
              .then((res) => ({
                id: room.id,
                available: Number(res?.data?.available ?? 0),
                subtotal: Number(res?.data?.subtotal ?? 0),
                pricing_type: res?.data?.pricing_type ?? "flat",
                daily_breakdown: res?.data?.daily_breakdown ?? [],
                error: null,
              }))
              .catch((err) => ({
                id: room.id,
                available: null,
                subtotal: 0,
                pricing_type: "flat",
                daily_breakdown: [],
                error: err?.data?.message || "Unable to price this room",
              }))
          )
        );
        const availability = {};
        const previews = {};
        const errored = {};
        for (const r of results) {
          availability[r.id] = r.available;
          errored[r.id] = !!r.error;
          previews[r.id] = {
            subtotal: r.subtotal,
            pricing_type: r.pricing_type,
            daily_breakdown: r.daily_breakdown,
            error: r.error,
          };
        }
        this.availability = availability;
        this.roomPreviews = previews;
        this.lastAvailabilityCheck = Date.now();

        // Clamp selected quantities to remaining availability. A failed probe
        // (errored) means the room can't be priced/booked for these dates, so
        // drop any selected qty rather than carry an unbookable room forward.
        const next = { ...this.rooms };
        let changed = false;
        for (const [id, qty] of Object.entries(next)) {
          if (errored[id]) {
            delete next[id];
            changed = true;
            continue;
          }
          const avail = availability[id];
          if (avail != null && Number(qty) > avail) {
            next[id] = Math.max(0, avail);
            changed = true;
          }
        }
        if (changed) this.rooms = next;
      } finally {
        this.checkingAvailability = false;
      }
    },
  },
});
