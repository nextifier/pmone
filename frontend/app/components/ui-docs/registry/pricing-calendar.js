import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "pricing-calendar",
  title: "Pricing Calendar",
  description:
    "Calendar where each date cell shows a price tag. Use for hotel rooms, vehicle rentals, or any per-day inventory with variable pricing.",
  installation: {
    importPath: "@/components/ui/pricing-calendar",
    imports: [
      "PricingCalendar",
      "PricingCalendarCell",
      "PricingCalendarCellTrigger",
      "PricingCalendarGrid",
      "PricingCalendarGridBody",
      "PricingCalendarGridHead",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Two months side by side. Pass pricing-data keyed by ISO date with { rate, available }. Prices at/below good-price-threshold are highlighted.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "sold-out",
      title: "Sold out dates",
      description: "Dates with available === 0 are automatically disabled and not selectable.",
      examples: ["sold-out"],
      align: "start",
    },
    {
      id: "single-month",
      title: "Single month",
      description: "Set :number-of-months=\"1\" for a compact single-month layout.",
      examples: ["single-month"],
      align: "start",
    },
    {
      id: "loading",
      title: "Loading state",
      description: "While is-loading is true and pricing-data is empty, each cell shows a skeleton where the price would be.",
      examples: ["loading"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "PricingCalendar",
      props: [
        { name: "modelValue", type: "DateRange", default: "—", description: "Selected { start, end } range. Built on RangeCalendar. Supports v-model." },
        { name: "pricingData", type: "PricingMap", default: "{}", description: "Per-day price map keyed by ISO date (YYYY-MM-DD)." },
        { name: "numberOfMonths", type: "number", default: "2", description: "Months rendered side by side." },
        { name: "goodPriceThreshold", type: "number", default: "—", description: "Prices at or below this value get a highlighted style." },
        { name: "isLoading", type: "boolean", default: "false", description: "Show a loading state over the cells." },
        { name: "minValue", type: "DateValue", default: "—", description: "Earliest selectable date. Forwards to reka-ui RangeCalendarRoot." },
        { name: "maxValue", type: "DateValue", default: "—", description: "Latest selectable date." },
        { name: "locale", type: "string", default: '"en"', description: "Locale for date and currency formatting." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the range changes. Enables v-model." },
        { name: "update:placeholder", description: "Fires when the focused month changes." },
        { name: "month-change", description: "Fires when navigating months. Receives the visible-month payload (use to lazy-load prices)." },
      ],
    },
  ],
});
