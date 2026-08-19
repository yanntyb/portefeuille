"""Stub yfinance injecté par PYTHONPATH dans les tests des scripts de prix.

Reproduit sans réseau la forme des DataFrames renvoyés par yfinance, y compris
la dernière barre incomplète que Yahoo publie en séance : OHLC à NaN, volume
déjà renseigné, donc non éliminée par `dropna(how="all")`.
"""

import numpy as np
import pandas as pd

DATES = pd.to_datetime(["2026-08-14", "2026-08-17"])


def _frame() -> pd.DataFrame:
    return pd.DataFrame(
        {
            "Open": [100.0, np.nan],
            "High": [101.0, np.nan],
            "Low": [99.0, np.nan],
            "Close": [100.5, np.nan],
            "Volume": [1000, 2000],
        },
        index=DATES,
    )


class Ticker:
    def __init__(self, symbol: str) -> None:
        self.symbol = symbol

    def history(self, **kwargs) -> pd.DataFrame:
        return _frame()

    @property
    def dividends(self) -> pd.Series:
        """Trois détachements dont un montant absent, comme Yahoo en publie sur une opération
        sur titre incomplète : le script doit l'écarter sans perdre les deux autres."""
        return pd.Series(
            [0.51, np.nan, 0.62],
            index=pd.to_datetime(["2026-03-05", "2026-06-04", "2026-09-03"]),
        )


def download(tickers, **kwargs) -> pd.DataFrame:
    symbols = [tickers] if isinstance(tickers, str) else list(tickers)

    return pd.concat({symbol: _frame() for symbol in symbols}, axis=1)
