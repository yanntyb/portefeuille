<?php

namespace App\Domains\Analytics\Data\Simulation;

class CdiVsSasuSimulation
{
    public static function build(): Simulation
    {
        $objects = [
            // -- Parametres globaux (entrees) --
            new SimulationObject('salaire_brut', SimulationValue::euro(3862.50), null, []),
            new SimulationObject('taux_charges_patronales', SimulationValue::percent(0.42), null, []),

            // -- Calculs globaux (mensuel) --
            new SimulationObject('charges_patronales', SimulationValue::euro(0), null, [
                new SimulationStep('salaire_brut', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_charges_patronales', 'reference'),
            ]),
            new SimulationObject('cout_employeur_cdi', SimulationValue::euro(0), null, [
                new SimulationStep('salaire_brut', 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep('charges_patronales', 'reference'),
            ]),

            // -- Calculs globaux (annuel) --
            new SimulationObject('salaire_brut_annuel', SimulationValue::euro(0), null, [
                new SimulationStep('salaire_brut', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            new SimulationObject('cout_employeur_annuel', SimulationValue::euro(0), null, [
                new SimulationStep('cout_employeur_cdi', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),

            // -- CDI — Parametres --
            new SimulationObject('taux_charges_salariales', SimulationValue::percent(0.2145), 'CDI', []),
            new SimulationObject('tickets_resto_total', SimulationValue::euro(200), 'CDI', []),
            new SimulationObject('taux_part_salariale_tickets', SimulationValue::percent(0.50), 'CDI', []),

            // -- CDI — Calculs intermediaires --
            new SimulationObject('tickets_resto_part_salariale', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('tickets_resto_total', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_part_salariale_tickets', 'reference'),
            ]),
            new SimulationObject('charges_salariales', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('salaire_brut', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_charges_salariales', 'reference'),
            ]),
            new SimulationObject('net_avant_impot', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('salaire_brut', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('charges_salariales', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('tickets_resto_part_salariale', 'reference'),
            ]),

            // -- CDI — Bareme IR dynamique --
            new SimulationObject('net_avant_impot_annuel', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('net_avant_impot', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            new SimulationObject('ir_annuel_cdi', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('net_avant_impot_annuel', 'reference'),
                new SimulationStep('bareme_ir', 'function'),
            ]),
            new SimulationObject('taux_prelevement_source', SimulationValue::percent(0), 'CDI', [
                new SimulationStep('ir_annuel_cdi', 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep('net_avant_impot_annuel', 'reference'),
            ]),

            new SimulationObject('prelevement_source', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('net_avant_impot', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_prelevement_source', 'reference'),
            ]),
            new SimulationObject('net_paye', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('net_avant_impot', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('prelevement_source', 'reference'),
            ]),
            new SimulationObject('tickets_resto_part_employeur', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('tickets_resto_total', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('tickets_resto_part_salariale', 'reference'),
            ]),

            // -- CDI — Resultat --
            new SimulationObject('cdi_remuneration', SimulationValue::euro(0), 'CDI', [
                new SimulationStep('net_paye', 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep('tickets_resto_part_employeur', 'reference'),
            ]),

            // -- SASU — Parametres --
            new SimulationObject('remuneration_dirigeant', SimulationValue::euro(2088.07), 'SASU', []),
            new SimulationObject('taux_cotisations_dirigeant', SimulationValue::percent(0.22), 'SASU', []),
            new SimulationObject('taux_charges_entreprise', SimulationValue::percent(0.45), 'SASU', []),
            new SimulationObject('frais_fixes_mensuels', SimulationValue::euro(200), 'SASU', []),
            new SimulationObject('taux_flat_tax', SimulationValue::percent(0.314), 'SASU', []),
            new SimulationObject('jours_travailles', SimulationValue::plain(218), 'SASU', []),

            // -- SASU — TJM --
            new SimulationObject('tjm', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('cout_employeur_annuel', 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep('jours_travailles', 'reference'),
            ]),

            // -- SASU — Salaire dirigeant --
            new SimulationObject('cotisations_dirigeant', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('remuneration_dirigeant', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_cotisations_dirigeant', 'reference'),
            ]),
            new SimulationObject('net_dirigeant_avant_impot', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('remuneration_dirigeant', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('cotisations_dirigeant', 'reference'),
            ]),

            // -- SASU — Bareme IR dynamique --
            new SimulationObject('net_dirigeant_avant_impot_annuel', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('net_dirigeant_avant_impot', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            new SimulationObject('ir_annuel_dirigeant', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('net_dirigeant_avant_impot_annuel', 'reference'),
                new SimulationStep('bareme_ir', 'function'),
            ]),
            new SimulationObject('taux_prelevement_source_dirigeant', SimulationValue::percent(0), 'SASU', [
                new SimulationStep('ir_annuel_dirigeant', 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep('net_dirigeant_avant_impot_annuel', 'reference'),
            ]),

            new SimulationObject('prelevement_source_dirigeant', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('net_dirigeant_avant_impot', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_prelevement_source_dirigeant', 'reference'),
            ]),
            new SimulationObject('sasu_remuneration', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('net_dirigeant_avant_impot', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('prelevement_source_dirigeant', 'reference'),
            ]),

            // -- SASU — Cout entreprise & tresorerie --
            new SimulationObject('charges_entreprise', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('remuneration_dirigeant', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_charges_entreprise', 'reference'),
            ]),
            new SimulationObject('cout_total_dirigeant', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('remuneration_dirigeant', 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep('charges_entreprise', 'reference'),
            ]),
            new SimulationObject('resultat_avant_is', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('cout_employeur_cdi', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('cout_total_dirigeant', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('frais_fixes_mensuels', 'reference'),
            ]),

            // -- SASU — Bareme IS dynamique --
            new SimulationObject('resultat_avant_is_annuel', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('resultat_avant_is', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('12', 'value'),
            ]),
            new SimulationObject('is_annuel', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('resultat_avant_is_annuel', 'reference'),
                new SimulationStep('bareme_is', 'function'),
            ]),
            new SimulationObject('taux_is', SimulationValue::percent(0), 'SASU', [
                new SimulationStep('is_annuel', 'reference'),
                new SimulationStep('/', 'operator'),
                new SimulationStep('resultat_avant_is_annuel', 'reference'),
            ]),

            new SimulationObject('impot_societes', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('resultat_avant_is', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_is', 'reference'),
            ]),
            new SimulationObject('tresorerie_sasu', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('resultat_avant_is', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('impot_societes', 'reference'),
            ]),

            // -- SASU — Dividendes & resultat total --
            new SimulationObject('flat_tax', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('tresorerie_sasu', 'reference'),
                new SimulationStep('*', 'operator'),
                new SimulationStep('taux_flat_tax', 'reference'),
            ]),
            new SimulationObject('dividendes_nets', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('tresorerie_sasu', 'reference'),
                new SimulationStep('-', 'operator'),
                new SimulationStep('flat_tax', 'reference'),
            ]),
            new SimulationObject('sasu_remuneration_totale', SimulationValue::euro(0), 'SASU', [
                new SimulationStep('sasu_remuneration', 'reference'),
                new SimulationStep('+', 'operator'),
                new SimulationStep('dividendes_nets', 'reference'),
            ]),
        ];

        $percentages = [5, 10, 15, 20, 25, 30, 33, 35, 40, 45, 50];

        $scenarios = collect($percentages)
            ->map(fn (int $pct): SimulationScenario => new SimulationScenario(
                nom: "Augmentation de {$pct} %",
                overrides: [
                    new ScenarioOverride('salaire_brut', '*', number_format(1 + $pct / 100, 2, ',', ' ')),
                ],
            ))
            ->all();

        $hiddenFromScenario = collect($objects)
            ->filter(fn (SimulationObject $obj): bool => ! empty($obj->pipeline) && ! empty($obj->steps))
            ->map(fn (SimulationObject $obj): string => $obj->nom)
            ->reject(fn (string $name): bool => in_array($name, ['cdi_remuneration', 'sasu_remuneration_totale', 'tjm']))
            ->values()
            ->all();

        return new Simulation(
            nom: 'UVI - Salaire',
            objects: $objects,
            scenarios: $scenarios,
            pipelineNames: ['CDI', 'SASU'],
            hiddenFromScenario: $hiddenFromScenario,
        );
    }
}
