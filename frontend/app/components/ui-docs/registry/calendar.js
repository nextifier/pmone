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
  anatomy: {
    tree: [
      { component: "Calendar", children: [
        { component: "CalendarHeader", children: [
          { component: "CalendarPrevButton" },
          { component: "CalendarHeading" },
          { component: "CalendarNextButton" },
        ]},
        { component: "CalendarGrid", children: [
          { component: "CalendarGridHead", children: [ { component: "CalendarGridRow", children: [ { component: "CalendarHeadCell" } ] } ] },
          { component: "CalendarGridBody", children: [ { component: "CalendarGridRow", children: [ { component: "CalendarCell", children: [ { component: "CalendarCellTrigger" } ] } ] } ] },
        ]},
      ]},
    ],
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
      events: [
        { name: "update:modelValue", description: "Fires when the selection changes. Enables v-model." },
        { name: "update:placeholder", description: "Fires when the focused month/year changes during navigation." },
      ],
      slots: [
        { name: "calendar-heading", description: "Scoped slot to override the month/year heading. Receives { headingValue }." },
        { name: "calendar-prev-icon", description: "Override the previous-month navigation icon." },
        { name: "calendar-next-icon", description: "Override the next-month navigation icon." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["←"], description: "Moves to the previous day." },
      { keys: ["→"], description: "Moves to the next day." },
      { keys: ["↑"], description: "Moves to the same day in the previous week." },
      { keys: ["↓"], description: "Moves to the same day in the next week." },
      { keys: ["Home"], description: "Moves to the first day of the row." },
      { keys: ["End"], description: "Moves to the last day of the row." },
      { keys: ["Page Up"], description: "Moves to the previous month." },
      { keys: ["Page Down"], description: "Moves to the next month." },
      { keys: ["Enter"], description: "Selects the focused date." },
      { keys: ["Space"], description: "Selects the focused date." },
    ],
    notes: [
      "Uses the grid pattern; the focused date is the only tabbable cell (roving tabindex).",
      "Selected and disabled dates are conveyed via aria-selected and aria-disabled.",
    ],
  },
});
