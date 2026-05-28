import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "language-switcher",
  title: "Language Switcher",
  description:
    "Dropdown for changing the active locale. Reads available locales from the @nuxtjs/i18n config and writes to the store on selection.",
  installation: {
    importPath: "@/components/ui/language-switcher",
    imports: ["LanguageSwitcher"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Auto-populated locale list.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "LanguageSwitcher",
      props: [
        { name: "—", type: "—", default: "—", description: "No props. Reads the locale list from the i18n module." },
      ],
    },
  ],
});
