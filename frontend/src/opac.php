<?php
// frontend/src/opac.php — Public OPAC portal (no login required)
date_default_timezone_set('Asia/Manila');
$base = ''; // public path
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OPAC — St. Agnes Academy Library</title>
<meta name="theme-color" content="#e51d66">
<style>
:root{--ink:#111018;--muted:#776b78;--pink:#b40f7d;--pink2:#d41688;--pink3:#ff0199;--paper:#f8f0f5;--white:#fff;--line:#efdce9;--soft:#fbedf5;--shadow:0 12px 35px rgba(42,10,34,.07)}
*{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background:var(--paper);color:#1a1a2e;min-height:100vh}
.opac-header{background:#010107;color:#f0e8e8;min-height:88px;padding:14px clamp(18px,4vw,54px);display:flex;align-items:center;justify-content:space-between;gap:18px;border-bottom:1px solid #2a2a3e;box-shadow:0 8px 24px rgba(0,0,0,.08)}
.brand{display:flex;align-items:center;gap:15px;min-width:0}.brand-logo{width:62px;height:62px;object-fit:contain;display:block;flex-shrink:0}.brand-copy h1{font-size:17px;font-weight:750;letter-spacing:.45px;line-height:1.25}.brand-copy .tag{color:rgba(255,255,255,.48);font-size:11px;letter-spacing:.7px;margin-top:5px}.opac-header a{color:#f5e8f1;text-decoration:none;font-size:13px;font-weight:650;padding:10px 16px;border:1px solid rgba(255,255,255,.14);border-radius:9px;white-space:nowrap;transition:.2s}.opac-header a:hover{background:rgba(180,15,125,.2);border-color:rgba(212,22,136,.55)}
.opac-wrap{max-width:1380px;margin:0 auto;padding:30px clamp(16px,3vw,36px) 64px}.opac-intro{background:linear-gradient(135deg, #08080a 0%, #0a090a 30%, #d41688b0 60%, #ff0199a8 80%, #ff0199c9 100%);border-radius:18px;padding:clamp(25px,4vw,42px);margin-bottom:22px;color:#fff;box-shadow:0 14px 34px rgba(180,15,125,.14);position:relative;overflow:hidden}.opac-intro:after{content:"";position:absolute;width:260px;height:260px;border:1px solid rgba(255,255,255,.13);border-radius:50%;right:-70px;top:-125px;box-shadow:0 0 0 28px rgba(255,255,255,.035),0 0 0 58px rgba(255,255,255,.025)}.intro-kicker{font-size:10px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.72);font-weight:700;margin-bottom:10px}.opac-intro h2{font-size:clamp(23px,3vw,31px);font-weight:750;letter-spacing:-.5px;margin-bottom:9px}.opac-intro p{color:rgba(255,255,255,.82);font-size:13px;line-height:1.65;max-width:760px}
.opac-form{background:#fff;border-radius:16px;padding:22px;border:1px solid var(--line);box-shadow:var(--shadow);margin-bottom:23px}.opac-form .grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;margin-bottom:15px}@media(max-width:760px){.opac-form .grid{grid-template-columns:1fr}}.opac-form label{display:block;font-size:10px;color:#6e5c6b;margin-bottom:7px;font-weight:750;text-transform:uppercase;letter-spacing:.8px}.opac-form input,.opac-form select{width:100%;padding:12px 14px;border:1px solid #e8dce6;border-radius:9px;font-size:13px;background:#fff;color:#1a1a2e;transition:.2s;min-height:44px}.opac-form input::placeholder{color:#b2a5b0}.opac-form input:focus,.opac-form select:focus{border-color:var(--pink);outline:none;box-shadow:0 0 0 3px rgba(180,15,125,.10)}.opac-form .row2{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:17px}@media(max-width:760px){.opac-form .row2{grid-template-columns:1fr 1fr}}@media(max-width:420px){.opac-form .row2{grid-template-columns:1fr}}.opac-form .actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.lang-toggle{display:inline-flex;background:#f7edf4;border:1px solid #f0dce9;border-radius:9px;overflow:hidden;margin-right:auto;padding:3px}.lang-toggle button{border:0;background:transparent;padding:8px 15px;font-size:12px;font-weight:650;cursor:pointer;color:#776b78;border-radius:7px}.lang-toggle button.active{background:#010107;color:#fff;box-shadow:0 2px 7px rgba(0,0,0,.15)}.btn-primary,.btn-ghost{padding:11px 21px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:700;transition:.2s}.btn-primary{background:#b40f7d;color:#fff;border:1px solid #b40f7d;box-shadow:0 5px 12px rgba(180,15,125,.15)}.btn-primary:hover{background:#8f0c64;transform:translateY(-1px)}.btn-ghost{background:#f8f0f5;color:#594653;border:1px solid #eedce8}.btn-ghost:hover{background:#f2e2ed;border-color:#dfbfd4}.syn{font-size:11px;color:#927b8e;margin-top:9px;line-height:1.5}
.opac-meta{display:flex;justify-content:space-between;align-items:center;margin:0 2px 14px;flex-wrap:wrap;gap:8px}.opac-meta .count{color:#776b78;font-size:13px}.opac-meta .count strong{color:#1a1a2e;font-size:15px}.opac-meta .syn{margin:0;color:#9a5281}.book-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}.book-card{background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--line);display:flex;flex-direction:column;transition:transform .22s,box-shadow .22s,border-color .22s;box-shadow:0 3px 12px rgba(42,10,34,.025)}.book-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(42,10,34,.10);border-color:#dca9ca}.book-cover{height:205px;background:linear-gradient(145deg,#f8eaf3,#f1dce9);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;padding:10px}.book-cover img{width:100%;height:100%;object-fit:contain}.book-cover .initial{font-size:64px;color:#fff;font-weight:800}.book-cover .badge{position:absolute;top:11px;right:11px;padding:5px 10px;border-radius:20px;font-size:10px;font-weight:750;color:#fff;background:#168451;box-shadow:0 3px 8px rgba(0,0,0,.12)}.book-cover .badge.low{background:#b77918}.book-cover .badge.none{background:#a52b50}.book-body{padding:15px 16px 16px;display:flex;flex-direction:column;flex:1;min-height:155px}.book-title{font-size:14px;font-weight:750;margin-bottom:5px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:#191522}.book-author{color:#857486;font-size:12px;margin-bottom:10px}.book-chip{display:inline-block;background:#fbedf5;color:#9b176e;padding:5px 10px;border-radius:20px;font-size:10px;font-weight:700;align-self:flex-start;border:1px solid #f3dce9}.book-meta{margin-top:auto;padding-top:12px;border-top:1px solid #f2e8ef;font-size:11px;color:#887b89;display:flex;justify-content:space-between;align-items:center;gap:8px}.book-meta strong{color:#211827;font-size:12px}.no-results{background:#fff;border-radius:16px;padding:60px 30px;text-align:center;border:1px solid var(--line);color:#897b89;box-shadow:var(--shadow)}.no-results h3{color:#211827;font-size:17px}.no-results p{font-size:13px}.spinner{width:34px;height:34px;border:3px solid #f2e4ee;border-top-color:#b40f7d;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 12px}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:560px){.opac-header{align-items:flex-start;padding:13px 15px}.brand-logo{width:48px;height:48px}.brand-copy h1{font-size:13px}.brand-copy .tag{font-size:9px}.opac-header a{font-size:11px;padding:9px 10px}.opac-wrap{padding-top:18px}.opac-form{padding:16px}.book-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.book-cover{height:155px}.book-body{padding:12px;min-height:150px}.book-title{font-size:12px}.book-author{font-size:11px}.book-meta{font-size:10px}.book-chip{font-size:9px}}
</style>
</head>
<body>
<header class="opac-header">
    <div class="brand">
        <img class="brand-logo" src="img/agustinnb.png" alt="St. Agnes Academy logo">
        <div class="brand-copy">
            <h1>ST. AGNES ACADEMY — OPAC</h1>
            <div class="tag">ONLINE PUBLIC ACCESS CATALOG · CALOOCAN INC.</div>
        </div>
    </div>
    <a href="../homepage.php">← Back to Library</a>
</header>

<main class="opac-wrap">
    <section class="opac-intro">
        <div class="intro-kicker">St. Agnes Academy Library</div>
        <h2>Discover Your Next Great Read</h2>
        <p>Search the library collection by title, author, ISBN, or subject. Walang kailangang login — maghanap sa Tagalog o English.</p>
    </section>

    <form class="opac-form" id="opacForm" onsubmit="event.preventDefault(); runSearch();">
        <div class="grid">
            <div>
                <label>Keyword / Pamagat / May-akda</label>
                <input type="text" id="q" placeholder="e.g. kasaysayan, history, Rizal, programming..." autocomplete="off">
            </div>
            <div>
                <label>Author</label>
                <input type="text" id="author" placeholder="Author name">
            </div>
            <div>
                <label>ISBN</label>
                <input type="text" id="isbn" placeholder="e.g. 978-...">
            </div>
        </div>
        <div class="row2">
            <div>
                <label>Category</label>
                <input type="text" id="category" placeholder="e.g. Science">
            </div>
            <div>
                <label>Year From</label>
                <input type="number" id="year_from" placeholder="1900" min="1000" max="2100">
            </div>
            <div>
                <label>Year To</label>
                <input type="number" id="year_to" placeholder="2100" min="1000" max="2100">
            </div>
            <div>
                <label>Availability</label>
                <select id="available">
                    <option value="">All</option>
                    <option value="1">Available only</option>
                </select>
            </div>
        </div>
        <div class="actions" style="align-items:center">
            <div class="lang-toggle" role="tablist">
                <button type="button" id="langEn" class="active" onclick="setLang('en')">English</button>
                <button type="button" id="langTl" onclick="setLang('tl')">Tagalog</button>
            </div>
            <button type="button" class="btn-ghost" onclick="resetForm()">Reset</button>
            <button type="submit" class="btn-primary">Search</button>
        </div>
        <div class="syn" id="synNote"></div>
    </form>

    <div class="opac-meta">
        <div class="count">Results: <strong id="count">0</strong></div>
        <div class="syn" id="expandedNote"></div>
    </div>

    <div class="book-grid" id="grid"></div>
</main>

<script>
let LANG = 'en';

function setLang(l) {
    LANG = l;
    document.getElementById('langEn').classList.toggle('active', l === 'en');
    document.getElementById('langTl').classList.toggle('active', l === 'tl');
    document.getElementById('synNote').textContent = l === 'tl'
        ? 'Tagalog mode: words will be translated to English synonyms before matching.'
        : '';
}

function placeholderColor(id) {
    const c = ['#2a2a2a','#4a4a4a','#6a6a6a','#8a8a8a','#aaaaaa','#cacaca'];
    let h = 0; for (let i = 0; i < String(id).length; i++) { h = ((h << 5) - h) + String(id).charCodeAt(i); h |= 0; }
    return c[Math.abs(h) % c.length];
}

function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

function cardHTML(b) {
    const hasCover = b.cover_image && b.cover_image.length > 100;
    const avail = b.available || 0;
    const badgeClass = avail <= 0 ? 'none' : (avail <= 2 ? 'low' : '');
    const badgeText  = avail <= 0 ? 'Not Available' : (avail <= 2 ? 'Low Stock' : 'Available');
    return `
        <div class="book-card">
            <div class="book-cover">
                ${hasCover ? `<img src="${esc(b.cover_image)}" alt="" onerror="this.style.display='none'">` : ''}
                ${!hasCover ? `<div class="initial" style="background:${placeholderColor(b.id)};width:100%;height:100%;display:flex;align-items:center;justify-content:center">${esc((b.title||'B').charAt(0).toUpperCase())}</div>` : ''}
                <span class="badge ${badgeClass}">${badgeText}</span>
            </div>
            <div class="book-body">
                <div class="book-title">${esc(b.title)}</div>
                <div class="book-author">by ${esc(b.author)}</div>
                <span class="book-chip">${esc(b.category_name || 'Uncategorized')}</span>
                <div class="book-meta">
                    <span><strong>${avail}</strong> / ${b.quantity || 0}</span>
                    <span>${esc(b.year_published || '')}</span>
                </div>
            </div>
        </div>`;
}

async function runSearch() {
    const grid = document.getElementById('grid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px"><div class="spinner"></div><p style="color:#897b89;font-size:13px">Searching catalog...</p></div>';
    document.getElementById('count').textContent = '0';
    document.getElementById('expandedNote').textContent = '';

    const params = new URLSearchParams({
        q: document.getElementById('q').value.trim(),
        author: document.getElementById('author').value.trim(),
        isbn: document.getElementById('isbn').value.trim(),
        category: document.getElementById('category').value.trim(),
        year_from: document.getElementById('year_from').value,
        year_to: document.getElementById('year_to').value,
        available: document.getElementById('available').value,
        lang: LANG,
        limit: 60,
    });

    try {
        const r = await fetch('../../api/opac_search.php?' + params.toString(), { cache: 'no-store' });
        const data = await r.json();
        if (!data.ok) throw new Error('Bad response');
        document.getElementById('count').textContent = data.count;
        if (data.expanded && data.expanded.toLowerCase() !== (data.query || '').toLowerCase()) {
            document.getElementById('expandedNote').textContent = 'Expanded: ' + data.expanded;
        }
        if (!data.results.length) {
            grid.innerHTML = '<div class="no-results" style="grid-column:1/-1"><h3>No books found</h3><p style="margin-top:6px">Try different keywords or check spelling.</p></div>';
        } else {
            grid.innerHTML = data.results.map(cardHTML).join('');
        }
    } catch (e) {
        grid.innerHTML = '<div class="no-results" style="grid-column:1/-1"><h3>Search error</h3><p>' + esc(e.message) + '</p></div>';
    }
}

function resetForm() {
    ['q','author','isbn','category','year_from','year_to','available'].forEach(id => document.getElementById(id).value = '');
    setLang('en');
    document.getElementById('grid').innerHTML = '';
    document.getElementById('count').textContent = '0';
    document.getElementById('expandedNote').textContent = '';
}

// initial load
window.addEventListener('DOMContentLoaded', runSearch);
</script>
</body>
</html>