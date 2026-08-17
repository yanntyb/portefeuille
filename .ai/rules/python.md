---
paths:
  - 'app/Contexts/Market/Infrastructure/Python/**'
---

# Python

## Ne jamais laisser un NaN atteindre json.dumps
yfinance renvoie la journée en cours comme une barre portant un volume mais un OHLC à NaN, et `dropna(how="all")` ne l'élimine pas. `json.dumps` sérialise ces NaN en littéraux `NaN` : JSON invalide que `json_decode` refuse, donc tout le lot est perdu pour une seule ligne.

Dans tout script renvoyant des séries : écarter les lignes sans OHLC, retomber sur un volume nul (les fonds n'en déclarent souvent aucun), et sérialiser avec `allow_nan=False` pour échouer bruyamment.

Ces scripts se testent depuis Pest sans réseau : `tests/Fixtures/python/yfinance.py` est un stub injecté par `PYTHONPATH` (voir YahooScriptTest.php).
