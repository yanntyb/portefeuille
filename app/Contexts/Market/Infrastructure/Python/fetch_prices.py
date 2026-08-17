from __future__ import annotations

import json
import math
import sys

import yfinance as yf


def main() -> None:
    raw = sys.stdin.read()
    params = json.loads(raw)

    ticker = params.get("ticker", "")
    start_date = params.get("start_date", "")
    end_date = params.get("end_date", "")

    if not ticker or not start_date or not end_date:
        print(json.dumps({"status": "error", "error": "Missing required parameters: ticker, start_date, end_date"}))
        sys.exit(1)

    t = yf.Ticker(ticker)
    history = t.history(start=start_date, end=end_date)

    data = []
    for date, row in history.iterrows():
        # Yahoo publishes the running session as a bar carrying a volume but an
        # empty OHLC. Its NaN would serialize as bare `NaN` literals, which strict
        # JSON decoders reject: one such bar would cost the caller the whole reply.
        prices = [_as_float(row[column]) for column in ("Open", "High", "Low", "Close")]
        if None in prices:
            continue

        open_, high, low, close = prices
        volume = _as_float(row["Volume"])

        data.append(
            {
                "date": date.strftime("%Y-%m-%d"),
                "open": round(open_, 4),
                "high": round(high, 4),
                "low": round(low, 4),
                "close": round(close, 4),
                # Funds often quote without ever reporting a volume.
                "volume": int(volume) if volume is not None else 0,
            }
        )

    print(json.dumps({"status": "ok", "data": data}, allow_nan=False))


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
