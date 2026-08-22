---
paths:
  - 'app/Contexts/RealEstate/**'
---

# Real Estate

## L'investi d'un bien à crédit est le cash sorti
L'investi d'un bien acheté à crédit vaut `apport + cash injecté`, où `apport = prix + frais − capital emprunté` et `cash injecté = Σ_mois max(0, −net)`. Jamais `apport + capital remboursé` : l'échéance qui sort de la poche contient déjà ce capital, donc la seconde formule le compte deux fois dès qu'un mois est déficitaire, et elle compte comme une mise le capital remboursé par le locataire — qui doit apparaître en gain, c'est le levier. `GetRealEstateCashInvested` porte la formule.
