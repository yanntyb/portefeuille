<?php

namespace App\Shared\Inertia;

use Closure;

/**
 * Une prop différée d'une page : ce qui la calcule, et le groupe Inertia sous lequel le client la
 * redemande. Un groupe par section repliable, pour qu'un dépli ne calcule que sa section.
 */
final readonly class DeferredProp
{
    public function __construct(public Closure $resolve, public string $group) {}
}
