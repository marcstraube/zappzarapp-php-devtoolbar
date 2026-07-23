/**
 * timeUtils Unit Tests
 *
 * Tests for time formatting, time-ago strings, and sparkline generation.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { timeAgo, formatTimestamp, generateSparkline } from '@backend/DevToolbar/utils/timeUtils';

describe('timeUtils', () => {
  describe('timeAgo', () => {
    beforeEach(() => {
      // Mock Date.now() for predictable testing
      vi.useFakeTimers();
      vi.setSystemTime(new Date('2026-01-29T12:00:00Z'));
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('should return seconds for timestamps < 60s ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 30; // 30 seconds ago
      expect(timeAgo(timestamp)).toBe('30s ago');
    });

    it('should return 0s for current timestamp', () => {
      const timestamp = Math.floor(Date.now() / 1000);
      expect(timeAgo(timestamp)).toBe('0s ago');
    });

    it('should return minutes for timestamps < 1h ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 180; // 3 minutes ago
      expect(timeAgo(timestamp)).toBe('3m ago');
    });

    it('should return minutes (59m) for 59 minutes ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 3540; // 59 minutes ago
      expect(timeAgo(timestamp)).toBe('59m ago');
    });

    it('should return hours for timestamps < 24h ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 7200; // 2 hours ago
      expect(timeAgo(timestamp)).toBe('2h ago');
    });

    it('should return hours (23h) for 23 hours ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 82800; // 23 hours ago
      expect(timeAgo(timestamp)).toBe('23h ago');
    });

    it('should return days for timestamps >= 24h ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 86400; // 1 day ago
      expect(timeAgo(timestamp)).toBe('1d ago');
    });

    it('should return days for multiple days ago', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 259200; // 3 days ago
      expect(timeAgo(timestamp)).toBe('3d ago');
    });

    it('should handle large time differences', () => {
      const timestamp = Math.floor(Date.now() / 1000) - 2592000; // ~30 days ago
      expect(timeAgo(timestamp)).toBe('30d ago');
    });
  });

  describe('formatTimestamp', () => {
    it('should format timestamp as YYYY-MM-DD HH:MM:SS', () => {
      // 2024-01-28 15:42:35 UTC
      const timestamp = 1706451755;
      const result = formatTimestamp(timestamp);

      // Result depends on timezone, but structure should be correct
      expect(result).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
    });

    it('should pad single-digit values with zeros', () => {
      // 2024-01-05 03:05:09 UTC
      const timestamp = 1704423909;
      const result = formatTimestamp(timestamp);

      // Should have correct format
      expect(result).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);

      // Check that month/day are padded (result should contain "-01-" and "-05")
      const parts = result.split(/[- :]/);
      expect(parts[1]).toHaveLength(2); // Month
      expect(parts[2]).toHaveLength(2); // Day
      expect(parts[3]).toHaveLength(2); // Hour
      expect(parts[4]).toHaveLength(2); // Minute
      expect(parts[5]).toHaveLength(2); // Second
    });

    it('should handle epoch timestamp (0)', () => {
      const result = formatTimestamp(0);
      expect(result).toMatch(/^1970-01-01 /); // Epoch start
    });

    it('should handle recent timestamp correctly', () => {
      // 2026-01-29 12:00:00 UTC
      const timestamp = 1769673600;
      const result = formatTimestamp(timestamp);

      // Should contain year 2026
      expect(result).toContain('2026');
      expect(result).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
    });
  });

  describe('generateSparkline', () => {
    it('should return empty string for empty array', () => {
      expect(generateSparkline([])).toBe('');
    });

    it('should return empty string for null/undefined', () => {
       
      expect(generateSparkline(null as any)).toBe('');
       
      expect(generateSparkline(undefined as any)).toBe('');
    });

    it('should use middle tick for equal values', () => {
      const result = generateSparkline([5, 5, 5, 5]);
      // When range is 0, use ticks[3] = '▄'
      expect(result).toBe('▄▄▄▄');
    });

    it('should generate sparkline for ascending values', () => {
      const result = generateSparkline([1, 2, 3, 4, 5]);
      // Minimum value (1) → ▁, maximum (5) → █
      expect(result[0]).toBe('▁'); // Min
      expect(result[4]).toBe('█'); // Max
      expect(result.length).toBe(5);
    });

    it('should generate sparkline for descending values', () => {
      const result = generateSparkline([5, 4, 3, 2, 1]);
      // Maximum value (5) → █, minimum (1) → ▁
      expect(result[0]).toBe('█'); // Max
      expect(result[4]).toBe('▁'); // Min
      expect(result.length).toBe(5);
    });

    it('should handle single value', () => {
      const result = generateSparkline([10]);
      expect(result).toBe('▄'); // Middle tick (range is 0, ticks[3])
    });

    it('should handle two values', () => {
      const result = generateSparkline([1, 10]);
      expect(result[0]).toBe('▁'); // Min
      expect(result[1]).toBe('█'); // Max
    });

    it('should normalize values correctly', () => {
      const result = generateSparkline([0, 50, 100]);
      expect(result[0]).toBe('▁'); // 0% → index 0
      expect(result[1]).toBe('▅'); // 50% → normalized=0.5, floor(0.5*8)=4, ticks[4]='▅'
      expect(result[2]).toBe('█'); // 100% → max
    });

    it('should handle negative values', () => {
      const result = generateSparkline([-5, 0, 5]);
      expect(result[0]).toBe('▁'); // Min (-5) → normalized=0
      expect(result[1]).toBe('▅'); // Middle (0) → normalized=0.5, floor(0.5*8)=4
      expect(result[2]).toBe('█'); // Max (5) → normalized=1
    });

    it('should handle decimal values', () => {
      const result = generateSparkline([1.5, 2.7, 3.9]);
      expect(result.length).toBe(3);
      expect(result[0]).toBe('▁'); // Min
      expect(result[2]).toBe('█'); // Max
    });

    it('should handle large values', () => {
      const result = generateSparkline([1000, 2000, 3000]);
      expect(result.length).toBe(3);
      expect(result[0]).toBe('▁');
      expect(result[2]).toBe('█');
    });

    it('should use 8 different tick levels', () => {
      const result = generateSparkline([0, 1, 2, 3, 4, 5, 6, 7]);
      // Each value should map to a different tick
      const uniqueTicks = new Set(result.split(''));
      expect(uniqueTicks.size).toBeGreaterThanOrEqual(7); // At least 7 different ticks
    });
  });
});
