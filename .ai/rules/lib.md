---
paths:
  - 'resources/js/lib/chart*.ts'
---

# Lib

## Toute option ECharts suppose son composant enregistré
`resources/js/lib/echarts.ts` fait un import sélectif (`echarts.use([...])`) : une option dont le composant n'y figure pas est ignorée **en silence**, sans erreur ni avertissement. Une légende y est restée invisible tout un temps — `legend` était bien dans l'option, `LegendComponent` absent de la liste (elle a depuis été retirée pour de bon).

Un test qui n'inspecte que l'objet d'option ne prouve donc rien. `resources/js/lib/echarts.test.ts` peint de vrais graphes (moteur SVG, qui fonctionne sous jsdom) et compare le DOM obtenu à celui du même graphe sans l'option : ajouter un cas là quand on branche un nouveau composant (markLine, visualMap, toolbox...). À noter : `AriaComponent` n'écrit rien dans le SVG, son effet ne se teste pas de cette façon.
