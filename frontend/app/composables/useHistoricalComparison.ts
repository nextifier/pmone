/**
 * Composable for comparing current analytics period with previous period.
 * Provides percentage changes and trend indicators.
 */
export function useHistoricalComparison() {
  /**
   * Calculate percentage change between two values.
   */
  function calculateChange(current: number, previous: number): number {
    if (previous === 0) return current > 0 ? 100 : 0;
    return ((current - previous) / previous) * 100;
  }

  /**
   * Format change as percentage string with + or - prefix.
   */
  function formatChange(change: number, decimals: number = 1): string {
    const formatted = Math.abs(change).toFixed(decimals);
    return change >= 0 ? `+${formatted}%` : `-${formatted}%`;
  }

  /**
   * Get trend direction.
   */
  function getTrend(change: number): "up" | "down" | "neutral" {
    if (Math.abs(change) < 0.1) return "neutral";
    return change > 0 ? "up" : "down";
  }

  /**
   * Get trend icon name.
   */
  function getTrendIcon(change: number): string {
    const trend = getTrend(change);
    if (trend === "up") return "hugeicons:arrow-up-01";
    if (trend === "down") return "hugeicons:arrow-down-01";
    return "hugeicons:minus-sign";
  }

  /**
   * Get trend color class.
   */
  function getTrendColor(change: number, inverseGood: boolean = false): string {
    const trend = getTrend(change);
    if (trend === "neutral") return "text-muted-foreground";

    // For metrics where increase is good (users, sessions, etc.)
    if (!inverseGood) {
      return trend === "up" ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400";
    }

    // For metrics where decrease is good (bounce rate, etc.)
    return trend === "down" ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400";
  }

  /**
   * Compare two metric objects and return comparison data.
   */
  function compareMetrics(
    current: Record<string, number>,
    previous: Record<string, number>
  ): Record<string, { current: number; previous: number; change: number; trend: string }> {
    const comparison: Record<string, any> = {};

    for (const key in current) {
      const currentValue = current[key] || 0;
      const previousValue = previous[key] || 0;
      const change = calculateChange(currentValue, previousValue);

      comparison[key] = {
        current: currentValue,
        previous: previousValue,
        change,
        changeFormatted: formatChange(change),
        trend: getTrend(change),
        trendIcon: getTrendIcon(change),
      };
    }

    return comparison;
  }

  return {
    calculateChange,
    formatChange,
    getTrend,
    getTrendIcon,
    getTrendColor,
    compareMetrics,
  };
}
