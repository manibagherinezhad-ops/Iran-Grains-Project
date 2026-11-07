"""Update the site's five ICE transfer-sale rates from TGJU's ICE table."""

from datetime import datetime
from html.parser import HTMLParser
from pathlib import Path
import re
from urllib.parse import quote
from urllib.request import Request, urlopen
from zoneinfo import ZoneInfo


SOURCE = "https://www.tgju.org/مرکز-مبادله-ارز-و-طلای-ایران"
ROOT = Path(__file__).resolve().parents[1]
CURRENCIES = {
    "دلار آمریکا": "ice_transfer_usd_sell",
    "یورو": "ice_transfer_eur_sell",
    "درهم امارات": "ice_transfer_aed_sell",
    "یوآن چین": "ice_transfer_cny_sell",
    "روبل روسیه": "ice_transfer_rub_sell",
}
PERSIAN = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
WESTERN = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


class ExchangeRows(HTMLParser):
    def __init__(self):
        super().__init__()
        self.prices = {}
        self.row = None
        self.in_price = False
        self.price_parts = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "tr":
            slug = attrs.get("data-market-nameslug")
            self.row = slug if slug in CURRENCIES.values() else None
        elif tag == "td" and self.row and "nf" in attrs.get("class", "").split() and self.row not in self.prices:
            self.in_price = True
            self.price_parts = []

    def handle_data(self, data):
        if self.in_price:
            self.price_parts.append(data)

    def handle_endtag(self, tag):
        if tag == "td" and self.in_price:
            value = "".join(self.price_parts).strip().translate(WESTERN)
            if not re.fullmatch(r"[\d,٬،]+", value):
                raise ValueError(f"Unexpected ICE rate: {value!r}")
            self.prices[self.row] = int(re.sub(r"\D", "", value))
            self.in_price = False
        elif tag == "tr":
            self.row = None


def extract_rates(html):
    parser = ExchangeRows()
    parser.feed(html)
    missing = set(CURRENCIES.values()) - set(parser.prices)
    if missing:
        raise ValueError(f"Missing ICE transfer-sale rates: {', '.join(sorted(missing))}")
    rates = {name: parser.prices[slug] for name, slug in CURRENCIES.items()}
    dollar = rates["دلار آمریکا"]
    ratios = (
        (rates["یورو"] / dollar, 0.7, 1.6),
        (dollar / rates["درهم امارات"], 2.5, 5),
        (dollar / rates["یوآن چین"], 4, 11),
        (dollar / rates["روبل روسیه"], 25, 250),
    )
    if not 500_000 <= dollar <= 10_000_000 or any(not low <= value <= high for value, low, high in ratios):
        raise ValueError("ICE rates failed validation; refusing to publish")
    return rates


def jalali_date(now):
    gy, gm, gd = now.year, now.month, now.day
    gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
    jy = 0 if gy <= 1600 else 979
    gy -= 621 if gy <= 1600 else 1600
    gy2 = gy + (1 if gm > 2 else 0)
    days = 365 * gy + (gy2 + 3) // 4 - (gy2 + 99) // 100 + (gy2 + 399) // 400 - 80 + gd + gdm[gm - 1]
    jy += 33 * (days // 12053)
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    jy += (days - 1) // 365
    if days > 365:
        days = (days - 1) % 365
    jm = 1 + days // 31 if days < 186 else 7 + (days - 186) // 30
    jd = 1 + (days % 31 if days < 186 else (days - 186) % 30)
    return f"{jy:04d}/{jm:02d}/{jd:02d}".translate(PERSIAN)


def update_page(path, rates, date):
    html = path.read_text()
    for name, price in rates.items():
        pattern = rf'(<span class="ex-name">{re.escape(name)}</span><b class="ex-value">)[^<]*(?=<i class="ex-unit">)'
        value = f"{price:,}".translate(PERSIAN)
        html, count = re.subn(pattern, lambda match: match.group(1) + value + " ", html, count=1)
        if count != 1:
            raise ValueError(f"Could not update {name} in {path}")
    html, count = re.subn(r'(<span class="exchange-date" id="exchangeDate">)[^<]*(</span>)',
                          lambda match: match.group(1) + "مورخ " + date + match.group(2), html, count=1)
    if count != 1:
        raise ValueError(f"Could not update exchange date in {path}")
    path.write_text(html)


def main():
    request = Request(quote(SOURCE, safe=":/"), headers={"User-Agent": "Mozilla/5.0 (compatible; IranGrainsRates/1.0)"})
    with urlopen(request, timeout=45) as response:
        html = response.read().decode("utf-8")
    rates = extract_rates(html)
    date = jalali_date(datetime.now(ZoneInfo("Asia/Tehran")))
    for path in (ROOT / "Site/index.html", ROOT / "Site/iran-grains-standalone.html"):
        update_page(path, rates, date)
    print(f"Updated ICE transfer-sale rates for {date} from {SOURCE}: {rates}")


if __name__ == "__main__":
    main()
