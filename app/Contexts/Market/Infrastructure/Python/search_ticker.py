import json
import sys

import yfinance as yf


def search(query: str) -> list[dict]:
    results = yf.Search(query)
    quotes = results.quotes if hasattr(results, "quotes") else []
    return [
        {
            "symbol": q.get("symbol", ""),
            "name": q.get("longname") or q.get("shortname", ""),
            "exchange": q.get("exchDisp") or q.get("exchange", ""),
            "type": q.get("typeDisp") or q.get("quoteType", ""),
        }
        for q in quotes
    ]


def main() -> None:
    raw = sys.stdin.read()
    params = json.loads(raw)

    query = params.get("query", "")
    fallback_query = params.get("fallback_query")

    if not query:
        print(json.dumps({"status": "error", "error": "No query provided"}))
        sys.exit(1)

    results = search(query)

    if not results and fallback_query:
        results = search(fallback_query)

    print(json.dumps({"status": "ok", "data": results}))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}))
        sys.exit(1)
