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

        # `Ticker.dividends` has no batched equivalent in yfinance: the loop stays within a
        # single process, which is the point of the batch — one interpreter boot and one
        # yfinance import for the whole catalogue instead of N.
        rows = _series_to_list(yf.Ticker(ticker).dividends, info["start_date"], info["end_date"])

        if rows:
            all_data[ticker] = rows

    print(json.dumps({"status": "ok", "data": all_data}, allow_nan=False))


def _series_to_list(series, start: str, end: str) -> list[dict]:
    """Ex-dividend dates within [start, end), bounds aligned with yfinance's convention."""
    data = []

    for date, value in series.items():
        day = date.strftime("%Y-%m-%d")

        if day < start or day >= end:
            continue

        # Yahoo sometimes publishes a corporate action with no amount. This NaN would
        # serialize as a bare `NaN` literal: invalid JSON that would cost the whole batch
        # for one line.
        amount = _as_float(value)

        if amount is None or amount <= 0:
            continue

        data.append({"ex_date": day, "amount_per_share": round(amount, 6)})

    return data


def _as_float(value) -> float | None:
    """Read a cell as a float, reporting a missing or unusable one as None."""
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
