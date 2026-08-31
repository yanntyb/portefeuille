---
paths:
  - 'app/Contexts/*/Http/**'
---

# Http

## La validation passe par un FormRequest co-localisé, jamais en ligne
Toute route d'écriture valide son corps dans un FormRequest posé dans le `Http/` du contexte propriétaire de la table — `Portfolio\Http\TransactionRequest` est le premier, et la référence. Une seule classe sert création et correction quand les règles sont identiques ; ce qui diffère se lit sur la route (`$this->route('id')`).

`authorize()` rend `auth()->check()` : les pages de lecture savent s'afficher vides, une écriture n'a personne à qui appartenir.

Deux ordres à connaître, tous deux figés par des tests : la requête validée tourne AVANT le contrôleur, donc un corps invalide rend 422 avant le 404 d'une ligne appartenant à autrui ; et `whereNumber('id')` fait qu'un identifiant non numérique ne matche aucune route, ce que le rendu d'exception de `bootstrap/app.php` traduit en redirection vers l'accueil — pas un 404, mais surtout pas une écriture.

Propriété de la ressource : contrôlée dans le contrôleur via un `Support/UserXxx` scopé sur `user_id`, avec `abort(404)` — jamais 403, le code ne doit pas révéler que la ligne existe.

Modèles gardés par `$guarded = ['id']` : les actions écrivent un tableau littéral explicite, jamais `create($request->validated())`. `user_id` vient de `auth()`, et il est immuable en correction.
