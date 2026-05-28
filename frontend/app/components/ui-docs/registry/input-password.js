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
  ],
  apiReference: [
    {
      component: "InputPassword",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "Password value. Supports v-model." },
        { name: "placeholder", type: "string", default: "—", description: "Placeholder text." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
      ],
    },
  ],
});
