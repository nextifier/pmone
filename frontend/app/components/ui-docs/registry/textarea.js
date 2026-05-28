import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "textarea",
  title: "Textarea",
  description: "Multi-line text input. Pair with Field for label + description + error.",
  installation: { importPath: "@/components/ui/textarea", imports: ["Textarea"] },
  sections: [
    { id: "default", title: "Default", description: "Standard textarea bound with v-model.", examples: ["default"], align: "center" },
    { id: "with-field", title: "With Field", description: "Wrap in Field to add label + description.", examples: ["with-field"], align: "center" },
    { id: "disabled", title: "Disabled", description: "Disable input.", examples: ["disabled"], align: "center" },
  ],
  apiReference: [
    {
      component: "Textarea",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Value. Supports v-model." },
        { name: "rows", type: "number", default: "—", description: "Starting visible rows." },
        { name: "placeholder", type: "string", default: "—", description: "Placeholder text." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
      ],
    },
  ],
});
