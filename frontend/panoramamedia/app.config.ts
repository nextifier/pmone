const isProduction = process.env.NODE_ENV === "production";

const app = {
  name: "Panorama Media",
  shortName: "Panorama Media",
  url: isProduction ? "https://panoramamedia.co.id" : "http://localhost:3000",
  company: {
    name: "PT Panorama Media",
    address:
      "Panorama Media Building, Jl. Tanjung Selor No.17A, RT.11/RW.6, Cideng, Kecamatan Gambir, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10150",
  },
  // PM One API Configuration
  pmOneApiUrl: isProduction ? "https://api.pmone.id" : "http://localhost:8000",
  pmOneApiKey: "YOUR_API_KEY_HERE", // Replace with actual API key from PM One API Consumer
  blogUsername: "admin", // Username of blog author in PM One
};

const settings = {
  blog: {
    showPostCardAuthor: true,
    showPostCardExcerpt: false,
  },
  ogImage: {
    isDarkMode: false,
  },
  terms: {
    lastUpdate: "August 21, 2025",
  },
};

const contact = {
  email: "hello@panoramamedia.co.id",
  whatsapp: "6281110529527",
};

const emailRecipients = {
  to: isProduction
    ? ["hello@panoramamedia.co.id"]
    : ["antonius@panoramamedia.co.id"],
  cc: isProduction ? ["events@panoramamedia.co.id"] : [],
  bcc: isProduction ? [] : [],
};

const social = {
  instagram: "panoramamediaid",
  // facebook: "",
  linkedin: "panorama-media",
  youtube: "pmoneid",
  // tiktok: "",
};

const contactLinks = {
  email: {
    label: "Email",
    path: `mailto:${contact.email}`,
  },
  whatsapp: {
    label: "WhatsApp",
    path: `https://api.whatsapp.com/send?phone=${contact.whatsapp}&text=Halo, ${app.shortName}!`,
  },
};

const socialLinks = {
  instagram: {
    label: "Instagram",
    path: `https://www.instagram.com/${social.instagram}`,
    iconName: "hugeicons:instagram",
  },
  // facebook: {
  //   label: "Facebook",
  //   path: `https://www.facebook.com/${social.facebook}`,
  //   iconName: "hugeicons:facebook-01",
  // },
  // tiktok: {
  //   label: 'TikTok',
  //   path: `https://tiktok.com/@${social.tiktok}`,
  //   iconName: 'hugeicons:tiktok',
  // },
  linkedin: {
    label: "LinkedIn",
    path: `https://www.linkedin.com/company/${social.linkedin}`,
    iconName: "hugeicons:linkedin-01",
  },
  youtube: {
    label: "YouTube",
    path: `https://www.youtube.com/@${social.youtube}`,
    iconName: "hugeicons:youtube",
  },
};

const routes = {
  home: {
    label: "Home",
    path: "/",
  },
  about: {
    label: "About",
    path: "/about",
  },
  products: {
    label: "Products",
    path: "/products",
  },
  events: {
    label: "Events",
    path: "/events",
  },
  faq: {
    label: "FAQ",
    path: "/faq",
  },
  contact: {
    label: "Contact",
    path: "/contact",
  },
  news: {
    label: "News",
    path: "/news",
    rightClickLink: "https://blog.levenium.com/ghost/#/dashboard",
  },
};

export default defineAppConfig({
  app: app,
  settings: settings,
  contact: contact,
  emailRecipients: emailRecipients,
  social: social,
  contactLinks: contactLinks,
  socialLinks: socialLinks,

  routes: {
    header: [
      routes.home,
      routes.about,
      routes.products,
      routes.events,
      routes.faq,
      routes.contact,
      routes.news,
    ],

    dialog: [
      {
        label: "Menu",
        links: [
          routes.home,
          routes.about,
          routes.products,
          routes.events,
          routes.faq,
          routes.contact,
          routes.news,
        ],
      },
      {
        label: "Get in touch",
        links: Object.values(contactLinks),
      },
      {
        label: "Social",
        links: Object.values(socialLinks),
      },
      // {
      //   label: "Resources",
      //   links: [
      //     routes.gallery,
      //     routes.faq,
      //     routes.ticketPolicy,
      //     routes.eventPolicy,
      //     routes.links,
      //   ],
      // },
    ],

    // footer: [
    //   {
    //     label: "Discover",
    //     links: [
    //       routes.brands,
    //       routes.rundown,
    //       routes.programs,
    //       routes.ticket,
    //       routes.partners,
    //       routes.news,
    //     ],
    //   },
    //   {
    //     label: "For Businesses",
    //     links: [
    //       routes.bookSpace,
    //       routes.sponsorship,
    //       routes.mediaPartner,
    //       routes.contact,
    //     ],
    //   },
    //   {
    //     label: "Resources",
    //     links: [
    //       routes.gallery,
    //       routes.faq,
    //       routes.ticketPolicy,
    //       routes.eventPolicy,
    //       routes.links,
    //     ],
    //   },
    // ],
  },
});
