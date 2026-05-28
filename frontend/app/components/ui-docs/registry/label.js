import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "label",
  title: "Label",
  description:
    "Form field label. Connects an input through the for attribute; clicking the label focuses the matching control.",
  installation: {
    importPath: "@/components/ui/label",
    imports: ["Label"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Plain text label tied to an input via for/id.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Label",
      props: [
        { name: "for", type: "string", default: "—", description: "ID of the input this label belongs to." },
        { name: "class", type: "string", default: "—", description: "Override typography or spacing." },
      ],
    },
  ],
});
