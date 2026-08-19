from __future__ import annotations

import json
import math
import sys

import yfinance as yf


def main() -> None:
    params = json.loads(sys.stdin.read())
    tickers_info = params.get("tickers", [])

    all_data: dict[str, list[dict]] = {}

    for info in tickers_info:
        ticker = info["ticker"]

        # `Ticker.dividends` n'a pas d'équivalent groupé chez yfinance : la boucle reste dans
        # un seul process, ce qui est le but du lot — un boot d'interpréteur et un import
        # yfinance pour tout le catalogue au lieu de N.
        rows = _series_to_list(yf.Ticker(ticker).dividends, info["start_date"], info["end_date"])

        if rows:
            all_data[ticker] = rows

    print(json.dumps({"status": "ok", "data": all_data}, allow_nan=False))


def _series_to_list(series, start: str, end: str) -> list[dict]:
    """Détachements de la fenêtre [start, end), bornes alignées sur la convention yfinance."""
    data = []

    for date, value in series.items():
        day = date.strftime("%Y-%m-%d")

        if day < start or day >= end:
            continue

        # Yahoo publie parfois une opération sur titre sans montant. Ce NaN se sérialiserait en
        # littéral `NaN` : du JSON invalide qui coûterait le lot entier pour une ligne.
        amount = _as_float(value)

        if amount is None or amount <= 0:
            continue

        data.append({"ex_date": day, "amount_per_share": round(amount, 6)})

    return data


def _as_float(value) -> float | None:
    """Lit une cellule en flottant, en signalant None si elle est absente ou inutilisable."""
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None

    return None if math.isnan(number) else number


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}))
        sys.exit(1)
