export default defineNuxtPlugin((nuxtApp) => {
  return {
    provide: {
      updateMetaThemeColor: () => {
        const themeColor = localStorage.getItem("color-mode") === "light" ? "#ffffff" : "#09090b";

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
