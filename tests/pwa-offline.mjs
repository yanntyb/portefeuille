import assert from 'node:assert/strict';
import { chromium } from 'playwright';

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

    await context.setOffline(true);
    await page.reload({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });

    const body = await page.textContent('body');
    assert.ok(body.includes('Performances'), 'La page en cache doit rester lisible hors-ligne.');
    assert.ok(!body.includes('Pas de connexion'), 'La page hors-ligne ne doit pas remplacer une page en cache.');

    await page.waitForSelector('[data-pwa-banner="stale"]', { timeout: TIMEOUT });

    /**
     * Les partiels différés ont été mis en cache pendant la visite en ligne. On les retire pour
     * atteindre le cas que le slot `#rescue` doit couvrir : la page est là, ses groupes ne le
     * sont pas.
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

    await page.waitForSelector('text=Données indisponibles hors-ligne', { timeout: TIMEOUT });

    /**
     * `InstrumentsSection.vue` n'utilise pas `<Deferred>` : elle éteint son squelette en lisant
     * `page.rescuedProps` elle-même (voir `isCatalogLoading`/`isDeferredPending`). Ce chemin n'est
     * atteignable par aucun test unitaire, qui appelle la fonction pure directement — seul un vrai
     * navigateur, avec un vrai worker rescapant une vraie requête, le traverse.
     *
     * Précondition positive avant la négative : `.animate-pulse` est aussi introuvable si la
     * section a disparu (renommée, attribut retiré) — sans ce garde-fou l'assertion virerait au
     * vert pendant qu'une telle régression passerait inaperçue. `[data-instrument-row]` (déjà
     * utilisé par `SmokeTest.php`) porte les positions de `overview.holdings`, jamais différées :
     * au moins une ligne y est toujours rendue dès que la section existe.
     */
    await page.waitForSelector('[data-section="instruments"] [data-instrument-row]', { timeout: TIMEOUT });

    /**
     * Sondage borné, pas une lecture ponctuelle : les groupes différés se résolvent
     * indépendamment les uns des autres. Un `evaluate` unique pris juste après le slot `#rescue`
     * échouerait à tort si le groupe catalogue arrivait un tick plus tard que celui qui rend ce
     * texte — `waitForFunction` réessaie jusqu'à la même borne, sans course.
     */
    await page.waitForFunction(
        () => document.querySelector('[data-section="instruments"] .animate-pulse') === null,
        undefined,
        { timeout: TIMEOUT },
    );

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
