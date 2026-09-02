<?php

namespace App\Shared\Inertia;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Les props d'une page en deux tas : celles qui partent avec le document, celles qui arrivent
 * après. Le contrôleur enveloppe les secondes dans `Inertia::defer()`, l'instantané hors-ligne les
 * appelle. Une seule définition sert les deux, là où `BuildPortfolioViewSnapshot` recopiait le
 * corps de chaque contrôleur.
 */
final readonly class PageProps
{
    /**
     * @param  array<string, mixed>  $sync
     * @param  array<string, DeferredProp>  $deferred
     */
    public function __construct(public array $sync, public array $deferred) {}

    /**
     * Sync tel quel ; chaque différée devient un `DeferProp` de son groupe.
     *
     * @return array<string, mixed>
     */
    public function toInertiaProps(): array
    {
        $props = $this->sync;

        foreach ($this->deferred as $key => $prop) {
            $props[$key] = Inertia::defer($prop->resolve, $prop->group);
        }

        return $props;
    }

    public function render(string $component): Response
    {
        return Inertia::render($component, $this->toInertiaProps());
    }

    /**
     * Le JSON que la page rendrait toutes sections dépliées : sync, puis chaque différée appelée.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $props = $this->sync;

        foreach ($this->deferred as $key => $prop) {
            $props[$key] = ($prop->resolve)();
        }

        return $props;
    }
}
