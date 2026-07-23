/**
 * Simple happy-dom test
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect } from 'vitest';

describe('happy-dom environment', () => {
  it('should provide localStorage', () => {
    expect(localStorage).toBeDefined();
    expect(typeof localStorage.getItem).toBe('function');
    expect(typeof localStorage.setItem).toBe('function');
    expect(typeof localStorage.clear).toBe('function');
  });

  it('should allow localStorage operations', () => {
    localStorage.setItem('test', 'value');
    expect(localStorage.getItem('test')).toBe('value');
    localStorage.clear();
    expect(localStorage.getItem('test')).toBeNull();
  });
});
