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
      description: "Pass a prices map keyed by ISO date.",
      examples: ["default"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "PricingCalendar",
      props: [
        { name: "modelValue", type: "Date | string", default: "—", description: "Selected date. Supports v-model." },
        { name: "prices", type: "Record<string, number>", default: "{}", description: "Price map. Keys are ISO date strings (YYYY-MM-DD)." },
        { name: "currency", type: "string", default: '"USD"', description: "Currency for formatting." },
        { name: "locale", type: "string", default: '"en"', description: "Locale for date and currency formatting." },
        { name: "minDate", type: "Date | string", default: "—", description: "Earliest selectable date." },
        { name: "maxDate", type: "Date | string", default: "—", description: "Latest selectable date." },
      ],
    },
  ],
});
