export default defineNuxtPlugin((nuxtApp) => {
  return {
    provide: {
      updateMetaThemeColor: () => {
        // Get actual color mode from localStorage
        const colorMode = localStorage.getItem("nuxt-color-mode") || "dark";

        // Dynamic theme color based on actual color mode
        const themeColor = colorMode === "light" ? "#ffffff" : "#09090b";

        const meta = document.querySelector("meta[name=theme-color]");

        if (meta) {
          meta.setAttribute("content", themeColor);
        } else {
          const newMeta = document.createElement("meta");
          newMeta.name = "theme-color";
          newMeta.content = themeColor;
          document.head.appendChild(newMeta);
        }
      },
    },
  };
});
