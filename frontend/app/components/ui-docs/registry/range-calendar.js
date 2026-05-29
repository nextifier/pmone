import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "range-calendar",
  title: "Range Calendar",
  description:
    "Inline calendar for selecting a start and end date. Built on reka-ui. For a button + popover wrapper, use RangeCalendarPicker.",
  installation: {
    importPath: "@/components/ui/range-calendar",
    imports: ["RangeCalendar"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pick start, then end. Range highlights in between.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "number-of-months",
      title: "Multiple months",
      description: "Render two months side by side for picking longer ranges.",
      examples: ["number-of-months"],
      align: "center",
    },
    {
      id: "min-max",
      title: "Min and max",
      description: "Constrain selection with minValue and maxValue; dates outside are disabled.",
      examples: ["min-max"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "RangeCalendar",
      props: [
        { name: "modelValue", type: "{ start: DateValue, end: DateValue }", default: "—", description: "Selected range. Supports v-model." },
        { name: "numberOfMonths", type: "number", default: "1", description: "Render multiple months side by side." },
        { name: "minValue", type: "DateValue", default: "—", description: "Earliest selectable date." },
        { name: "maxValue", type: "DateValue", default: "—", description: "Latest selectable date." },
        { name: "locale", type: "string", default: '"en"', description: "Locale for month/day names." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the range changes. Enables v-model." },
        { name: "update:placeholder", description: "Fires when the focused month changes during navigation." },
      ],
      slots: [
        { name: "calendar-heading", description: "Scoped slot to override the month/year heading. Receives { headingValue }." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["←"], description: "Moves to the previous day." },
      { keys: ["→"], description: "Moves to the next day." },
      { keys: ["↑"], description: "Moves to the same day in the previous week." },
      { keys: ["↓"], description: "Moves to the same day in the next week." },
      { keys: ["Page Up"], description: "Moves to the previous month." },
      { keys: ["Page Down"], description: "Moves to the next month." },
      { keys: ["Enter"], description: "Selects the focused date as a range endpoint." },
      { keys: ["Space"], description: "Selects the focused date as a range endpoint." },
    ],
    notes: [
      "The first Enter sets the range start; the second sets the range end.",
      "Uses the grid pattern with a roving tabindex on the focused date.",
      "Dates within the selection are conveyed via aria-selected.",
    ],
  },
});
