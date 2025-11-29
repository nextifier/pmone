export const useEventStore = defineStore("events", {
  state: () => ({
    events: [
      {
        title: "PM Kickoff & Outing 2024",
        slug: "pm-kickoff-and-outing-2024",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Outing", "Kickoff", "Team Building"],
        date: "Feb 1-3, 2024",
        venue: "Yogyakarta",
        totalParticipants: "40+",
        client: {
          name: "Panorama Media",
          logo: "/img/clients/SVG-panorama-media.svg",
          isVector: true,
        },
        photos: [
          {
            src: "/img/events/pm-kickoff-2024/photos/cover.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos/cover.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/01.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/01.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/02.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/02.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/03.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/03.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/04.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/04.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/05.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/05.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/06.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/06.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/07.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/07.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/08.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/08.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/09.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/09.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/10.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/10.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/11.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/11.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/12.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/12.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/13.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/13.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/14.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/14.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/15.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/15.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pm-kickoff-2024/photos/16.jpg",
            thumbnail: "/img/events/pm-kickoff-2024/photos-thumb/16.jpg",
            w: 1600,
            h: 1067,
          },
        ],
      },

      {
        title: "PwC Outing Series 2023",
        slug: "pwc-2023",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Outing", "Team Building"],
        date: "August - December 2023",
        venue: "Bali",
        totalParticipants: "3000+",
        client: {
          name: "PwC",
          logo: "/img/clients/SVG-pwc.svg",
          isVector: true,
        },
        photos: [
          {
            src: "/img/events/pwc-2023/photos/01.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/01.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pwc-2023/photos/02.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/02.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/pwc-2023/photos/03.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/03.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/04.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/04.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/05.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/05.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/06.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/06.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/07.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/07.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/08.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/08.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/09.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/09.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/10.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/10.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/pwc-2023/photos/11.jpg",
            thumbnail: "/img/events/pwc-2023/photos-thumb/11.jpg",
            w: 1600,
            h: 900,
          },
        ],
      },

      {
        title: "Mudik Sehat Bersama Bank BTN",
        slug: "mudik-sehat-bersama-btn",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Mudik", "Tour Leading"],
        date: "April 18, 2023",
        venue: "GBK",
        totalParticipants: "600+",
        client: {
          name: "Bank BTN",
          logo: "/img/clients/bank-btn.png",
        },
        photos: [
          {
            src: "/img/events/btn-2023/photos/01.jpg",
            thumbnail: "/img/events/btn-2023/photos-thumb/01.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/btn-2023/photos/02.jpg",
            thumbnail: "/img/events/btn-2023/photos-thumb/02.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/btn-2023/photos/03.jpg",
            thumbnail: "/img/events/btn-2023/photos-thumb/03.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/btn-2023/photos/04.jpg",
            thumbnail: "/img/events/btn-2023/photos-thumb/04.jpg",
            w: 1600,
            h: 1067,
          },
          {
            src: "/img/events/btn-2023/photos/05.jpg",
            thumbnail: "/img/events/btn-2023/photos-thumb/05.jpg",
            w: 1600,
            h: 1067,
          },
        ],
      },

      {
        title: "JP Morgan Training & Business Meeting (VIP)",
        slug: "jp-morgan-training-and-business-meeting-vip",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Training", "Business Meeting"],
        date: "April 16-18, 2023",
        venue: "Apurva Kempinski, Bali",
        totalParticipants: "8",
        client: {
          name: "JP Morgan",
          logo: "/img/clients/SVG-jpm.svg",
          isVector: true,
        },
        photos: [
          {
            src: "/img/events/jpm-2023/photos/01.jpg",
            thumbnail: "/img/events/jpm-2023/photos-thumb/01.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/jpm-2023/photos/02.jpg",
            thumbnail: "/img/events/jpm-2023/photos-thumb/02.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/jpm-2023/photos/03.jpg",
            thumbnail: "/img/events/jpm-2023/photos-thumb/03.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/jpm-2023/photos/04.jpg",
            thumbnail: "/img/events/jpm-2023/photos-thumb/04.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/jpm-2023/photos/05.jpg",
            thumbnail: "/img/events/jpm-2023/photos-thumb/05.jpg",
            w: 1600,
            h: 900,
          },
        ],
      },

      {
        title: "Tugure Anniversary 2023",
        slug: "tugure-anniversary-2023",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Anniversary"],
        date: "April 2023",
        venue: "Tugure Building, Jakarta",
        totalParticipants: "100",
        client: {
          name: "Tugure",
          logo: "/img/clients/tugure.png",
        },
        photos: [
          {
            src: "/img/events/tugure-2023/photos/01.jpg",
            thumbnail: "/img/events/tugure-2023/photos-thumb/01.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/tugure-2023/photos/02.jpg",
            thumbnail: "/img/events/tugure-2023/photos-thumb/02.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/tugure-2023/photos/03.jpg",
            thumbnail: "/img/events/tugure-2023/photos-thumb/03.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/tugure-2023/photos/04.jpg",
            thumbnail: "/img/events/tugure-2023/photos-thumb/04.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/tugure-2023/photos/05.jpg",
            thumbnail: "/img/events/tugure-2023/photos-thumb/05.jpg",
            w: 1600,
            h: 900,
          },
        ],
      },

      {
        title: "Calvin Klein Product Launching 2023",
        slug: "calvin-klein-product-launching-2023",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Product Launching"],
        date: "June 26, 2023",
        venue: "Cloud Lounge, Jakarta",
        totalParticipants: "70",
        client: {
          name: "Watch Continent",
          logo: "/img/clients/watch-continent.png",
        },
        photos: [
          {
            src: "/img/events/ck-2023/photos/01.jpg",
            thumbnail: "/img/events/ck-2023/photos-thumb/01.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/ck-2023/photos/02.jpg",
            thumbnail: "/img/events/ck-2023/photos-thumb/02.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/ck-2023/photos/03.jpg",
            thumbnail: "/img/events/ck-2023/photos-thumb/03.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/ck-2023/photos/04.jpg",
            thumbnail: "/img/events/ck-2023/photos-thumb/04.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/ck-2023/photos/05.jpg",
            thumbnail: "/img/events/ck-2023/photos-thumb/05.jpg",
            w: 1600,
            h: 900,
          },
        ],
      },

      {
        title: "MIDO Watch Product Launching 2023",
        slug: "mido-watch-product-launching-2023",
        status: "Completed", // Available Values: Completed, Upcoming
        categories: ["Product Launching"],
        date: "June 27, 2023",
        venue: "Quicksilver Cruise, Jakarta",
        totalParticipants: "70",
        client: {
          name: "Watch Continent",
          logo: "/img/clients/watch-continent.png",
        },
        photos: [
          {
            src: "/img/events/mido-2023/photos/01.jpg",
            thumbnail: "/img/events/mido-2023/photos-thumb/01.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/mido-2023/photos/02.jpg",
            thumbnail: "/img/events/mido-2023/photos-thumb/02.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/mido-2023/photos/03.jpg",
            thumbnail: "/img/events/mido-2023/photos-thumb/03.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/mido-2023/photos/04.jpg",
            thumbnail: "/img/events/mido-2023/photos-thumb/04.jpg",
            w: 1600,
            h: 900,
          },
          {
            src: "/img/events/mido-2023/photos/05.jpg",
            thumbnail: "/img/events/mido-2023/photos-thumb/05.jpg",
            w: 1600,
            h: 900,
          },
        ],
      },
    ],
  }),

  getters: {
    getEventBySlug: (state) => (slug) => {
      return state.events.find((event) => event.slug === slug);
    },
  },
});
