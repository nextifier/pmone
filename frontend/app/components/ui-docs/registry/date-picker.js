import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "date-picker",
  title: "Date Picker",
  description:
    "Button + Popover + Calendar bundled into one component. Supports optional time selection. For inline picking without a trigger, use Calendar directly.",
  installation: {
    importPath: "@/components/ui/date-picker",
    imports: ["DatePicker"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Click to open the calendar popover.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-time",
      title: "With time",
      description: "Set withTime to add hour and minute selects below the calendar.",
      examples: ["with-time"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Set disabled to block the trigger from opening.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "DatePicker",
      props: [
        { name: "modelValue", type: "Date | null", default: "null", description: "Selected date. Supports v-model." },
        { name: "withTime", type: "boolean", default: "false", description: "Add hour/minute selects below the calendar." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the trigger." },
        { name: "placeholder", type: "string", default: '"Pick a date"', description: "Trigger text when nothing is selected." },
        { name: "defaultHour", type: "number", default: "9", description: "Initial hour when withTime is on." },
        { name: "defaultMinute", type: "number", default: "0", description: "Initial minute when withTime is on." },
        { name: "disableFutureDates", type: "boolean", default: "false", description: "Block selecting dates after today." },
        { name: "disablePastDates", type: "boolean", default: "false", description: "Block selecting dates before today." },
        { name: "min", type: "Date | null", default: "null", description: "Earliest selectable date." },
        { name: "max", type: "Date | null", default: "null", description: "Latest selectable date." },
        { name: "minYear", type: "number", default: "—", description: "Lower bound of the year dropdown." },
        { name: "maxYear", type: "number", default: "—", description: "Upper bound of the year dropdown." },
        { name: "placeholderDate", type: "Date | null", default: "null", description: "Month the calendar opens on when empty." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the chosen Date (or null). Enables v-model." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "Opens the calendar popover from the trigger." },
      { keys: ["Enter"], description: "Opens the calendar popover from the trigger." },
      { keys: ["↑"], description: "Moves to the same day in the previous week." },
      { keys: ["↓"], description: "Moves to the same day in the next week." },
      { keys: ["←"], description: "Moves to the previous day." },
      { keys: ["→"], description: "Moves to the next day." },
      { keys: ["Esc"], description: "Closes the popover and returns focus to the trigger." },
    ],
    notes: [
      "Composes a button, popover, and calendar grid.",
      "Focus moves into the grid when the popover opens and back to the trigger when it closes.",
      "The trigger exposes aria-expanded to reflect the popover state.",
    ],
  },
});
