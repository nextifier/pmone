export const useServicesStore = defineStore("services", {
  state: () => ({
    services: [
      {
        name: "Corporate Events",
        slug: "corporate-events",
        img: "/img/services/corporate-events.jpg",
        list: [
          "Outing",
          "Conferences",
          "Seminars",
          "Team-building",
          "Gathering",
          "Gala dinners",
          "Holiday parties",
        ],
        description: `<p>Elevate your corporate gatherings with our comprehensive event planning services. We specialize in outing, conferences, seminars, team-building, gathering, gala dinners, and holiday parties.</p>`,
        detailedServices: [
          {
            name: "Outing",
            description: `<p>Foster team bonding and relaxation with curated outdoor activities and excursions.</p>`,
          },
          {
            name: "Conferences and Seminars",
            description: `<p>Host successful conferences and seminars with expert planning and execution.</p>`,
          },
          {
            name: "Team-Building",
            description: `<p>Strengthen team dynamics and morale with engaging workshops and challenges.</p>`,
          },
          {
            name: "Gathering",
            description: `<p>Celebrate milestones and achievements with elegant gatherings and soirées.</p>`,
          },
          {
            name: "Gala Dinners",
            description: `<p>Create memorable experiences with sophisticated gala dinners and award ceremonies.</p>`,
          },
          {
            name: "Holiday Parties",
            description: `<p>Spread festive cheer and camaraderie with lively holiday celebrations and themed parties.</p>`,
          },
        ],
      },
      {
        name: "Educational Programs",
        slug: "educational-programs",
        img: "/img/services/educational-programs.jpg",
        list: [
          "Seminars",
          "Training",
          "Field trips",
          "Summer camps",
          "Live-in programs",
          "Leader camps",
        ],
        description: `<p>Inspire learning and growth with our tailored educational programs designed for schools and institutions. Our offerings include seminars, training, field trips, summer camps, live-in programs, and leader camps.</p>`,
        detailedServices: [
          {
            name: "Seminars",
            description: `<p>Engage students and educators with informative and interactive seminar sessions.</p>`,
          },
          {
            name: "Training",
            description: `<p>Provide hands-on training and skill development opportunities for students and staff.</p>`,
          },
          {
            name: "Field Trips",
            description: `<p>Explore new environments and experiences with enriching field trips and excursions.</p>`,
          },
          {
            name: "Summer Camps",
            description: `<p>Foster creativity and teamwork with fun-filled summer camp experiences.</p>`,
          },
          {
            name: "Live-in Programs",
            description: `<p>Promote social responsibility and community engagement with immersive live-in experiences.</p>`,
          },
          {
            name: "Leader Camps",
            description: `<p>Develop leadership skills and empower future leaders with dynamic leader camp initiatives.</p>`,
          },
        ],
      },
      {
        name: "Social Impact Programs",
        slug: "social-impact-programs",
        img: "/img/services/social-impact-programs.jpg",
        list: ["Corporate Social Responbility (CSR)", "Live-in programs"],
        description: `<p>Make a difference in your community with our impactful social impact programs. Our offerings include Corporate Social Responsibility (CSR) and live-in programs.</p>`,
        detailedServices: [
          {
            name: "Corporate Social Responsibility (CSR)",
            description: `<p>Engage your team in meaningful CSR initiatives that contribute to social welfare and environmental sustainability.</p>`,
          },
          {
            name: "Live-In Programs",
            description: `<p>Foster personal development and community engagement through immersive live-in experiences that promote socializing and contribution to local communities.</p>`,
          },
        ],
      },
      {
        name: "Event & Brand Management",
        slug: "event-and-brand-management",
        img: "/img/services/event-and-brand-management.jpg",
        list: [
          "On-site management",
          "Accommodations",
          "Brand activation",
          "Production",
          "and many more",
        ],
        description: `<p>Let us take care of every aspect of your event and brand management needs.</p>`,
        detailedServices: [
          {
            name: "Event Management",
            description: `
              <ul>
                <li>Handle logistics, venue design, and stage setup.</li>
                <li>Create event rundowns and manage key opinion leaders (KOLs).</li>
                <li>Arrange transportation, accommodations, and visas for international and local guests.</li>
                <li>Ensure compliance with event permits and crowd management protocols.</li>
              </ul>
            `,
          },
          {
            name: "Brand Management",
            description: `
              <ul>
                <li>Design brand visuals and logos.</li>
                <li>Conceptualize brand activations and launches.</li>
                <li>Plan comprehensive marketing strategies including EDM, social media, telemarketing, and more.</li>
                <li>Provide talent production services such as MCs, promoters, and liaisons.</li>
              </ul>
            `,
          },
        ],
      },
    ],

    individualServices: [
      {
        title: "Venue Finding",
        description:
          "Discover ideal venues effortlessly. We handle search, decoration, and coordination for a stress-free event.",
        icon: "location",
        color: "#F43F5E",
      },
      {
        title: "Hotel Finding",
        description:
          "Enjoy exclusive hotel deals across Indonesia. We secure accommodations for a comfortable stay.",
        icon: "building-4",
        color: "#F97316",
      },
      {
        title: "Hotel Booking Service",
        description:
          "Relax as we manage hotel bookings efficiently, ensuring a hassle-free experience for your guests.",
        icon: "bookmark",
        color: "#0EA5E9",
      },
      {
        title: "Ground Handling",
        description:
          "Arrive and depart in style with our comprehensive ground handling services.",
        icon: "heart-tick",
        color: "#F59E0B",
      },
      {
        title: "Budget Management",
        description:
          "Maximize your budget with our expert negotiation skills and extensive network.",
        icon: "wallet",
        color: "#22C55E",
      },
      {
        title: "Production Team",
        description:
          "Turn your vision into reality with our professional team. From decorations to sound, we create captivating atmospheres.",
        icon: "shapes",
        color: "#3B82F6",
      },
      {
        title: "Creative Design",
        description:
          "Make a lasting impression with our creative designs tailored to your event's theme.",
        icon: "designtools",
        color: "#6366F1",
      },
      {
        title: "Talent Production",
        description:
          "Impress guests with curated talent suited to your event's ambiance and goals.",
        icon: "magic-star",
        color: "#FACC15",
      },
      {
        title: "Show Management",
        description:
          "We ensure seamless event execution from registration to conclusion, leaving you to enjoy the show.",
        icon: "grid-3",
        color: "#A3E635",
      },
      {
        title: "On-site Management",
        description:
          "Leave logistics to us. Our team ensures smooth on-site operations.",
        icon: "setting",
        color: "#A855F7",
      },
      {
        title: "Participant Management",
        description:
          "Participant registration, real-time attendance monitoring, seating arrangements, timely reminders, engagement enhancement, group communication, and day-to-day itinerary management.",
        icon: "people",
        color: "#14B8A6",
      },
      {
        title: "Documentation & Data Reporting",
        description:
          "Capture every moment and gather valuable insights with our comprehensive documentation services such as guest / attendee data, photography, videography, video editing.",
        icon: "camera",
        color: "#EC4899",
      },
    ],
  }),

  getters: {
    getServiceBySlug: (state) => (slug) => {
      return state.services.find((service) => service.slug === slug);
    },
  },
});
