import type { EventRef, Hotel, HotelTransferOption, Media, RoomType, TransferDirection } from './hotel'

export type ReservationStatus =
  | 'pending_payment'
  | 'paid'
  | 'voucher_sent'
  | 'expired'
  | 'cancelled'
  | 'refunded'

export type ReservationSource = 'public_website' | 'admin_manual'

export type IdentityType = 'nik' | 'passport'

export type PaymentMethod = 'xendit' | 'manual_bank_transfer' | 'complimentary' | string

export interface ReservationGuest {
  name: string
  email: string
  phone: string
  identity_type: IdentityType
  identity_type_label?: string
  identity_number: string
  nationality: string | null
  company: string | null
}

export interface ReservationAmounts {
  subtotal_rooms: number
  subtotal_transfer: number
  surcharge_amount: number
  tax: number
  service: number
  discount: number
  total: number
  refund_amount: number | null
}

export interface ReservationPayment {
  method: PaymentMethod | null
  method_label: string | null
  xendit_invoice_id: string | null
  payment_url: string | null
  xendit_refund_id: string | null
}

export interface ReservationItem {
  id: number
  reservation_id: number
  room_type_id: number
  allotment_id: number | null
  check_in_date: string
  check_out_date: string
  nights: number
  qty: number
  guest_name: string | null
  guest_identity: string | null
  rate_per_night: number
  subtotal: number
  room_type?: RoomType
}

export interface ReservationTransferItem {
  id: number
  reservation_id: number
  transfer_option_id: number
  direction: TransferDirection
  direction_label?: string
  transfer_date: string
  transfer_time: string | null
  pickup_location: string | null
  dropoff_location: string | null
  flight_number: string | null
  flight_time: string | null
  pax_count: number
  luggage_count: number | null
  note: string | null
  price: number
  transfer_option?: HotelTransferOption
}

export interface Reservation {
  id: number
  ulid: string
  reservation_number: string
  status: ReservationStatus
  status_label: string
  source: ReservationSource
  payment_expires_at: string | null
  paid_at: string | null
  voucher_sent_at: string | null
  cancelled_at: string | null
  refunded_at: string | null
  cancellation_reason: string | null
  refund_reason: string | null
  special_request: string | null
  notes: string | null
  guest: ReservationGuest
  amounts: ReservationAmounts
  payment: ReservationPayment
  hotel?: Hotel
  event?: EventRef
  items: ReservationItem[]
  transfers: ReservationTransferItem[]
  voucher: Media | null
  can_cancel?: boolean
  can_upload_voucher?: boolean
  can_send_voucher?: boolean
  can_view_documents?: boolean
  created_at: string
  updated_at: string
}

export interface ReservationActivityLog {
  id: number
  description: string
  event: string
  changes: Record<string, unknown> | null
  previous: Record<string, unknown> | null
  causer: { id: number; name: string } | null
  created_at: string
}

export interface BookingSessionGuest {
  name: string
  email: string
  phone: string
  identity_type: IdentityType
  identity_number: string
  nationality: string
  company: string
  special_request: string
}

export interface BookingSessionState {
  hotelId: number | null
  eventSlug: string | null
  hotelSlug: string | null
  checkIn: string | null
  checkOut: string | null
  rooms: Record<number, number>
  transfers: Record<number, boolean>
  guest: BookingSessionGuest
  acceptTerms: boolean
}
