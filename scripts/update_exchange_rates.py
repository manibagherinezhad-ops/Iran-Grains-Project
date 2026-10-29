"""Update the five static exchange rates from the ICE homepage."""

from datetime import datetime
from pathlib import Path
import re
import unicodedata
from zoneinfo import ZoneInfo

from playwright.sync_api import sync_playwright


SOURCE = "https://ice.ir/"
ROOT = Path(__file__).resolve().parents[1]
CURRENCIES = {
    "دلار آمریکا": ("دلار آمریکا", "دلار امريکا", "دلار امریکا"),
    "یورو": ("یورو",),
    "درهم امارات": ("درهم امارات",),
    "یوآن چین": ("یوآن چین", "یوان چین"),
    "روبل روسیه": ("روبل روسیه",),
}
DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
PERSIAN = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")


def normalized(value):
    return unicodedata.normalize("NFKC", value).translate(DIGITS).replace("ي", "ی").replace("ك", "ک")


def price_from_cells(cells):
    for cell in cells:
        text = normalized(cell)
        for match in re.finditer(r"(?<!\d)(?:\d{1,3}(?:[,٬،]\d{3})+|\d{4,8})(?!\d)", text):
            price = int(re.sub(r"\D", "", match.group()))
            if price >= 1000:
                return price
    return None


def extract_rates(rows):
    rates = {}
    for cells in rows:
        if len(cells) < 2:
            continue
        labels = [normalized(cell) for cell in cells]
        for name, aliases in CURRENCIES.items():
            if name in rates:
                continue
            index = next((i for i, label in enumerate(labels) if any(alias in label for alias in aliases)), None)
            if index is None:
                continue
            price = price_from_cells(cells[index + 1:])
            if price is not None:
                rates[name] = price
    missing = set(CURRENCIES) - set(rates)
    if missing:
        raise ValueError(f"ICE did not provide all five rates; missing: {', '.join(sorted(missing))}")
    # The site displays rial. If the source is in toman, convert all five together.
    if rates["دلار آمریکا"] < 500_000:
        rates = {name: value * 10 for name, value in rates.items()}
    if not 500_000 <= rates["دلار آمریکا"] <= 10_000_000:
        raise ValueError("Dollar rate is outside the expected rial range; refusing to publish")
    dollar = rates["دلار آمریکا"]
    ratios = (
        (rates["یورو"] / dollar, 0.7, 1.6),
        (dollar / rates["درهم امارات"], 2.5, 5),
        (dollar / rates["یوآن چین"], 4, 11),
        (dollar / rates["روبل روسیه"], 25, 250),
    )
    if any(not low <= ratio <= high for ratio, low, high in ratios):
        raise ValueError("ICE rates failed cross-currency validation; refusing to publish")
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
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        page = browser.new_page(locale="fa-IR")
        response = page.goto(SOURCE, wait_until="domcontentloaded", timeout=60000)
        if not response or response.status >= 400:
            raise RuntimeError(f"ICE returned HTTP {response.status if response else 'no response'}")
        page.wait_for_timeout(5000)
        rows = page.locator("tr").evaluate_all("rows => rows.map(row => [...row.querySelectorAll('th, td')].map(cell => cell.textContent.trim()))")
        rows += page.evaluate("""() => [...document.querySelectorAll('body *')]
            .filter(el => el.children.length === 0 && /دلار|یورو|درهم|یوآن|یوان|روبل/.test(el.textContent))
            .map(el => {
                let group = el.parentElement;
                for (let i = 0; i < 3 && group && !/(?:[۰-۹٠-٩0-9]{1,3}[,٬،][۰-۹٠-٩0-9]{3}|[۰-۹٠-٩0-9]{4,})/.test(group.textContent); i++) group = group.parentElement;
                return group ? [...group.querySelectorAll('*')].filter(child => child.children.length === 0)
                    .map(child => child.textContent.trim()).filter(Boolean) : [];
            }).filter(group => group.length > 1)""")
        browser.close()
    rates = extract_rates(rows)
    date = jalali_date(datetime.now(ZoneInfo("Asia/Tehran")))
    for path in (ROOT / "Site/index.html", ROOT / "Site/iran-grains-standalone.html"):
        update_page(path, rates, date)
    print(f"Updated exchange rates for {date} from {SOURCE}")


if __name__ == "__main__":
    main()
