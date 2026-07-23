<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

use Zappzarapp\DevToolbar\DataCollectors\CollectorFactory;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\DevToolbar;
use Zappzarapp\DevToolbar\Guard\EnvironmentGuard;
use Zappzarapp\DevToolbar\Guard\GuardInterface;
use Zappzarapp\DevToolbar\Renderers\Panels\PanelRendererFactory;
use Zappzarapp\DevToolbar\Renderers\Panels\PanelRendererInterface;
use Zappzarapp\Security\Csp\Nonce\NonceProvider;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Composition root for the Developer Toolbar.
 *
 * Bundles everything {@see DevToolbar} needs, each
 * with a sensible default, so consumers can override only what they care
 * about (a custom collector set, a framework-specific guard, an injected
 * nonce provider) without a widening constructor signature.
 */
final readonly class ToolbarConfig
{
    /** @var array<string, CollectorInterface> */
    public array $collectors;

    /** @var array<string, PanelRendererInterface> */
    public array $panels;

    public MiniBarConfigSource $configSource;

    public GuardInterface $guard;

    public NonceProvider $nonce;

    /**
     * @param array<string, CollectorInterface>|null     $collectors   Default: CollectorFactory::createDefault()
     * @param array<string, PanelRendererInterface>|null $panels       Default: PanelRendererFactory::createDefault()
     * @param MiniBarConfigSource|null                   $configSource Default: CookieConfigSource::fromGlobals()
     * @param GuardInterface|null                        $guard        Default: EnvironmentGuard (fail-closed)
     * @param NonceProvider|null                         $nonce        Default: NonceRegistry::generator()
     */
    public function __construct(
        ?array $collectors = null,
        ?array $panels = null,
        ?MiniBarConfigSource $configSource = null,
        ?GuardInterface $guard = null,
        ?NonceProvider $nonce = null,
    ) {
        $this->collectors   = $collectors ?? CollectorFactory::createDefault();
        $this->panels       = $panels ?? PanelRendererFactory::createDefault();
        $this->configSource = $configSource ?? CookieConfigSource::fromGlobals();
        $this->guard        = $guard ?? new EnvironmentGuard();
        $this->nonce        = $nonce ?? NonceRegistry::generator();
    }
}
