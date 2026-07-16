/**
 * Shared monochrome texture defs for the user-activity charts.
 *
 * The chart palette is grayscale (--chart-1..5); variety comes from chart TYPE
 * and texture, not colour. Each chart references a pattern/gradient via url(#id)
 * and passes the matching defs through :svg-defs. Keeping them here means the
 * two activity dashboards render one system instead of two drifting copies.
 */

export const areaGlowDefs = `
  <linearGradient id="ua-area-fill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--chart-1)" stop-opacity="0.4" />
    <stop offset="95%" stop-color="var(--chart-1)" stop-opacity="0" />
  </linearGradient>`;

export const dotDefs = `<pattern id="ua-dots" width="5" height="5" patternUnits="userSpaceOnUse">
  <rect width="5" height="5" fill="var(--chart-1)" opacity="0.1" />
  <circle cx="2.5" cy="2.5" r="1.2" fill="var(--chart-1)" opacity="0.55" /></pattern>`;

export const stripeDefs = `<pattern id="ua-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
  <rect width="6" height="6" fill="var(--chart-1)" opacity="0.05" />
  <path d="M0,6 L6,0" stroke="var(--chart-1)" stroke-width="0.8" opacity="0.35" /></pattern>`;

export const duotoneDefs = `<linearGradient id="ua-duotone" x1="0" y1="0" x2="1" y2="0">
  <stop offset="0%" stop-color="var(--chart-1)" stop-opacity="0.4" />
  <stop offset="100%" stop-color="var(--chart-1)" stop-opacity="1" /></linearGradient>`;

export const chartPadAngle = (2 * Math.PI) / 180;

const CHART_VARS = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
];

export const chartVar = (i) => CHART_VARS[i % CHART_VARS.length];
