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
  ],
  apiReference: [
    {
      component: "RangeCalendarPicker",
      props: [
        { name: "modelValue", type: "{ start, end }", default: "—", description: "Selected range. Supports v-model." },
        { name: "placeholder", type: "string", default: '"Pick a range"', description: "Trigger text." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the trigger." },
        { name: "minDate", type: "Date | string", default: "—", description: "Earliest selectable date." },
        { name: "maxDate", type: "Date | string", default: "—", description: "Latest selectable date." },
      ],
    },
  ],
});
