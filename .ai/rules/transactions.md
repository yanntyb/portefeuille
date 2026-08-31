---
paths:
  - 'resources/js/components/transactions/**'
---

# Transactions

## La saisie : useForm partiel, blocage hors-ligne, et deux attributs qui ne survivent pas
`useForm(data)` — forme à UN argument. Passer méthode et URL en premiers arguments rend un formulaire à précognition, qui validerait auprès du serveur pendant la frappe : incompatible avec « hors-ligne, on ne saisit pas ».

À l'envoi, trois options obligatoires : `only: refreshableKeys(page.props)` (les props non nommées gardent leur ancienne valeur, mais nommer une prop différée non chargée ferait calculer une section repliée), et `preserveState: true` + `preserveScroll: true` — ils ne sont PAS le défaut d'une visite non-GET, et sans eux la page se remonte, toutes les sections se replient et le lecteur perd de vue la ligne qu'il vient de saisir. `snapshot.sync()` dans `onSuccess`, sinon le blob hors-ligne clignote à la visite suivante.

Montants en `type="text"` + `inputmode="decimal"`, jamais `type="number"` : sur un clavier français la virgule rend un champ numérique invalide et vide sa valeur sans un mot. `parseDecimalInput` fait la traduction.

Hors-ligne : le service worker met tout non-GET en `passthrough`, donc aucun filet côté worker — le blocage est entièrement front, via `stores/network.ts` (`useOnline`, pas `serviceWorker.stale`, qui n'est pas un état de connectivité).

Deux attributs à ne pas poser au mauvais endroit : sur `DialogContent`, dont la racine est `DialogPortal`, un `data-*` disparaît sans erreur — le poser sur un enfant. Et dans `TransactionYearList` variante `named`, la ligne EST un `<button>` : les actions vivent dans le bloc de détail, qui en est un frère.
