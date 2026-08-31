---
paths:
  - 'resources/js/lib/chart*.ts'
---

# Lib

## Toute option ECharts suppose son composant enregistré
`resources/js/lib/echarts.ts` fait un import sélectif (`echarts.use([...])`) : une option dont le composant n'y figure pas est ignorée **en silence**, sans erreur ni avertissement. La légende du mode détail est restée invisible pour cette raison — `legend` était bien dans l'option, `LegendComponent` absent de la liste.

Un test qui n'inspecte que l'objet d'option ne prouve donc rien. `resources/js/lib/echarts.test.ts` peint un vrai graphe (moteur SVG, qui fonctionne sous jsdom) et cherche le résultat dans le DOM : ajouter un cas là quand on branche un nouveau composant (markLine, visualMap, toolbox...).
