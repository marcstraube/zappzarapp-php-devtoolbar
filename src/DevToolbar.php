<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar;

use Random\RandomException;
use RuntimeException;
use Zappzarapp\DevToolbar\Config\MiniBarConfigSource;
use Zappzarapp\DevToolbar\Config\ToolbarConfig;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\Guard\GuardInterface;
use Zappzarapp\DevToolbar\Renderers\Panels\PanelRendererInterface;
use Zappzarapp\DevToolbar\Renderers\ToolbarInjector;
use Zappzarapp\Security\Csp\Exception\InvalidDirectiveValueException;
use Zappzarapp\Security\Csp\Nonce\NonceProvider;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Main Developer Toolbar class — the composition root.
 *
 * Constructed from a {@see ToolbarConfig} (collectors, panels, guard,
 * config source, nonce provider), each with a sensible default.
 * {@see getInstance()} remains a thin convenience wrapper over the default
 * configuration for the common front-controller pattern.
 */
class DevToolbar
{
    private static ?self $instance = null;

    /** @var array<string, CollectorInterface> */
    private array $collectors;

    /** @var array<string, PanelRendererInterface> */
    private array $panels;

    private readonly MiniBarConfigSource $configSource;

    private readonly GuardInterface $guard;

    private readonly NonceProvider $nonce;

    /** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PHP 8.4 hooks crash PDepend/PHPMD */
    private bool $booted = false;

    public function __construct(ToolbarConfig $config = new ToolbarConfig())
    {
        $this->collectors   = $config->collectors;
        $this->panels       = $config->panels;
        $this->configSource = $config->configSource;
        $this->guard        = $config->guard;
        $this->nonce        = $config->nonce;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get the shared singleton instance (default configuration).
     */
    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register an additional collector (and optionally its panel renderer).
     *
     * A collector registered without a panel falls back to the generic
     * key/value panel. Call before {@see boot()}.
     */
    public function addCollector(CollectorInterface $collector, ?PanelRendererInterface $panel = null): self
    {
        $name                    = $collector->getName();
        $this->collectors[$name] = $collector;

        if ($panel instanceof PanelRendererInterface) {
            $this->panels[$name] = $panel;
        }

        return $this;
    }

    /**
     * Boot the toolbar and start collecting data.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if (!$this->guard->isEnabled()) {
            return;
        }

        foreach ($this->collectors as $collector) {
            $collector->start();
        }

        $this->booted = true;
    }

    /**
     * Output buffer callback - injects toolbar HTML into response.
     *
     * Called automatically by the ob_start() callback when the buffer is
     * flushed. A secure alternative to echoing from a shutdown function.
     *
     * @param string $buffer Output buffer content
     * @return string Modified buffer with toolbar HTML injected
     * @throws RuntimeException If asset file cannot be loaded
     * @throws RandomException If secure random source is unavailable
     */
    public function injectToolbar(string $buffer): string
    {
        if (!$this->booted || !$this->guard->isEnabled()) {
            return $buffer;
        }

        foreach ($this->collectors as $collector) {
            $collector->stop();
        }

        // Only decorate HTML responses. A JSON/XML/plain-text body that happens
        // to contain the literal "</body>" must never receive the toolbar.
        if ($buffer === '' || !$this->isHtmlResponse(headers_list())) {
            return $buffer;
        }

        $injector = new ToolbarInjector(
            $this->collectors,
            $this->panels,
            $this->configSource,
            $this->nonce,
        );

        return $injector->inject($buffer);
    }

    /**
     * Decide whether the response is HTML from its headers.
     *
     * A missing Content-Type counts as HTML because PHP's default_mimetype is
     * text/html; an explicit non-HTML type disables injection.
     *
     * @param list<string> $headers Raw headers as returned by headers_list()
     */
    private function isHtmlResponse(array $headers): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return stripos($header, 'text/html') !== false;
            }
        }

        return true;
    }

    /**
     * Clear all collector state and un-boot.
     *
     * For long-running runtimes (Swoole, RoadRunner, queue workers): call
     * between requests so no state bleeds across them.
     */
    public function reset(): void
    {
        foreach ($this->collectors as $collector) {
            $collector->reset();
        }

        $this->booted = false;
    }

    /**
     * Get a specific collector.
     *
     * @param string $name Collector name
     * @return CollectorInterface|null Collector instance or null
     */
    public function getCollector(string $name): ?CollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    /**
     * Set the CSP nonce from an external source.
     *
     * Updates the shared NonceRegistry that the default nonce provider reads
     * from, so a host with its own CSP implementation can share its nonce.
     * Call before {@see boot()}. With a custom NonceProvider injected via
     * ToolbarConfig, manage the nonce through that provider instead.
     *
     * @param string $nonce External nonce value (base64-encoded recommended)
     * @throws InvalidDirectiveValueException If nonce contains invalid characters
     */
    public function setNonce(string $nonce): void
    {
        NonceRegistry::set($nonce);
    }
}
