import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "flag",
  title: "Flag",
  description:
    "Country flag icon. Pass an ISO 3166-1 alpha-2 country code; the image is loaded from /flags/{code}.png at runtime.",
  installation: {
    importPath: "@/components/ui/flag",
    imports: ["Flag"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single flag, inline with text.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "list",
      title: "Inline list",
      description: "Use Flag in a list of countries.",
      examples: ["list"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Flag",
      props: [
        { name: "country", type: "string", default: "—", description: "ISO 3166-1 alpha-2 country code (e.g. 'us', 'id', 'jp'). Lowercased automatically." },
        { name: "countryName", type: "string", default: "—", description: "Used for alt and title text." },
      ],
    },
  ],
});
