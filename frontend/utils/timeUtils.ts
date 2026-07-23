/**
 * Time Utilities for DevToolbar
 *
 * Functions for formatting timestamps, time-ago strings, and sparkline charts.
 */

/**
 * Convert Unix timestamp to human-readable "time ago" string
 *
 * @param timestamp Unix timestamp in seconds
 * @returns Human-readable time ago (e.g., "5s ago", "3m ago", "2h ago")
 *
 * @example
 * timeAgo(Date.now() / 1000 - 30) // "30s ago"
 * timeAgo(Date.now() / 1000 - 120) // "2m ago"
 */
export function timeAgo(timestamp: number): string {
  const seconds = Math.floor(Date.now() / 1000 - timestamp);

  if (seconds < 60) return `${seconds}s ago`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  return `${Math.floor(seconds / 86400)}d ago`;
}

/**
 * Format Unix timestamp as readable date/time string
 *
 * @param timestamp Unix timestamp in seconds
 * @returns Formatted date/time string (YYYY-MM-DD HH:MM:SS)
 *
 * @example
 * formatTimestamp(1706451755) // "2024-01-28 15:42:35"
 */
export function formatTimestamp(timestamp: number): string {
  const date = new Date(timestamp * 1000);

  // Format: "2026-01-28 15:42:35"
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

/**
 * Generate sparkline chart from numeric values
 *
 * Uses Unicode block characters to create an inline bar chart.
 *
 * @param values Array of numeric values
 * @returns Sparkline string (e.g., "▁▃▄▇█▆▃▂")
 *
 * @example
 * generateSparkline([1, 2, 3, 4, 5]) // "▁▂▄▆█"
 * generateSparkline([5, 5, 5]) // "▃▃▃" (all equal)
 * generateSparkline([]) // ""
 */
export function generateSparkline(values: number[] | null | undefined): string {
  if (!values || values.length === 0) {
    return '';
  }

  const ticks = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
  const min = Math.min(...values);
  const max = Math.max(...values);
  const range = max - min;

  // If all values are equal, use middle tick
  if (range === 0) {
    return (ticks[3] ?? '▄').repeat(values.length);
  }

  let sparkline = '';
  values.forEach((value) => {
    const normalized = (value - min) / range;
    const index = Math.min(7, Math.floor(normalized * 8));
    sparkline += ticks[index] ?? '▁';
  });

  return sparkline;
}
