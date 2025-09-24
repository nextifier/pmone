const isProduction = process.env.NODE_ENV === "production";

const app = {
  name: "PM One",
  shortName: "PM One",
  url: isProduction ? "https://pmone.id" : "http://localhost:3000",
  company: {
    name: "PT Panorama Media",
    address:
      "Panorama Media Building, Jl. Tanjung Selor No.17A, RT.11/RW.6, Cideng, Kecamatan Gambir, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10150",
  },
};

const settings = {
  ogImage: {
    isDarkMode: true,
  },
  terms: {
    lastUpdate: "August 21, 2025",
  },
};

const contact = {
  email: "hello@panoramamedia.co.id",
  whatsapp: "6281110529527",
};

export default defineAppConfig({
  app: app,
  settings: settings,
  contact: contact,
});
