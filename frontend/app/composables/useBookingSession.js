import { effectScope, ref, watch } from 'vue'

const STORAGE_KEY = 'pmone.booking.v2'
const TTL_MS = 24 * 60 * 60 * 1000

const defaultState = () => ({
  hotelId: null,
  eventSlug: null,
  hotelSlug: null,
  checkIn: null,
  checkOut: null,
  rooms: {},
  transfers: {},
  guest: {
    name: '',
    email: '',
    phone: '',
    identity_type: 'nik',
    identity_number: '',
    nationality: 'Indonesia',
    company: '',
    special_request: ''
  },
  acceptTerms: false
})

const state = ref(defaultState())
const hydrated = ref(false)
let persisting = false

function clearStorage() {
  try {
    localStorage.removeItem(STORAGE_KEY)
    // Legacy cleanup for v1 session storage
    sessionStorage.removeItem('pmone.booking.v1')
  } catch {
    // storage disabled — ignore
  }
}

function load() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return
    const parsed = JSON.parse(raw)
    if (!parsed?.savedAt || Date.now() - parsed.savedAt > TTL_MS) {
      clearStorage()
      return
    }
    state.value = { ...defaultState(), ...(parsed.data || {}) }
  } catch {
    state.value = defaultState()
  }
}

let persistTimer = null
function persist(value) {
  if (persistTimer) clearTimeout(persistTimer)
  persistTimer = setTimeout(() => {
    try {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({ savedAt: Date.now(), data: value })
      )
    } catch {
      // storage quota exceeded or disabled — ignore
    }
  }, 300)
}

const persistScope = effectScope(true)

export function useBookingSession() {
  function hydrate() {
    if (!import.meta.client || hydrated.value) return
    load()
    if (!persisting) {
      persistScope.run(() => {
        watch(state, (value) => persist(value), { deep: true, flush: 'post' })
      })
      persisting = true
    }
    hydrated.value = true
  }

  function set(patch) {
    state.value = { ...state.value, ...patch }
  }

  function setGuest(patch) {
    state.value = { ...state.value, guest: { ...state.value.guest, ...patch } }
  }

  function clear() {
    state.value = defaultState()
    if (import.meta.client) {
      clearStorage()
    }
  }

  function hasBookingSelection() {
    const { hotelId, checkIn, checkOut, rooms } = state.value
    const totalRooms = Object.values(rooms || {}).reduce((sum, qty) => sum + (Number(qty) || 0), 0)
    return Boolean(hotelId && checkIn && checkOut && totalRooms > 0)
  }

  return {
    state,
    hydrated,
    hydrate,
    set,
    setGuest,
    clear,
    hasBookingSelection
  }
}
