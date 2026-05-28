import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "select",
  title: "Select",
  description:
    "Single-value dropdown built on reka-ui. For pickers that need search across many options, use Combobox instead.",
  installation: {
    importPath: "@/components/ui/select",
    imports: [
      "Select",
      "SelectTrigger",
      "SelectValue",
      "SelectContent",
      "SelectGroup",
      "SelectLabel",
      "SelectItem",
      "SelectSeparator",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Trigger, value placeholder, then SelectItem rows inside Content.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-group",
      title: "With groups",
      description:
        "Group options with SelectGroup and SelectLabel. Labels are visual separators, not selectable.",
      examples: ["with-group"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "The disabled attribute on the Select root disables the trigger.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Select",
      props: [
        {
          name: "modelValue",
          type: "string",
          default: "—",
          description: "Selected value. Supports v-model.",
        },
        {
          name: "defaultValue",
          type: "string",
          default: "—",
          description: "Starting value when v-model is not used.",
        },
        {
          name: "disabled",
          type: "boolean",
          default: "false",
          description: "Disable the entire select.",
        },
      ],
    },
    {
      component: "SelectTrigger / SelectContent",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Use class to set trigger width (e.g. w-[220px]).",
        },
      ],
    },
  ],
});
