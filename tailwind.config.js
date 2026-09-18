/** Tailwind (estático). Antes o site compilava isto no navegador via CDN (~400 KB de JS bloqueante).
 *  Build: npm run css   (gera public/assets/css/app.css — commitar o resultado). */
module.exports = {
  content: [
    './app/views/**/*.php',   // inclui app/views/admin (painel usa o mesmo CSS)
    './public/assets/js/novare.js',
    './public/api/*.php',
  ],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        "outline-variant": "#bec8d1",
        "on-primary": "#ffffff",
        "surface-container-lowest": "#ffffff",
        "inverse-on-surface": "#f0f0f3",
        "on-tertiary-fixed": "#2a1700",
        "surface-container-high": "#e8e8ea",
        "tertiary": "#845400",
        "on-secondary-fixed-variant": "#264a62",
        "primary-fixed-dim": "#88ceff",
        "on-surface-variant": "#3e4850",
        "surface": "#f9f9fc",
        "surface-container": "#eeeef0",
        "inverse-primary": "#88ceff",
        "surface-bright": "#f9f9fc",
        "surface-container-low": "#f3f3f6",
        "secondary-fixed-dim": "#a7cbe7",
        "on-surface": "#1a1c1e",
        "error-container": "#ffdad6",
        "on-error": "#ffffff",
        "on-primary-fixed": "#001e2f",
        "on-primary-container": "#00344d",
        "primary-container": "#24a1e0",
        "tertiary-fixed": "#ffddb7",
        "on-primary-fixed-variant": "#004c6e",
        "inverse-surface": "#2f3133",
        "on-tertiary-container": "#462a00",
        "on-error-container": "#93000a",
        "error": "#ba1a1a",
        "tertiary-container": "#d1880c",
        "background": "#f9f9fc",
        "surface-container-highest": "#e2e2e5",
        "surface-dim": "#dadadc",
        "secondary-fixed": "#c8e6ff",
        "secondary": "#3f627b",
        "primary-fixed": "#c8e6ff",
        "surface-variant": "#e2e2e5",
        "on-secondary-fixed": "#001e2f",
        "secondary-container": "#bde1fe",
        "outline": "#6f7881",
        "on-background": "#1a1c1e",
        "primary": "#006590",
        "tertiary-fixed-dim": "#ffb95b",
        "on-secondary-container": "#42657d",
        "on-secondary": "#ffffff",
        "on-tertiary": "#ffffff",
        "on-tertiary-fixed-variant": "#643f00",
        "surface-tint": "#006590"
      },
      borderRadius: {
        "DEFAULT": "0.25rem",
        "lg": "0.5rem",
        "xl": "0.75rem",
        "full": "9999px"
      },
      fontFamily: {
        "headline": ["Inter"],
        "body": ["Inter"],
        "label": ["Inter"]
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/container-queries'),
  ],
};
