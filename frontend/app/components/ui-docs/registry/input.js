import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input",
  title: "Input",
  description:
    "Single-line text field. Supports every native HTML input type (text, email, number, date, file). Pair it with Field to add a label, description, or error message.",
  installation: {
    importPath: "@/components/ui/input",
    imports: ["Input"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Standard input bound with v-model.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "The native disabled attribute works as expected.",
      examples: ["disabled"],
      align: "center",
    },
    {
      id: "types",
      title: "Type variants",
      description: "Every native HTML type is supported through the type attribute.",
      examples: ["types"],
      align: "center",
    },
    {
      id: "with-field",
      title: "With Field",
      description:
        "Wrap Input in Field, use FieldLabel for the label, and FieldDescription for hint text.",
      examples: ["with-field"],
      align: "center",
    },
    {
      id: "with-error",
      title: "Error state",
      description:
        "Set data-invalid on Field, aria-invalid on Input, and use FieldError for the message.",
      examples: ["with-error"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Input",
      props: [
        {
          name: "modelValue",
          type: "string | number",
          default: "—",
          description: "Value. Supports v-model.",
        },
        {
          name: "defaultValue",
          type: "string | number",
          default: "—",
          description: "Starting value when v-model is not used.",
        },
        {
          name: "type",
          type: "string",
          default: '"text"',
          description: "Native HTML input type.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
      events: [
        { name: "update:modelValue", description: "Fires on input. Enables v-model. Native input events also bubble through." },
      ],
    },
  ],
});
