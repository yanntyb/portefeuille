import json
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
            history = t.history(start=start_date, end=end_date)
            if not history.empty:
                all_data[group_tickers[0]] = _dataframe_to_list(history)
        else:
            df = yf.download(
                group_tickers,
                start=start_date,
                end=end_date,
                group_by="ticker",
                threads=True,
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

    print(json.dumps({"status": "ok", "data": all_data}))


def _dataframe_to_list(df) -> list[dict]:
    data = []
    for date, row in df.iterrows():
        data.append(
            {
                "date": date.strftime("%Y-%m-%d"),
                "open": round(float(row["Open"]), 4),
                "high": round(float(row["High"]), 4),
                "low": round(float(row["Low"]), 4),
                "close": round(float(row["Close"]), 4),
                "volume": int(row["Volume"]),
            }
        )
    return data


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}))
        sys.exit(1)
