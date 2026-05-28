import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "time-picker",
  title: "Time Picker",
  description:
    "Segmented hour/minute input built on reka-ui's TimeFieldRoot. Value is a Time instance from @internationalized/date.",
  installation: { importPath: "@/components/ui/time-picker", imports: ["TimePicker"] },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "24-hour input with hour and minute segments. Bind to a Time value.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "24h",
      title: "Hour cycle",
      description:
        "hourCycle accepts 12 or 24. The 12-hour variant adds an AM/PM segment whose literal can differ between Node and browser ICU, so wrap usage in ClientOnly to avoid hydration warnings.",
      examples: ["24h"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "TimePicker",
      props: [
        {
          name: "modelValue",
          type: "Time | null | undefined",
          default: "—",
          description: "Time value from @internationalized/date. Use new Time(hour, minute). Supports v-model.",
        },
        {
          name: "hourCycle",
          type: "12 | 24",
          default: "24",
          description: "Display 12- or 24-hour format.",
        },
        { name: "granularity", type: '"hour" | "minute" | "second"', default: '"minute"', description: "Smallest segment shown." },
        { name: "locale", type: "string", default: '"en-US"', description: "BCP 47 locale for formatting." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
        { name: "readonly", type: "boolean", default: "false", description: "Make the field read-only." },
        { name: "clearable", type: "boolean", default: "false", description: "Show a clear button when a value is set." },
        { name: "showCaret", type: "boolean", default: "false", description: "Show a blinking caret on the focused segment." },
      ],
    },
  ],
});
