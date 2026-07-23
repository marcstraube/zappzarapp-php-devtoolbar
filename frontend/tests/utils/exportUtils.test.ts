/**
 * exportUtils Unit Tests
 *
 * Tests for JSON export data structure creation.
 * Browser download tests are in @zappzarapp/browser-utils.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect } from 'vitest';
import { exportRequestAsJson } from '@backend/DevToolbar/utils/exportUtils';
import { mockRequestMetadata } from '../mocks/browserMocks';
import type { RequestData } from '@backend/DevToolbar/types';

describe('exportUtils', () => {
  describe('exportRequestAsJson', () => {
    it('should create export data structure with correct fields', () => {
      const requestId = 'test-request-123';
      const rawData = {
        request: { method: 'GET', uri: '/', status_code: 200 },
        queries: { count: 5, queries: [] },
      };
      const requestData: RequestData = {
        id: requestId,
        metadata: mockRequestMetadata({ id: requestId }),
        tabs: {
          request: '<div>Request content</div>',
          database: '<div>Database queries</div>',
        },
        json_data: rawData,
      };

      const result = exportRequestAsJson(requestId, requestData);

      expect(result).toHaveProperty('toolbar_version', '2.1.0');
      expect(result).toHaveProperty('export_time');
      expect(result).toHaveProperty('request_id', requestId);
      expect(result).toHaveProperty('metadata');
      expect(result).toHaveProperty('data');
    });

    it('should include request metadata', () => {
      const metadata = mockRequestMetadata({
        method: 'POST',
        uri: '/api/test',
        status: 201,
      });

      const requestData: RequestData = {
        id: 'test-123',
        metadata,
        tabs: {},
        json_data: { request: {}, queries: {} },
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.metadata.method).toBe('POST');
      expect(result.metadata.uri).toBe('/api/test');
      expect(result.metadata.status).toBe(201);
    });

    it('should exclude badge_counts from metadata (redundant)', () => {
      const metadata = mockRequestMetadata({
        badge_counts: {
          request: 1,
          queries: 5,
          exceptions: 2,
        },
      });

      const requestData: RequestData = {
        id: 'test-123',
        metadata,
        tabs: {},
        json_data: { request: {}, queries: {} },
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.metadata).not.toHaveProperty('badge_counts');
      expect(result.metadata).toHaveProperty('method');
      expect(result.metadata).toHaveProperty('uri');
      expect(result.metadata).toHaveProperty('status');
    });

    it('should export JSON collector data', () => {
      const rawData = {
        request: { method: 'GET', uri: '/', status_code: 200 },
        queries: { count: 9, queries: [], n_plus_one: [] },
        exceptions: { count: 3, exceptions: [] },
        http: { count: 2, requests: [] },
      };

      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: {},
        json_data: rawData,
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.data).toEqual(rawData);
      expect(Object.keys(result.data)).toHaveLength(4);
    });

    it('should have valid ISO export_time', () => {
      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: {},
        json_data: {},
      };

      const result = exportRequestAsJson('test-123', requestData);

      // Should be valid ISO 8601 format
      expect(result.export_time).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/);
      expect(new Date(result.export_time).toString()).not.toBe('Invalid Date');
    });

    it('should handle empty collector data', () => {
      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: {},
        json_data: {},
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.data).toEqual({});
    });

    it('should export only JSON data (no HTML)', () => {
      const rawData = {
        request: { method: 'GET', uri: '/', status_code: 200 },
        queries: { count: 5, queries: [] },
        exceptions: { count: 0, exceptions: [] },
      };

      const requestData: RequestData = {
        id: 'test-123',
        metadata: mockRequestMetadata(),
        tabs: { request: '<div>HTML</div>' },
        json_data: rawData,
      };

      const result = exportRequestAsJson('test-123', requestData);

      expect(result.data).toEqual(rawData);
      expect(result).not.toHaveProperty('html_data');
    });

    it('should throw error when json_data is not available', () => {
      const requestData: RequestData = {
        id: 'test-no-json',
        metadata: mockRequestMetadata(),
        tabs: { request: '<div>HTML</div>' },
      };

      expect(() => exportRequestAsJson('test-no-json', requestData)).toThrow(
        'Cannot export request test-no-json: No JSON data available'
      );
    });
  });
});
