import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "calendar",
  title: "Calendar",
  description:
    "Inline date picker built on reka-ui. Three layouts: month-and-year (default), month-only, year-only. Supports single, multiple, and range selection modes.",
  installation: {
    importPath: "@/components/ui/calendar",
    imports: ["Calendar"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single date selection.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "range",
      title: "Range",
      description: "Pick a start and end date.",
      examples: ["range"],
      align: "center",
    },
    {
      id: "multiple",
      title: "Multiple",
      description: "Select arbitrary dates one by one.",
      examples: ["multiple"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Calendar",
      props: [
        {
          name: "modelValue",
          type: "DateValue | DateValue[]",
          default: "—",
          description: "Selected date(s). Supports v-model.",
        },
        {
          name: "layout",
          type: '"month-and-year" | "month-only" | "year-only"',
          default: '"month-and-year"',
          description: "Heading layout.",
        },
        { name: "mode", type: '"single" | "multiple" | "range"', default: '"single"', description: "Selection mode (reka-ui)." },
        { name: "numberOfMonths", type: "number", default: "1", description: "Render multiple months side by side." },
        { name: "minValue", type: "DateValue", default: "—", description: "Earliest selectable date." },
        { name: "maxValue", type: "DateValue", default: "—", description: "Latest selectable date." },
        { name: "locale", type: "string", default: '"en"', description: "Locale for month/day names." },
        { name: "weekStartsOn", type: "number", default: "1", description: "0 = Sunday, 1 = Monday." },
      ],
    },
  ],
});
