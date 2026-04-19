export interface Media {
  id: number
  name: string
  url: string
  lqip?: string
  sm?: string
  md?: string
  lg?: string
  xl?: string
  original?: string
  size?: number
}

export type HotelCategory = 'hotel' | 'resort' | 'guest_house' | 'apartment' | string

export interface Hotel {
  id: number
  ulid: string
  slug: string
  name: string
  description: string | null
  star_rating: number | null
  category: HotelCategory | null
  address: string | null
  city: string | null
  country: string | null
  latitude: number | null
  longitude: number | null
  google_maps_link: string | null
  google_maps_embed_src: string | null
  contact_email: string | null
  contact_phone: string | null
  website_url: string | null
  check_in_time: string | null
  check_out_time: string | null
  commission_rate: number
  tax_percentage: number
  service_charge_percentage: number
  cancellation_policy: string | null
  children_policy: string | null
  nearest_airport: string | null
  airport_distance_km: number | null
  facilities: string[] | null
  is_active: boolean
  featured: Media | null
  gallery: Media[]
  event?: EventRef
  room_types?: RoomType[]
  transfer_options?: HotelTransferOption[]
  room_types_count?: number
  reservations_count?: number
  created_at?: string
  updated_at?: string
}

export interface EventRef {
  id: number
  slug: string
  title: string
  start_date?: string | null
  end_date?: string | null
  is_active?: boolean
  project?: { id: number; username: string; name: string }
}

export interface RoomType {
  id: number
  ulid: string
  hotel_id: number
  slug: string
  name: string
  description: string | null
  max_pax: number
  bed_type: string | null
  view_type: string | null
  area_sqm: number | null
  base_rate: number
  all_in_rate?: number
  breakfast_included: boolean
  smoking_allowed: boolean
  amenities: string[] | null
  cancellation_policy: string | null
  is_active: boolean
  gallery?: Media[]
}

export type AllotmentSurchargeType = 'fixed' | 'percentage' | null

export interface HotelEventAllotment {
  id: number
  ulid: string
  hotel_id: number
  room_type_id: number
  quantity: number
  start_date: string
  end_date: string
  release_at: string | null
  surcharge_type: AllotmentSurchargeType
  surcharge_amount: number | null
  is_active: boolean
}

export type TransferDirection = 'in' | 'out' | 'both'

export interface HotelTransferOption {
  id: number
  ulid: string
  hotel_id: number
  label: string
  direction: TransferDirection
  direction_label?: string
  vehicle_type: string | null
  max_pax: number
  price: number
  is_active: boolean
}

export interface AvailabilityResult {
  available: number
  qty: number
  rate_per_night: number
  all_in_per_night: number
  estimated_total: number
  is_available?: boolean
}
