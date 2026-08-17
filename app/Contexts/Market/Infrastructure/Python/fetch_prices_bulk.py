from __future__ import annotations

import json
import math
import sys
from datetime import datetime

import yfinance as yf


def main() -> None:
    raw = sys.stdin.read()
    params = json.loads(raw)

    tickers_info = params.get("tickers", [])

    if not tickers_info:
        print(json.dumps({"status": "ok", "data": {}}))
        return

    # Group tickers by date range for batch downloading
    date_groups: dict[tuple[str, str], list[str]] = {}
    for info in tickers_info:
        ticker = info["ticker"]
        start = info["start_date"]
        end = info["end_date"]
        key = (start, end)
        date_groups.setdefault(key, []).append(ticker)

    all_data: dict[str, list[dict]] = {}

    for (start_date, end_date), group_tickers in date_groups.items():
        if len(group_tickers) == 1:
            t = yf.Ticker(group_tickers[0])
            # auto_adjust is explicit in both branches: yf.download flipped its default
            # in 0.2.51, so leaving it implicit made the two paths disagree on any
            # older allowed version and mixed both conventions in the stored series.
            history = t.history(start=start_date, end=end_date, auto_adjust=True)
            if not history.empty:
                all_data[group_tickers[0]] = _dataframe_to_list(history)
        else:
            df = yf.download(
                group_tickers,
                start=start_date,
                end=end_date,
                group_by="ticker",
                threads=True,
                auto_adjust=True,
            )

            if df.empty:
                continue

            for ticker in group_tickers:
                try:
                    ticker_df = df[ticker].dropna(how="all")
                    if not ticker_df.empty:
                        all_data[ticker] = _dataframe_to_list(ticker_df)
                except (KeyError, TypeError):
                    continue

    print(json.dumps({"status": "ok", "data": all_data}, allow_nan=False))


def _dataframe_to_list(df) -> list[dict]:
    data = []
    for date, row in df.iterrows():
        # Yahoo publishes the running session as a bar carrying a volume but an
        # empty OHLC, so `dropna(how="all")` keeps it. Its NaN would serialize as
        # bare `NaN` literals, which strict JSON decoders reject: one such bar
        # would cost the caller the whole batch.
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
