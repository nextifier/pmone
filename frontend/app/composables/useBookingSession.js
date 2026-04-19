import { ref, watch } from "vue";

const STORAGE_KEY = "pmone.booking.v1";

const defaultState = () => ({
  hotelId: null,
  eventSlug: null,
  hotelSlug: null,
  checkIn: null,
  checkOut: null,
  rooms: {},
  transfers: {},
  guest: {
    name: "",
    email: "",
    phone: "",
    identity_type: "nik",
    identity_number: "",
    nationality: "Indonesia",
    company: "",
    special_request: "",
  },
  acceptTerms: false,
});

const state = ref(defaultState());
const hydrated = ref(false);
let persisting = false;

function load() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (raw) {
      state.value = { ...defaultState(), ...JSON.parse(raw) };
    }
  } catch {
    state.value = defaultState();
  }
}

let persistTimer = null;
function persist(value) {
  if (persistTimer) clearTimeout(persistTimer);
  persistTimer = setTimeout(() => {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
      // storage quota exceeded or disabled — ignore
    }
  }, 300);
}

export function useBookingSession() {
  function hydrate() {
    if (!import.meta.client || hydrated.value) return;
    load();
    if (!persisting) {
      watch(state, (value) => persist(value), { deep: true, flush: "post" });
      persisting = true;
    }
    hydrated.value = true;
  }

  function set(patch) {
    state.value = { ...state.value, ...patch };
  }

  function setGuest(patch) {
    state.value = { ...state.value, guest: { ...state.value.guest, ...patch } };
  }

  function clear() {
    state.value = defaultState();
    if (import.meta.client) {
      sessionStorage.removeItem(STORAGE_KEY);
    }
  }

  function hasBookingSelection() {
    const { hotelId, checkIn, checkOut, rooms } = state.value;
    const totalRooms = Object.values(rooms || {}).reduce(
      (sum, qty) => sum + (Number(qty) || 0),
      0,
    );
    return Boolean(hotelId && checkIn && checkOut && totalRooms > 0);
  }

  return {
    state,
    hydrated,
    hydrate,
    set,
    setGuest,
    clear,
    hasBookingSelection,
  };
}
