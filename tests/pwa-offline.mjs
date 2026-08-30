import assert from 'node:assert/strict';
import { chromium } from 'playwright';

/**
 * Précondition, à la charge de qui lance ce script : `https://argent.test` (la base de
 * **développement**, servie par Herd, jamais une base de test) doit porter un utilisateur dont le
 * revenu mensualisé (`[data-income-monthly]`, tableau de bord) n'est pas nul — `DividendDemoSeeder`
 * la satisfait (`php artisan db:seed --class=DividendDemoSeeder`).
 *
 * Ni une base de test dédiée, ni un semis automatique en tête de ce script : Playwright pilote un
 * vrai navigateur contre un vrai service worker, lui-même conditionné par la présence réelle de
 * `public/build` (voir la vérification `sw.js` ci-dessous) — deux choses qu'aucune base de test
 * éphémère (`RefreshDatabase`) ne reproduit d'un lancement à l'autre. Semer depuis ce script
 * écrirait dans cette même base de développement à chaque exécution, ce qui serait pire qu'une
 * précondition documentée : un script censé seulement lire finirait par la faire dériver.
 */
const BASE_URL = process.env.PWA_BASE_URL ?? 'https://argent.test';

/** Aucune attente de ce script ne doit rester ouverte indéfiniment : un pendu est pire qu'un échec. */
const TIMEOUT = 10_000;

const browser = await chromium.launch();
const context = await browser.newContext({ ignoreHTTPSErrors: true });
/** Borne par défaut tout ce que Playwright sait borner (evaluate, textContent…), pas seulement les appels qui le précisent explicitement. */
context.setDefaultTimeout(TIMEOUT);
const page = await context.newPage();

