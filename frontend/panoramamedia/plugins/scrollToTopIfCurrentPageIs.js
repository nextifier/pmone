export default defineNuxtPlugin((nuxtApp) => {
  return {
    provide: {
      /**
       * Melakukan scroll ke atas jika path yang diberikan sama dengan path halaman saat ini.
       * @param {string} path - Path URL untuk dibandingkan (misal: '/news').
       */
      scrollToTopIfCurrentPageIs: (path) => {
        // Gunakan useRoute().path untuk perbandingan yang andal
        if (useRoute().path === path) {
          window.scrollTo({
            top: 0,
            behavior: "smooth",
          });
        }
      },
    },
  };
});
