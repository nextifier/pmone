import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-error-message",
  title: "Input Error Message",
  description:
    "Small destructive-coloured text used to display validation errors under a form control. A lightweight alternative to FieldError when you are not using a Field wrapper.",
  installation: {
    importPath: "@/components/ui/input-error-message",
    imports: ["InputErrorMessage"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Render below an Input.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputErrorMessage",
      props: [
        { name: "class", type: "string", default: "—", description: "Extra classes for typography or spacing overrides." },
      ],
      slots: [
        { name: "default", description: "Error text." },
      ],
    },
  ],
});
