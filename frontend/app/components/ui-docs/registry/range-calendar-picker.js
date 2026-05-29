import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "range-calendar-picker",
  title: "Range Calendar Picker",
  description:
    "Button + Popover + RangeCalendar bundle. The DatePicker equivalent for date ranges.",
  installation: {
    importPath: "@/components/ui/range-calendar-picker",
    imports: ["RangeCalendarPicker"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Click to open, pick start and end dates.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "single-month",
      title: "Single month",
      description: "Set numberOfMonths to 1 for a more compact popover.",
      examples: ["single-month"],
      align: "center",
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "The trigger button comes in sm, default, and lg sizes.",
      examples: ["sizes"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "RangeCalendarPicker",
      props: [
        { name: "modelValue", type: "DateRangeValue | null", default: "null", description: "Selected { start, end } range. Supports v-model." },
        { name: "placeholder", type: "string", default: '"Pick a date range"', description: "Trigger text when nothing is selected." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the trigger." },
        { name: "numberOfMonths", type: "number", default: "2", description: "Months shown side by side in the popover." },
        { name: "min", type: "Date | null", default: "null", description: "Earliest selectable date." },
        { name: "max", type: "Date | null", default: "null", description: "Latest selectable date." },
        { name: "size", type: '"default" | "sm" | "lg"', default: '"sm"', description: "Trigger button size." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the chosen range. Enables v-model." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "Opens the range calendar popover from the trigger." },
      { keys: ["Enter"], description: "Opens the popover, or selects a range endpoint inside the grid." },
      { keys: ["←"], description: "Moves to the previous day." },
      { keys: ["→"], description: "Moves to the next day." },
      { keys: ["↑"], description: "Moves to the same day in the previous week." },
      { keys: ["↓"], description: "Moves to the same day in the next week." },
      { keys: ["Esc"], description: "Closes the popover and returns focus to the trigger." },
    ],
    notes: [
      "Composes a button, popover, and range calendar grid.",
      "The first Enter sets the range start; the second sets the range end.",
      "Focus moves into the grid on open and back to the trigger on close.",
    ],
  },
});
