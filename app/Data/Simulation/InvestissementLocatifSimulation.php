<?php

namespace App\Data\Simulation;

class InvestissementLocatifSimulation
{
    public static function build(): Simulation
    {
        // -- Parametres partages (profil investisseur) --
        $sharedParams = [
            new SimulationObject('taux_interet_annuel', SimulationValue::percent(0.035), null, []),
            new SimulationObject('duree_credit_annees', SimulationValue::plain(20), null, []),
            new SimulationObject('taux_assurance_emprunteur', SimulationValue::percent(0.0034), null, []),
            new SimulationObject('tmi', SimulationValue::percent(0.30), null, []),
            new SimulationObject('taux_prelevements_sociaux', SimulationValue::percent(0.172), null, []),
        ];

        // -- Calculs partages --
        $sharedCalcs = [
            new SimulationObject('duree_credit_mois', SimulationValue::plain(0), [
                new SimulationStep('duree_credit_annees', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
        ];

        // -- Biens immobiliers --
        $bien1 = self::buildPropertyPipeline('Bien 1', 'bien1', [
            'prix_achat' => 60_000,
            'frais_notaire_taux' => 0.075,
            'travaux' => 0,
            'apport_personnel' => 15_000,
            'loyer_mensuel' => 650,
            'taux_vacance' => 0.05,
            'taxe_fonciere_annuelle' => 800,
            'charges_copropriete_annuelles' => 900,
            'assurance_pno_annuelle' => 150,
            'frais_gestion_taux' => 0.07,
            'provision_travaux_annuelle' => 400,
        ]);

        $bien2 = self::buildPropertyPipeline('Bien 2', 'bien2', [
            'prix_achat' => 80_000,
            'frais_notaire_taux' => 0.075,
            'travaux' => 0,
            'apport_personnel' => 15_000,
            'loyer_mensuel' => 650,
            'taux_vacance' => 0.05,
            'taxe_fonciere_annuelle' => 800,
            'charges_copropriete_annuelles' => 900,
            'assurance_pno_annuelle' => 150,
            'frais_gestion_taux' => 0.07,
            'provision_travaux_annuelle' => 400,
        ]);

        $objects = array_merge($sharedParams, $sharedCalcs, $bien1, $bien2);

        // -- Scenarios --
        $scenarios = [];

        // Variation des travaux
        foreach ([10_000, 20_000, 30_000] as $travaux) {
            $label = number_format($travaux, 0, ',', ' ');

            $scenarios[] = new SimulationScenario(
                nom: "Travaux {$label} €",
                overrides: [
                    new ScenarioOverride('travaux_bien1', '=', number_format($travaux, 2, ',', ' ').' €'),
                    new ScenarioOverride('travaux_bien2', '=', number_format($travaux, 2, ',', ' ').' €'),
                ],
            );
        }

        // -- hiddenFromScenario : masquer les intermediaires --
        $visibleNames = [
            'cash_flow_mensuel_bien1', 'cash_flow_mensuel_bien2',
            'rendement_net_net_bien1', 'rendement_net_net_bien2',
            'rendement_brut_bien1', 'rendement_brut_bien2',
        ];

        $hiddenFromScenario = collect($objects)
            ->filter(fn (SimulationObject $obj): bool => ! empty($obj->pipeline) && ! empty($obj->steps))
            ->map(fn (SimulationObject $obj): string => $obj->nom)
            ->reject(fn (string $name): bool => in_array($name, $visibleNames))
            ->values()
            ->all();

        return new Simulation(
            nom: 'Investissement Locatif',
            objects: $objects,
            scenarios: $scenarios,
            pipelineNames: ['Bien 1', 'Bien 2'],
            hiddenFromScenario: $hiddenFromScenario,
        );
    }

    /**
     * @param  array<string, float>  $defaults
     * @return list<SimulationObject>
     */
    private static function buildPropertyPipeline(string $pipelineName, string $prefix, array $defaults): array
    {
        $p = fn (string $name): string => "{$name}_{$prefix}";

        $percentParams = ['frais_notaire_taux', 'taux_vacance', 'frais_gestion_taux'];

        // Parametres du bien
        $params = [];
        foreach ($defaults as $name => $value) {
            if (in_array($name, $percentParams)) {
                $params[] = new SimulationObject($p($name), SimulationValue::percent($value), $pipelineName, []);
            } else {
                $params[] = new SimulationObject($p($name), SimulationValue::euro($value), $pipelineName, []);
            }
        }

        // Calculs du bien
        $calcs = [
            // 1. frais_notaire
            new SimulationObject($p('frais_notaire'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('prix_achat'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep($p('frais_notaire_taux'), 'reference'),
            ]),
            // 2. cout_acquisition
            new SimulationObject($p('cout_acquisition'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('prix_achat'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('frais_notaire'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('travaux'), 'reference'),
            ]),
            // 3. capital_emprunte
            new SimulationObject($p('capital_emprunte'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('cout_acquisition'), 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep($p('apport_personnel'), 'reference'),
            ]),
            // 4. mensualite_credit (function)
            new SimulationObject($p('mensualite_credit'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('capital_emprunte'), 'reference'),
                new SimulationStep('mensualite_credit', 'function'),
            ]),
            // 5. assurance_mensuelle
            new SimulationObject($p('assurance_mensuelle'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('capital_emprunte'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_assurance_emprunteur', 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            // 6. mensualite_totale
            new SimulationObject($p('mensualite_totale'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('mensualite_credit'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('assurance_mensuelle'), 'reference'),
            ]),
            // 7. taux_occupation (1 - taux_vacance)
            new SimulationObject($p('taux_occupation'), SimulationValue::percent(0), $pipelineName, [
                new SimulationStep('1', 'value'),
                new SimulationStep('-', 'operator'),
                new SimulationStep($p('taux_vacance'), 'reference'),
            ]),
            // 8. loyer_annuel_brut
            new SimulationObject($p('loyer_annuel_brut'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('loyer_mensuel'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            // 9. loyer_annuel_effectif
            new SimulationObject($p('loyer_annuel_effectif'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('loyer_annuel_brut'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep($p('taux_occupation'), 'reference'),
            ]),
            // 10. frais_gestion_annuels
            new SimulationObject($p('frais_gestion_annuels'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('loyer_annuel_effectif'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep($p('frais_gestion_taux'), 'reference'),
            ]),
            // 11. total_charges_annuelles
            new SimulationObject($p('total_charges_annuelles'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('taxe_fonciere_annuelle'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('charges_copropriete_annuelles'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('assurance_pno_annuelle'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('frais_gestion_annuels'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('provision_travaux_annuelle'), 'reference'),
            ]),
            // 12. remboursement_annuel
            new SimulationObject($p('remboursement_annuel'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('mensualite_totale'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            // 13. rendement_brut (%)
            new SimulationObject($p('rendement_brut'), SimulationValue::percent(0), $pipelineName, [
                new SimulationStep($p('loyer_annuel_brut'), 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep($p('cout_acquisition'), 'reference'),
            ]),
            // 14. revenu_imposable (micro-foncier, abattement 30%)
            new SimulationObject($p('revenu_imposable'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('loyer_annuel_effectif'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('0,70', 'value'),
            ]),
            // 15. impot
            new SimulationObject($p('impot'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('revenu_imposable'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('tmi', 'reference'),
            ]),
            // 16. prelevements_sociaux
            new SimulationObject($p('prelevements_sociaux'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('revenu_imposable'), 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_prelevements_sociaux', 'reference'),
            ]),
            // 17. fiscalite_totale
            new SimulationObject($p('fiscalite_totale'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('impot'), 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep($p('prelevements_sociaux'), 'reference'),
            ]),
            // 18. revenu_net
            new SimulationObject($p('revenu_net'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('loyer_annuel_effectif'), 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep($p('total_charges_annuelles'), 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep($p('fiscalite_totale'), 'reference'),
            ]),
            // 19. cash_flow_annuel
            new SimulationObject($p('cash_flow_annuel'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('revenu_net'), 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep($p('remboursement_annuel'), 'reference'),
            ]),
            // 20. cash_flow_mensuel (resultat cle)
            new SimulationObject($p('cash_flow_mensuel'), SimulationValue::euro(0), $pipelineName, [
                new SimulationStep($p('cash_flow_annuel'), 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            // 21. rendement_net_net (resultat cle, %)
            new SimulationObject($p('rendement_net_net'), SimulationValue::percent(0), $pipelineName, [
                new SimulationStep($p('revenu_net'), 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep($p('cout_acquisition'), 'reference'),
            ]),
        ];

        return array_merge($params, $calcs);
    }
}
