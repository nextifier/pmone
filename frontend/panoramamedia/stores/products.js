export const useProductStore = defineStore("products", {
  state: () => ({
    list: [
      {
        name: "Panorama Events",
        slug: "panorama-events",
        img: "/img/products/panorama-events.jpg",
      },
      {
        name: "Panorama Live",
        slug: "panorama-live",
        img: "/img/products/panorama-live.jpg",
      },
      {
        name: "Megabuild Indonesia",
        slug: "megabuild-indonesia",
        img: "/img/products/megabuild.jpg",
      },
      {
        name: "Keramika Indonesia",
        slug: "keramika-indonesia",
        img: "/img/products/keramika.jpg",
      },
      {
        name: "Renovation Expo",
        slug: "renovation-expo",
        img: "/img/products/renex.jpg",
      },
      {
        name: "Indonesia Outing Expo",
        slug: "indonesia-outing-expo",
        img: "/img/products/ioe.jpg",
      },
      {
        name: "Franchise & License Expo Indonesia",
        slug: "flei",
        img: "/img/products/flei.jpg",
      },
      {
        name: "Cafe & Brasserie Expo Indonesia",
        slug: "cbe",
        img: "/img/products/cbe.jpg",
      },
      {
        name: "Indonesia Coffee Festival",
        slug: "indonesia-coffee-festival",
        img: "/img/products/icf.jpg",
      },
      {
        name: "Cokelat Expo Indonesia",
        slug: "cokelat-expo",
        img: "/img/products/cokelat-expo.jpg",
      },
      {
        name: "Indonesia Comic Con",
        slug: "indonesia-comic-con",
        img: "/img/products/icc.jpg",
      },
      {
        name: "Indonesia Anime Con",
        slug: "indonesia-anime-con",
        img: "/img/products/inacon.jpg",
      },
    ],
  }),

  getters: {
    getItemBySlug: (state) => (slug) => {
      return state.list.find((item) => item.slug === slug);
    },
  },
});
