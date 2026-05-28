import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "spinner",
  title: "Spinner",
  description: "Looping loading indicator. Use inside buttons, empty states, or inline next to text.",
  installation: { importPath: "@/components/ui/spinner", imports: ["Spinner"] },
  sections: [
    { id: "default", title: "Default", description: "Default size and colour.", examples: ["default"], align: "center" },
    { id: "with-text", title: "With text", description: "Inline next to status text.", examples: ["with-text"], align: "center" },
  ],
  apiReference: [
    {
      component: "Spinner",
      props: [
        { name: "class", type: "string", default: "—", description: "Override size (size-4, size-6, ...) and colour." },
      ],
    },
  ],
});
