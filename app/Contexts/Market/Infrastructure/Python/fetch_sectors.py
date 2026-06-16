import json
import re
import sys

import yfinance as yf


def normalize_key(key: str) -> str:
    key = re.sub(r"(?<=[a-z])(?=[A-Z])", "_", key)
    key = key.replace(" ", "_").lower()
    return key


def main() -> None:
    raw = sys.stdin.read()
    params = json.loads(raw)

    ticker = params.get("ticker", "")

    if not ticker:
        print(json.dumps({"status": "error", "error": "Missing required parameter: ticker"}))
        sys.exit(1)

    t = yf.Ticker(ticker)

    sectors = {}

    try:
        weightings = t.funds_data.sector_weightings
        if weightings:
            for key, weight in weightings.items():
                weight = float(weight)
                if weight > 0:
                    sectors[normalize_key(key)] = round(weight, 6)
    except Exception:
        pass

    if not sectors:
        try:
            info = t.info
            sector = info.get("sector")
            if sector:
                sectors[normalize_key(sector)] = 1.0
        except Exception:
            pass

    print(json.dumps({"status": "ok", "data": sectors}))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}))
        sys.exit(1)
