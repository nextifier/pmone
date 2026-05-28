import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "button-back",
  title: "Button Back",
  description:
    "Smart back button. Uses browser history when available, otherwise navigates to a fallback destination derived from the current path. Bound to the B keyboard shortcut by default.",
  installation: {
    importPath: "@/components/ui/button-back",
    imports: ["ButtonBack"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Renders an arrow + Back label. Pressing B triggers it.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "variants",
      title: "Variants",
      description: "default for inline use, bordered for floating buttons, semiTransparent for over-image headers.",
      examples: ["variants"],
      align: "center",
    },
    {
      id: "custom-trigger",
      title: "Custom trigger",
      description: "Use the default scoped slot to render your own UI while keeping the navigation behaviour.",
      examples: ["custom-trigger"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ButtonBack",
      props: [
        {
          name: "destination",
          type: "string",
          default: "null",
          description: "Fallback path when history is empty. Falls back to the parent path when null.",
        },
        {
          name: "showLabel",
          type: "boolean",
          default: "true",
          description: "Show or hide the Back label.",
        },
        {
          name: "forceDestination",
          type: "boolean",
          default: "false",
          description: "Always navigate to destination, ignoring history.",
        },
        {
          name: "variant",
          type: '"default" | "bordered" | "semiTransparent"',
          default: '"default"',
          description: "Visual style.",
        },
        {
          name: "shortcut",
          type: "boolean",
          default: "true",
          description: "Enable the B keyboard shortcut.",
        },
      ],
      slots: [
        {
          name: "default",
          description: "Scoped slot receives goBack handler for fully custom triggers.",
        },
      ],
    },
  ],
});
