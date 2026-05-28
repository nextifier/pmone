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
  ],
  apiReference: [
    {
      component: "DatePicker",
      props: [
        { name: "modelValue", type: "Date | string", default: "—", description: "Selected date. Supports v-model." },
        { name: "withTime", type: "boolean", default: "false", description: "Add hour/minute pickers." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the trigger." },
        { name: "placeholder", type: "string", default: '"Pick a date"', description: "Trigger text when nothing is selected." },
        { name: "minDate", type: "Date | string", default: "—", description: "Earliest selectable date." },
        { name: "maxDate", type: "Date | string", default: "—", description: "Latest selectable date." },
        { name: "yearRange", type: "DateValue[]", default: "—", description: "Override the year dropdown range." },
      ],
    },
  ],
});
