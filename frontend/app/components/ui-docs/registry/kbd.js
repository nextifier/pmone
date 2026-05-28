import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "kbd",
  title: "Kbd",
  description:
    "Inline keyboard hint, styled to look like a physical key cap. KbdGroup chains multiple together for sequences like ⌘ K.",
  installation: {
    importPath: "@/components/ui/kbd",
    imports: ["Kbd", "KbdGroup"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single key cap.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "group",
      title: "Group",
      description: "Chain modifiers with KbdGroup.",
      examples: ["group"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Kbd / KbdGroup",
      props: [
        { name: "class", type: "string", default: "—", description: "Override typography or shape." },
      ],
    },
  ],
});
