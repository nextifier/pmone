import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "color-mode-toggle",
  title: "Color Mode Toggle",
  description:
    "One-click colour mode flip for the header. Cycles through light, dark, and system. For a settings-page picker, use ColorModeButtons.",
  installation: {
    importPath: "@/components/ui/color-mode-toggle",
    imports: ["ColorModeToggle"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Drop into a header alongside other navigation actions.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ColorModeToggle",
      props: [
        {
          name: "—",
          type: "—",
          default: "—",
          description: "No props. Reads and writes the Nuxt color-mode store directly.",
        },
      ],
    },
  ],
});
