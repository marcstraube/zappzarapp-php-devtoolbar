<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers;

/**
 * Interface for toolbar renderers
 */
interface RendererInterface
{
    /**
     * Render HTML output
     *
     * @return string HTML output
     */
    public function render(): string;
}
