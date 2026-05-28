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
    },
  ],
});
