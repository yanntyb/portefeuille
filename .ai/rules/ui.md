---
paths:
  - 'resources/js/components/ui/**'
---

# Ui

## Un combobox reka dans une modale s'ancre en inline, jamais en portail
`ComboboxContent` est en `position="inline"` par défaut : dans ce mode reka rend un simple `Primitive` et n'instancie **pas** `PopperContent`. C'est ce qui rend un combobox acceptable dans le `DialogContent` de saisie, là où `NativeSelect.vue` écartait reka (liste flottante en modale `fixed` sous clavier virtuel = deux verrous de défilement, deux pièges de focus). Ne jamais y ajouter `ComboboxPortal`. `bodyLock` reste faux, Échap ne ferme que la couche haute — la liste, pas la modale.

Trois pièges vérifiés dans reka 2.10.1 :
- `open-on-click` doit être posé explicitement (défaut faux) ; laisser `open-on-focus` faux, sinon un Tab déplie la liste au passage.
- Liste ouverte sans entrée surlignée (état vide), `Entrée` n'est pas intercepté et **soumet le formulaire** : neutraliser avec un `@keydown.enter`.
- `display-value` ne resynchronise le texte que sur changement de valeur, pas quand les options arrivent plus tard (cas d'un `fetch` après montage) : ajouter un `watch` sur les options.

Et pour tout contrôle dont la racine n'est pas l'`<input>`/`<select>` : déclarer `aria-describedby` en prop. En attribut de passe il tombe sur la racine et le message d'erreur n'est annoncé par personne (défaut actuel de `NativeSelect`).
