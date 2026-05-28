import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-link",
  title: "Input Link",
  description:
    "URL / social handle input that auto-prefixes with the right base URL. Pass label='Instagram' and the input prepends https://instagram.com/. Pasting a full URL strips the prefix automatically.",
  installation: {
    importPath: "@/components/ui/input-link",
    imports: ["InputLink"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Generic URL input.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "social",
      title: "Social media",
      description: "Pass label as Instagram, Facebook, X, TikTok, LinkedIn, YouTube to get the matching prefix.",
      examples: ["social"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputLink",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "Full URL. Supports v-model." },
        {
          name: "label",
          type: '"Website" | "Instagram" | "Facebook" | "X" | "TikTok" | "LinkedIn" | "YouTube"',
          default: '"Website"',
          description: "Determines the prefix and validation pattern.",
        },
        { name: "class", type: "string", default: "—", description: "Extra classes." },
      ],
    },
  ],
});
