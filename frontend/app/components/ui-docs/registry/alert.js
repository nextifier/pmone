import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "alert",
  title: "Alert",
  description:
    "Inline message for status, warnings, or callouts. Two visual variants and slots for an icon, title, and description.",
  installation: {
    importPath: "@/components/ui/alert",
    imports: ["Alert", "AlertTitle", "AlertDescription"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Neutral alert with icon, title, and body.",
      examples: ["default"],
    },
    {
      id: "destructive",
      title: "Destructive",
      description: "Use for failures and irreversible actions.",
      examples: ["destructive"],
    },
    {
      id: "without-icon",
      title: "Without icon",
      description: "Drop the leading icon for plain text alerts.",
      examples: ["without-icon"],
    },
  ],
  apiReference: [
    {
      component: "Alert",
      props: [
        {
          name: "variant",
          type: '"default" | "destructive"',
          default: '"default"',
          description: "Visual tone.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
    },
    {
      component: "AlertTitle / AlertDescription",
      props: [
        { name: "class", type: "string", default: "—", description: "Override typography or spacing." },
      ],
    },
  ],
});
