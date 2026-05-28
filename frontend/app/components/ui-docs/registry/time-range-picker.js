import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "time-range-picker",
  title: "Time Range Picker",
  description:
    "Pick a start and end time as Time instances. Used for opening hours, shift schedules, and event blocks. Built on reka-ui's TimeRangeFieldRoot.",
  installation: {
    importPath: "@/components/ui/time-range-picker",
    imports: ["TimeRangePicker"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Start and end segments with a dash separator. Bind to { start, end } Time values.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "TimeRangePicker",
      props: [
        {
          name: "modelValue",
          type: "{ start: Time, end: Time }",
          default: "—",
          description: "Range with Time instances from @internationalized/date. Supports v-model.",
        },
        {
          name: "hourCycle",
          type: "12 | 24",
          default: "24",
          description: "Display 12- or 24-hour format.",
        },
        { name: "locale", type: "string", default: '"en-US"', description: "BCP 47 locale for formatting." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
        { name: "readonly", type: "boolean", default: "false", description: "Make the field read-only." },
        { name: "clearable", type: "boolean", default: "false", description: "Show a clear button when a value is set." },
        { name: "showCaret", type: "boolean", default: "false", description: "Show a blinking caret on the focused segment." },
      ],
    },
  ],
});
