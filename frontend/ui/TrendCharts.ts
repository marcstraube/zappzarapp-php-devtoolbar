/**
 * TrendCharts - SVG line chart renderer for performance trends
 *
 * Renders three stacked SVG line charts (Time, Memory, Queries) with:
 * - Shared X-axis (request sequence, up to 50 points)
 * - Independent Y-scales per chart
 * - Dashed threshold lines from configured thresholds
 * - Synchronized crosshair on hover with exact values
 * - Checkbox visibility toggles
 */

import { HtmlEscaper } from '@zappzarapp/browser-utils/html';
import { StorageManager } from '../storage/StorageManager.js';
import type { RequestMetadata } from '../types/index.js';

interface ChartMetric {
  key: 'time' | 'memory' | 'queries';
  label: string;
  unit: string;
  color: string;
  thresholdKey: 'time_ms' | 'memory_mb' | 'query_count';
  extract: (r: RequestMetadata) => number;
  format: (v: number) => string;
}

const CHART_HEIGHT = 70;
const CHART_PADDING_TOP = 8;
const CHART_PADDING_BOTTOM = 20;
const CHART_PADDING_LEFT = 50;
const CHART_PADDING_RIGHT = 16;

const METRICS: ChartMetric[] = [
  {
    key: 'time',
    label: 'Time',
    unit: 'ms',
    color: '#3b82f6',
    thresholdKey: 'time_ms',
    extract: (r) => r.time,
    format: (v) => `${v.toFixed(0)}ms`,
  },
  {
    key: 'memory',
    label: 'Memory',
    unit: 'MB',
    color: '#10b981',
    thresholdKey: 'memory_mb',
    extract: (r) => r.memory / 1024 / 1024,
    format: (v) => `${v.toFixed(1)}MB`,
  },
  {
    key: 'queries',
    label: 'Queries',
    unit: '',
    color: '#f59e0b',
    thresholdKey: 'query_count',
    extract: (r) => r.query_count,
    format: (v) => String(Math.round(v)),
  },
];

/**
 * Render performance trend charts into the container
 */
export function renderTrendCharts(container: HTMLElement, metaArray: RequestMetadata[]): void {
  if (metaArray.length < 2) {
    container.innerHTML =
      '<p class="dev-toolbar-trends-empty">Need at least 2 requests to show trends.</p>';
    return;
  }

  // Use up to 50 entries, oldest first
  const data = metaArray.slice(0, 50).reverse();
  const thresholds = StorageManager.getThresholds();
  const width = container.clientWidth || 500;

  let html = '<div class="dev-toolbar-trends-controls">';
  for (const metric of METRICS) {
    html += `<label class="dev-toolbar-trends-toggle">
      <input type="checkbox" data-trend-metric="${metric.key}" checked>
      <span class="dev-toolbar-trends-color" style="background:${metric.color}"></span>
      ${metric.label}
    </label>`;
  }
  html += '</div>';
  html += '<div class="dev-toolbar-trends-charts">';

  for (const metric of METRICS) {
    const values = data.map(metric.extract);
    const threshold = thresholds[metric.thresholdKey];
    const svg = buildChartSVG(metric, values, threshold, width);
    html += `<div class="dev-toolbar-trend-chart" data-trend-chart="${metric.key}">
      <div class="dev-toolbar-trend-chart-label">${metric.label}${metric.unit ? ` (${metric.unit})` : ''}</div>
      ${svg}
    </div>`;
  }

  html += '</div>';
  html += '<div class="dev-toolbar-trends-tooltip" id="dev-toolbar-trends-tooltip"></div>';

  container.innerHTML = html;

  attachChartInteractions(container, data);
}

/**
 * Build an SVG line chart for a single metric
 */
