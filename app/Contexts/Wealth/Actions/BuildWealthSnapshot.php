<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Pages\DashboardPage;

/** Le volet tableau de bord de l'instantané hors-ligne : la page, toutes sections résolues. */
class BuildWealthSnapshot
{
    public function __construct(private DashboardPage $page) {}

    /** @return array<string, mixed> */
    public function __invoke(int $userId): array
    {
        return $this->page->for($userId)->resolve();
    }
}