try {
    await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: TIMEOUT });

    /**
     * `public/hot` présent ou `public/sw-runtime.js` absent : la route `/sw.js` sert le worker
     * inerte, qui se désenregistre. Rien de ce que ce script vérifie ne serait alors vrai — on le
     * détecte tout de suite plutôt que de laisser les étapes suivantes échouer de façon obscure.
     */
    const swResponse = await page.request.get(`${BASE_URL}/sw.js`, { timeout: TIMEOUT });
    const swScript = await swResponse.text();
    assert.ok(
        !swScript.includes('unregister'),
        "Le worker inerte est servi. Arrête `bun run dev` et retire `public/hot`, puis relance `bun run build`.",
    );

    /** Le worker doit être actif et son précache rempli avant qu'on coupe le réseau. */
    await page.waitForFunction(() => navigator.serviceWorker.controller !== null, undefined, { timeout: TIMEOUT });
    await page.waitForFunction(
        () => caches.keys().then((names) => names.some((name) => name.startsWith('argent-'))),
        undefined,
        { timeout: TIMEOUT },
    );

    /**
     * La page analyse n'est jamais visitée par la suite de ce script sans cette étape : ses six
     * sections partagent le motif `Deferred`/`#rescue` de `PerformancesSection.vue`, et rien
     * d'autre ici ne prouve qu'elles se comportent pareil hors-ligne. `networkidle` attend aussi
     * les requêtes `__sw=partial` de ses quatre groupes différés, mises en cache par le worker au
     * même titre que la navigation elle-même.
     */
    await page.goto(`${BASE_URL}/actions/analyse`, { waitUntil: 'networkidle', timeout: TIMEOUT });
    await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: TIMEOUT });

    await context.setOffline(true);
    await page.reload({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });

    const body = await page.textContent('body');
    assert.ok(body.includes('Investi'), 'La page en cache doit rester lisible hors-ligne.');
    assert.ok(!body.includes('Pas de connexion'), 'La page hors-ligne ne doit pas remplacer une page en cache.');

    await page.waitForSelector('[data-pwa-banner="stale"]', { timeout: TIMEOUT });

    /**
     * Les partiels différés ont été mis en cache pendant la visite en ligne. On les retire pour
     * atteindre le cas que le slot `#rescue` couvrait avant la tâche 12 : la page est là, ses
     * groupes ne le sont pas.
     */
    await context.setOffline(false);
    await page.evaluate(async () => {
        const [name] = (await caches.keys()).filter((key) => key.startsWith('argent-'));
        const cache = await caches.open(name);
        const keys = await cache.keys();

        await Promise.all(
            keys
                .filter((request) => request.url.includes('__sw=partial'))
                .map((request) => cache.delete(request)),
        );
    });
    await context.setOffline(true);
    await page.reload({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });

    /**
     * Avant la tâche 12, vider le cache des partiels forçait ici le slot `#rescue` d'Inertia à
     * s'afficher : « Données indisponibles hors-ligne » était le texte attendu. Depuis la tâche 12,
     * `WealthEvolutionSection` et `WealthIncomeSection` — les deux seules sections différées du
     * tableau de bord — rendent d'abord la valeur fusionnée avec l'instantané (`aheadOfNetwork`),
     * déjà en mémoire à ce stade puisque `/instantane` a réussi pendant la visite en ligne. Le slot
     * `#rescue` n'est donc plus jamais atteint ici : cette étape vérifie maintenant que l'instantané
     * comble bien l'absence des partiels, au lieu de vérifier que la page s'en excuse.
     *
     * Précondition positive avant la négative : `[data-income-monthly]` ne prouve l'absence du
     * rescapage que si la section a bien rendu de vraies données ; sans cette attente, l'assertion
     * négative virerait au vert même si les deux sections avaient disparu.
     */
    /** Revenus est repliée à l'arrivée : son chiffre ne se lit qu'une fois le pli ouvert. */
    await page.click('[data-section="wealth-income"] [data-section-toggle]');
    await page.waitForSelector('[data-income-monthly]', { timeout: TIMEOUT });
    await page.waitForFunction(
        () => document.querySelector('[data-section="wealth-evolution"] .animate-pulse') === null,
        undefined,
        { timeout: TIMEOUT },
    );

    const dashboardBody = await page.textContent('body');
    assert.ok(
        !dashboardBody.includes('Données indisponibles hors-ligne'),
        'L\'instantané doit combler Évolution et Revenus, pas les renvoyer au slot #rescue.',
    );

    /**
     * La page analyse a été visitée en ligne plus haut (son cache de partiels vient d'être vidé
     * avec celui du tableau de bord, au même titre) : elle est donc dans le même cas que le
     * tableau de bord ci-dessus, page en cache, groupes différés absents. Ses six sections
     * doivent se combler par l'instantané plutôt que de tomber sur `#rescue`, exactement comme
     * `WealthEvolutionSection`/`WealthIncomeSection` le font sur le tableau de bord.
     */
    await page.goto(`${BASE_URL}/actions/analyse`, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });

    /** Précondition positive avant la négative : voir le commentaire équivalent ci-dessus. */
    await page.waitForSelector('[data-section="concentration"] dl', { timeout: TIMEOUT });

    const analysisBody = await page.textContent('body');
    assert.ok(
        !analysisBody.includes('Pas de connexion'),
        'La page analyse en cache ne doit pas tomber sur le repli hors-ligne.',
    );
    assert.ok(
        !analysisBody.includes('Données indisponibles hors-ligne'),
        'L\'instantané doit combler les six sections de la page analyse, pas les renvoyer au slot #rescue.',
    );

    /**
     * Ce script visait autrefois ici `[data-section="instruments"] [data-instrument-row]`, un
     * catalogue qui vivait sur le tableau de bord. Il a migré vers `/instruments` par un refactor
     * antérieur à ce chantier (`Dashboard.vue` ne monte plus que `WealthSummarySection`,
     * `WealthEvolutionSection` et `WealthIncomeSection`) : ce contrôle était mort, jamais détecté
     * puisque ce script n'avait jamais tourné de bout en bout avant la tâche 13. Le couvrir
     * hors-ligne supposerait de naviguer vers `/instruments` après la coupure — une page jamais
     * visitée dans ce scénario, qui retomberait sur la même capacité manquante que la tâche 13 a
     * identifiée et n'a pas comblée (le worker ne sait synthétiser un document que pour une page
     * déjà mise en cache par une vraie navigation). Retiré plutôt que remplacé : le couvrir
     * correctement n'est pas à la portée de cette tâche.
     */

    /** Une URL jamais visitée n'a rien en cache : c'est le repli qui doit apparaître. */
    await page.goto(`${BASE_URL}/instruments/999999`, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    assert.ok(
        (await page.textContent('body')).includes('Pas de connexion'),
        'Une page jamais visitée doit tomber sur le repli hors-ligne.',
    );

    console.log('Parcours hors-ligne : OK');
} finally {
    await context.close();
    await browser.close();
}
