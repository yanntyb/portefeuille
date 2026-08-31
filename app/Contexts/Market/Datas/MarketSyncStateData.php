<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\MarketSyncStatus;
use JsonSerializable;

/**
 * Ce que le bouton de synchronisation affiche : un statut, deux horodatages, et le mot de la fin —
 * résumé d'une réussite ou message d'échec, jamais les deux.
 *
 * Les horodatages sont des secondes Unix, comme `generatedAt` de l'instantané : le client les
 * multiplie par mille pour en faire des dates.
 */
readonly class MarketSyncStateData implements JsonSerializable
{
    public function __construct(
        public MarketSyncStatus $status,
        public ?int $startedAt = null,
        public ?int $finishedAt = null,
        public ?string $summary = null,
        public ?string $error = null,
    ) {}

    public static function idle(): self
    {
        return new self(MarketSyncStatus::Idle);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status->value,
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'summary' => $this->summary,
            'error' => $this->error,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Relecture depuis le cache. Un statut inconnu — clé écrite par une version précédente —
     * redevient `Idle` plutôt que de faire échouer la lecture du tableau de bord.
     *
     * @param  array<string, mixed>  $stored
     */
    public static function fromArray(array $stored): self
    {
        $status = MarketSyncStatus::tryFrom((string) ($stored['status'] ?? ''));

        if ($status === null) {
            return self::idle();
        }

        return new self(
            status: $status,
            startedAt: isset($stored['startedAt']) ? (int) $stored['startedAt'] : null,
            finishedAt: isset($stored['finishedAt']) ? (int) $stored['finishedAt'] : null,
            summary: isset($stored['summary']) ? (string) $stored['summary'] : null,
            error: isset($stored['error']) ? (string) $stored['error'] : null,
        );
    }
}
