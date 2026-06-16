import json
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

    print(json.dumps({"status": "ok", "data": data}))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}))
        sys.exit(1)
