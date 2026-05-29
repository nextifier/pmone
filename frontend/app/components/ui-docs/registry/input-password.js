import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-password",
  title: "Input Password",
  description: "Password input with a built-in show/hide toggle.",
  installation: {
    importPath: "@/components/ui/input-password",
    imports: ["InputPassword"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Click the eye icon to reveal the value.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-label",
      title: "With label",
      description: "Pair with a Label for a complete form field.",
      examples: ["with-label"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Pass disabled to block input and the reveal toggle.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputPassword",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "Password value. Supports v-model." },
        { name: "showLabel", type: "string", default: '"Show password"', description: "aria-label / tooltip for the reveal toggle." },
        { name: "hideLabel", type: "string", default: '"Hide password"', description: "aria-label / tooltip when the value is visible." },
        { name: "placeholder", type: "string", default: "—", description: "Native placeholder, passed through to the input." },
        { name: "disabled", type: "boolean", default: "false", description: "Native disabled attribute, passed through to the input." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires on input. Enables v-model." },
      ],
    },
  ],
});
