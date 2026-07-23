<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers;

use Random\RandomException;
use RuntimeException;
use Zappzarapp\DevToolbar\Config\MiniBarConfigSource;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\DevToolbar;
use Zappzarapp\DevToolbar\Renderers\Panels\PanelRendererInterface;
use Zappzarapp\Security\Csp\Nonce\NonceProvider;

/**
 * Injects the rendered toolbar HTML into a response body.
 *
 * Not a PSR-15 middleware: this is an internal output-buffer post-processor
 * ({@see inject()} takes and returns a string). The Middleware namespace is
 * intentionally left free for a future real PSR-15 adapter.
 *
 * @internal Constructed by {@see DevToolbar}; not part
 *           of the stable public API.
 */
final readonly class ToolbarInjector
{
    /**
     * @param array<string, CollectorInterface>     $collectors keyed by collector name
     * @param array<string, PanelRendererInterface> $panels     panel renderers keyed by collector name
     */
    public function __construct(
        private array $collectors,
        private array $panels,
        private MiniBarConfigSource $configSource,
        private NonceProvider $nonce,
    ) {
    }

    /**
     * Inject toolbar HTML into the response output.
     *
     * Injects once, before the LAST </body> (case-insensitive), so pages that
     * legitimately contain the literal string more than once are not
     * decorated multiple times.
     *
     * @param string $output Response HTML
     * @return string Modified HTML with toolbar injected
     * @throws RuntimeException If asset file cannot be loaded
     * @throws RandomException If secure random source is unavailable
     */
    public function inject(string $output): string
    {
        $position = strripos($output, '</body>');
        if ($position === false) {
            return $output;
        }

        return substr($output, 0, $position)
            . $this->renderToolbar()
            . substr($output, $position);
    }

    /**
     * Render complete toolbar HTML (mini bar + panel + assets).
     *
     * All assets are inline and self-contained (no external dependencies).
     *
     * @return string Toolbar HTML
     * @throws RuntimeException If asset file cannot be loaded
     * @throws RandomException If secure random source is unavailable
     */
    private function renderToolbar(): string
    {
        // Resolve the mini bar config once; every renderer reads from it
        // instead of touching superglobals or the filesystem itself.
        $config        = $this->configSource->read();
        $panelRenderer = new PanelRenderer($this->collectors, $this->panels, $config->thresholds);

        $miniBar    = (new MiniBarRenderer($this->collectors, $config))->render();
        $panel      = $panelRenderer->render();
        $assets     = (new AssetsRenderer($this->nonce))->render();
        $dataScript = (new DataInjectionRenderer($this->collectors, $this->nonce, $config, $panelRenderer))->render();

        return $miniBar . $panel . $assets . $dataScript;
    }
}
