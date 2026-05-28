import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "button-copy",
  title: "Button Copy",
  description:
    "Icon button that copies text to the clipboard. Cross-fades to a check icon when copied with a tippy tooltip.",
  installation: {
    importPath: "@/components/ui/button-copy",
    imports: ["ButtonCopy"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass the string to copy via the text prop.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-input",
      title: "Next to an input",
      description: "Common pattern: read-only input plus copy button for shareable URLs.",
      examples: ["with-input"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ButtonCopy",
      props: [
        {
          name: "text",
          type: "string",
          default: "—",
          description: "String written to the clipboard on click.",
        },
      ],
    },
  ],
});
