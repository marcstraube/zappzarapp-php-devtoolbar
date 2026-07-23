/**
 * Export Utilities for DevToolbar
 *
 * Functions for exporting request data as JSON.
 * Browser downloads are handled by @zappzarapp/browser-utils/download.
 */

import type { RequestData } from '../types/index.js';

/**
 * Export structure for DevToolbar request data
 */
export interface ExportData {
  toolbar_version: string;
  export_time: string;
  request_id: string;
  metadata: RequestData['metadata'];
  data: Record<string, unknown>; // JSON collector data
}

/**
 * Create export data structure from request
 *
 * Exports JSON collector data only.
 *
 * @param requestId Request ID
 * @param requestData Full request data from storage
 * @returns Export-ready JSON object
 *
 * @example
 * const exportData = exportRequestAsJson('req-123', requestData);
 * Downloader.json(exportData, 'devtoolbar-req-123.json');
 */
export function exportRequestAsJson(requestId: string, requestData: RequestData): ExportData {
  // Validate that JSON data is available
  if (!requestData.json_data) {
    throw new Error(`Cannot export request ${requestId}: No JSON data available`);
  }

  // Remove badge_counts from metadata (redundant information)
  const { badge_counts: _badge_counts, ...exportMetadata } = requestData.metadata;

  return {
    toolbar_version: '2.1.0',
    export_time: new Date().toISOString(),
    request_id: requestId,
    metadata: exportMetadata,
    data: requestData.json_data,
  };
}
