// Single source of truth for booth types, mirrors App\Enums\BoothType on the API.
// Keep both lists in sync when a booth type is added or removed. Auto-imported.

export const BOOTH_TYPE_OPTIONS = [
  { value: "raw_space", label: "Raw Space" },
  { value: "standard_shell_scheme", label: "Standard Shell Scheme" },
  { value: "enhanced_shell_scheme", label: "Enhanced Shell Scheme" },
  { value: "table_chair_only", label: "Table & Chair Only" },
  { value: "alley", label: "Alley" },
  { value: "food_and_beverage", label: "Food and Beverage" },
  { value: "artist_alley", label: "Artist Alley" },
  { value: "artist_alley_table", label: "Artist Alley Table" },
  { value: "guest_booth", label: "Guest Booth" },
  { value: "cosplay_zone", label: "Cosplay Zone" },
  { value: "toys_hunting_ground", label: "Toys Hunting Ground" },
  { value: "portfolio_review", label: "Portfolio Review" },
  { value: "community_booth", label: "Community Booth" },
  { value: "pavilion", label: "Pavilion" },
];

export function boothTypeLabel(value) {
  return BOOTH_TYPE_OPTIONS.find((option) => option.value === value)?.label || value;
}
