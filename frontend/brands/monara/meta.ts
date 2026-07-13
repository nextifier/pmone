import type { BrandMeta } from "../types";

// TODO(owner): replace the placeholder company/contact values with Monara's
// real identity before launching monara.id, then flip assetsReady to true
// once public/brands/monara/ holds the real favicon/icons/screenshots.
const meta: BrandMeta = {
  id: "monara",
  name: "Monara",
  shortName: "Monara",
  siteUrl: "https://monara.id",
  apiUrl: "https://api.monara.id",
  company: {
    name: "Monara",
    address: "",
  },
  contact: {
    email: "hello@monara.id",
    whatsapp: "",
  },
  manifestDescription:
    "Streamline your project management with Monara - a powerful, intuitive dashboard that helps you organize tasks, track progress, and collaborate seamlessly. Access your projects anywhere, anytime with our fast and reliable PWA experience.",
  assetsReady: false,
  organizationOptions: ["Monara"],
};

export default meta;