function buildChartSVG(
  metric: ChartMetric,
  values: number[],
  threshold: number,
  containerWidth: number
): string {
  const plotWidth = containerWidth - CHART_PADDING_LEFT - CHART_PADDING_RIGHT;
  const plotHeight = CHART_HEIGHT - CHART_PADDING_TOP - CHART_PADDING_BOTTOM;
  const n = values.length;

  const min = Math.min(...values);
  const max = Math.max(...values);
  // Include threshold in range if it's visible
  const rangeMax = Math.max(max, threshold) * 1.1;
  const rangeMin = Math.min(min, 0);
  const range = rangeMax - rangeMin || 1;

  const xStep = n > 1 ? plotWidth / (n - 1) : 0;

  // Build polyline points
  const points = values
    .map((v, i) => {
      const x = CHART_PADDING_LEFT + i * xStep;
      const y = CHART_PADDING_TOP + plotHeight - ((v - rangeMin) / range) * plotHeight;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');

  // Threshold line Y position
  const thresholdY = CHART_PADDING_TOP + plotHeight - ((threshold - rangeMin) / range) * plotHeight;

  // Y-axis labels (min, mid, max)
  const midVal = (rangeMin + rangeMax) / 2;
  const midY = CHART_PADDING_TOP + plotHeight / 2;

  let svg = `<svg class="dev-toolbar-trend-svg" data-metric="${metric.key}" width="100%" height="${CHART_HEIGHT}" viewBox="0 0 ${containerWidth} ${CHART_HEIGHT}" preserveAspectRatio="none">`;

  // Grid lines
  svg += `<line x1="${CHART_PADDING_LEFT}" y1="${CHART_PADDING_TOP}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${CHART_PADDING_TOP}" class="dev-toolbar-trend-grid"/>`;
  svg += `<line x1="${CHART_PADDING_LEFT}" y1="${midY}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${midY}" class="dev-toolbar-trend-grid"/>`;
  svg += `<line x1="${CHART_PADDING_LEFT}" y1="${CHART_PADDING_TOP + plotHeight}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${CHART_PADDING_TOP + plotHeight}" class="dev-toolbar-trend-grid"/>`;

  // Threshold line
  if (threshold > rangeMin && threshold < rangeMax) {
    svg += `<line x1="${CHART_PADDING_LEFT}" y1="${thresholdY.toFixed(1)}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${thresholdY.toFixed(1)}" class="dev-toolbar-trend-threshold" stroke="${metric.color}"/>`;
    svg += `<text x="${CHART_PADDING_LEFT + plotWidth + 2}" y="${thresholdY.toFixed(1)}" class="dev-toolbar-trend-threshold-label" fill="${metric.color}">${metric.format(threshold)}</text>`;
  }

  // Y-axis labels
  svg += `<text x="${CHART_PADDING_LEFT - 6}" y="${CHART_PADDING_TOP + 4}" class="dev-toolbar-trend-axis-label" text-anchor="end">${metric.format(rangeMax)}</text>`;
  svg += `<text x="${CHART_PADDING_LEFT - 6}" y="${midY + 4}" class="dev-toolbar-trend-axis-label" text-anchor="end">${metric.format(midVal)}</text>`;
  svg += `<text x="${CHART_PADDING_LEFT - 6}" y="${CHART_PADDING_TOP + plotHeight + 4}" class="dev-toolbar-trend-axis-label" text-anchor="end">${metric.format(rangeMin)}</text>`;

  // Data line
  svg += `<polyline points="${points}" class="dev-toolbar-trend-line" stroke="${metric.color}" fill="none"/>`;

  // Data dots (for hover targets)
  values.forEach((v, i) => {
    const x = CHART_PADDING_LEFT + i * xStep;
    const y = CHART_PADDING_TOP + plotHeight - ((v - rangeMin) / range) * plotHeight;
    svg += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="3" class="dev-toolbar-trend-dot" fill="${metric.color}" data-index="${i}"/>`;
  });

  // Crosshair line (hidden by default)
  svg += `<line x1="0" y1="${CHART_PADDING_TOP}" x2="0" y2="${CHART_PADDING_TOP + plotHeight}" class="dev-toolbar-trend-crosshair" style="display:none"/>`;

  // Hover value label + highlight dot (positioned dynamically)
  svg += `<circle cx="0" cy="0" r="4" class="dev-toolbar-trend-highlight" fill="${metric.color}" style="display:none"/>`;
  svg += `<text x="0" y="0" class="dev-toolbar-trend-value-label" fill="${metric.color}" style="display:none"></text>`;

  svg += '</svg>';
  return svg;
}

/**
 * Attach hover interactions for synchronized crosshairs
 */
function attachChartInteractions(container: HTMLElement, data: RequestMetadata[]): void {
  const svgs = container.querySelectorAll<SVGSVGElement>('.dev-toolbar-trend-svg');
  const tooltip = container.querySelector<HTMLElement>('#dev-toolbar-trends-tooltip');
  const n = data.length;

  // Toggle chart visibility
  container.querySelectorAll<HTMLInputElement>('[data-trend-metric]').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      const metric = checkbox.dataset.trendMetric;
      const chart = container.querySelector<HTMLElement>(`[data-trend-chart="${metric}"]`);
      if (chart) {
        chart.style.display = checkbox.checked ? '' : 'none';
      }
    });
  });

  // Synchronized hover
  svgs.forEach((svg) => {
    svg.addEventListener('mousemove', (e: MouseEvent) => {
      const rect = svg.getBoundingClientRect();
      const svgWidth = rect.width;
      const scaleX = svgWidth > 0 ? svg.viewBox.baseVal.width / svgWidth : 1;
      const mouseX = (e.clientX - rect.left) * scaleX;

      const plotWidth = svg.viewBox.baseVal.width - CHART_PADDING_LEFT - CHART_PADDING_RIGHT;
      const xStep = n > 1 ? plotWidth / (n - 1) : 0;
      const relX = mouseX - CHART_PADDING_LEFT;
      const index = Math.round(relX / (xStep || 1));

      if (index < 0 || index >= n) {
        hideCrosshairs(svgs);
        if (tooltip) tooltip.style.display = 'none';
        return;
      }

      const xPos = CHART_PADDING_LEFT + index * xStep;
      showCrosshairs(svgs, xPos, index, data[index]!);
      showTooltip(tooltip, data[index]!, e, container);
    });

    svg.addEventListener('mouseleave', () => {
      hideCrosshairs(svgs);
      if (tooltip) tooltip.style.display = 'none';
    });
  });
}

function showCrosshairs(
  svgs: NodeListOf<SVGSVGElement>,
  x: number,
  index: number,
  request: RequestMetadata
): void {
  svgs.forEach((svg) => {
    const crosshair = svg.querySelector<SVGLineElement>('.dev-toolbar-trend-crosshair');
    if (crosshair) {
      crosshair.setAttribute('x1', x.toFixed(1));
      crosshair.setAttribute('x2', x.toFixed(1));
      crosshair.style.display = '';
    }

    // Position highlight dot and value label at the data point
    const metricKey = svg.dataset.metric as 'time' | 'memory' | 'queries';
    const metric = METRICS.find((m) => m.key === metricKey);
    if (!metric) return;

    const dot = svg.querySelector<SVGCircleElement>('.dev-toolbar-trend-highlight');
    const label = svg.querySelector<SVGTextElement>('.dev-toolbar-trend-value-label');
    const dataDot = svg.querySelector<SVGCircleElement>(
      `.dev-toolbar-trend-dot[data-index="${index}"]`
    );

    if (dot && dataDot) {
      const cy = dataDot.getAttribute('cy') ?? '0';
      dot.setAttribute('cx', x.toFixed(1));
      dot.setAttribute('cy', cy);
      dot.style.display = '';
    }

    if (label) {
      const value = metric.extract(request);
      const cy = dataDot?.getAttribute('cy') ?? '0';
      const labelY = parseFloat(cy) - 8;
      label.setAttribute('x', x.toFixed(1));
      label.setAttribute('y', labelY.toFixed(1));
      label.textContent = metric.format(value);
      label.style.display = '';
    }
  });
}

function hideCrosshairs(svgs: NodeListOf<SVGSVGElement>): void {
  svgs.forEach((svg) => {
    const crosshair = svg.querySelector<SVGLineElement>('.dev-toolbar-trend-crosshair');
    if (crosshair) {
      crosshair.style.display = 'none';
    }
    const dot = svg.querySelector<SVGCircleElement>('.dev-toolbar-trend-highlight');
    if (dot) dot.style.display = 'none';
    const label = svg.querySelector<SVGTextElement>('.dev-toolbar-trend-value-label');
    if (label) label.style.display = 'none';
  });
}

function showTooltip(
  tooltip: HTMLElement | null,
  request: RequestMetadata,
  event: MouseEvent,
  container: HTMLElement
): void {
  if (!tooltip) return;

  const time = request.time.toFixed(0);
  const memory = (request.memory / 1024 / 1024).toFixed(1);
  const queries = request.query_count;

  tooltip.innerHTML = `<strong>${HtmlEscaper.escape(request.method)} ${HtmlEscaper.escape(request.uri)}</strong><br>
    Time: ${time}ms | Memory: ${memory}MB | Queries: ${queries}`;
  tooltip.style.display = 'block';

  // Position near cursor
  const containerRect = container.getBoundingClientRect();
  const x = event.clientX - containerRect.left;
  const y = event.clientY - containerRect.top;

  tooltip.style.left = `${x + 12}px`;
  tooltip.style.top = `${y - 10}px`;

  // Prevent overflow right
  const tooltipRect = tooltip.getBoundingClientRect();
  if (tooltipRect.right > containerRect.right) {
    tooltip.style.left = `${x - tooltipRect.width - 12}px`;
  }
}
