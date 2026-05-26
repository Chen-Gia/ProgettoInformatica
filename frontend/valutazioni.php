<?php
require_once "../backend/config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

// ──────────────────────────────────────────────────────────────────────
// AZIONI AJAX
// ──────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'vota') {
        $branoId = (int) $_POST['brano_id'];
        $voto    = (int) $_POST['voto'];

        if ($voto < 1 || $voto > 5) {
            echo json_encode(['status' => 'error', 'message' => 'Voto non valido']);
            exit;
        }

        try {
            $s = $connessione->prepare("
                INSERT INTO valutazioni (utente_username, brano_id, voto)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE voto = VALUES(voto), created_at = CURRENT_TIMESTAMP
            ");
            $s->execute([$username, $branoId, $voto]);

            $avg = $connessione->prepare("
                SELECT ROUND(AVG(voto), 1) AS media, COUNT(*) AS totale
                FROM valutazioni WHERE brano_id = ?
            ");
            $avg->execute([$branoId]);
            $row = $avg->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'ok', 'media' => $row['media'], 'totale' => $row['totale']]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ──────────────────────────────────────────────────────────────────────
// CARICA BRANI con media e voto personale (subquery separate)
// ──────────────────────────────────────────────────────────────────────

$stmt = $connessione->prepare("
    SELECT
        b.id_brano,
        b.titolo,
        b.anno,
        b.genere,
        b.durata,
        b.artwork_url,
        b.preview_url,
        a.nome              AS artista,
        stats.media_voti,
        stats.totale_voti,
        mio.voto            AS mio_voto
    FROM brani b
    LEFT JOIN artisti a ON b.artista_id = a.id_artista
    LEFT JOIN (
        SELECT brano_id,
               ROUND(AVG(voto), 1) AS media_voti,
               COUNT(*)            AS totale_voti
        FROM valutazioni
        GROUP BY brano_id
    ) stats ON b.id_brano = stats.brano_id
    LEFT JOIN valutazioni mio ON b.id_brano = mio.brano_id AND mio.utente_username = ?
    ORDER BY stats.media_voti DESC, stats.totale_voti DESC, b.titolo ASC
");
$stmt->execute([$username]);
$brani = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valutazioni - Trackly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script><?php include 'card.php'; ?></script>

    <style>
        /* ── Stelle ── */
        .star-widget {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 3px;
            margin: 8px 0 4px;
        }
        .star-widget input[type="radio"] { display: none; }
        .star-widget label {
            font-size: 22px;
            color: #444;
            cursor: pointer;
            transition: color .15s, transform .12s;
            line-height: 1;
        }
        .star-widget label:hover,
        .star-widget label:hover ~ label,
        .star-widget input:checked ~ label { color: #f5c518; }
        .star-widget label:hover { transform: scale(1.2); }

        /* ── Badge media ── */
        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(245, 197, 24, 0.12);
            border: 1px solid rgba(245, 197, 24, 0.35);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 12px;
            color: #f5c518;
            margin-bottom: 4px;
        }
        .rating-badge.no-votes {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.1);
            color: #555;
        }

        /* ── Filtri ── */
        .filters-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .filter-btn {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: #b3b3b3;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 13px;
            cursor: pointer;
            transition: all .2s;
        }
        .filter-btn.active, .filter-btn:hover {
            background: rgba(29,185,84,0.2);
            border-color: #1DB954;
            color: #1DB954;
        }
        .filter-input {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 13px;
            outline: none;
            flex: 1;
            min-width: 160px;
        }
        .filter-input::placeholder { color: #555; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #b3b3b3;
            grid-column: 1/-1;
        }
        .empty-state i { display:block; font-size:64px; margin-bottom:20px; opacity:.4; }
    </style>
</head>
<body>
<div class="container">
    <?php include 'sidebar.php' ?>
    <div class="main-content">
        <?php include 'topbar.php' ?>
        <div class="content-area">

            <div class="section-title">
                <i class="fas fa-star"></i> Valutazioni Brani
            </div>

            <div class="filters-bar">
                <input class="filter-input" id="searchInput" type="text"
                       placeholder="Filtra per titolo o artista..."
                       oninput="filtra()">
                <button class="filter-btn active" data-filter="tutti" onclick="setFilter('tutti', this)">
                    Tutti (<?= count($brani) ?>)
                </button>
                <button class="filter-btn" data-filter="votati" onclick="setFilter('votati', this)">
                    <i class="fas fa-star"></i> Già votati
                </button>
                <button class="filter-btn" data-filter="non-votati" onclick="setFilter('non-votati', this)">
                    <i class="far fa-star"></i> Non ancora votati
                </button>
            </div>

            <div class="grid-container" id="rating-grid"></div>

        </div>
    </div>
</div>

<script>
// Dati passati da PHP al JS — stesso pattern di preferiti.php
const braniFull = <?php echo json_encode($brani); ?>;

// ──────────────────────────────────────────────────────────────────────
// TEMPLATE CARD VALUTAZIONI
// Replica la struttura di getCardTemplate() da card.php,
// aggiunge badge media e widget stelle come sezioni extra.
// ──────────────────────────────────────────────────────────────────────

function getCardTemplateValutazioni(b) {
    const id      = b.id_brano;
    const mioVoto = b.mio_voto ? parseInt(b.mio_voto) : 0;
    const totale  = b.totale_voti ? parseInt(b.totale_voti) : 0;
    const media   = b.media_voti ?? null;
    const durata  = b.durata ? parseInt(b.durata) + 's' : 'N/A';

    // Bottone riproduci (stesso stile di getCardTemplate)
    const playButton = b.preview_url
        ? `<audio id="audio_${id}" src="${b.preview_url}"></audio>
           <button class="card-action" style="margin-bottom:6px" onclick="togglePreview(${id})">
               <i class="fas fa-play" id="icon_${id}"></i> Riproduci
           </button>`
        : `<button class="card-action" style="margin-bottom:6px; opacity:.5; cursor:not-allowed;">
               <i class="fas fa-play"></i> Riproduci
           </button>`;

    // Badge media voti
    const badgeClass = totale === 0 ? 'rating-badge no-votes' : 'rating-badge';
    const badgeHtml = `
        <span class="${badgeClass}" id="badge_${id}">
            <i class="fas fa-star"></i>
            <span id="media_${id}">${totale > 0 ? media + ' / 5' : 'Nessun voto'}</span>
            <span id="totale_${id}" style="opacity:.65">${totale > 0 ? '(' + totale + ' ' + (totale === 1 ? 'voto' : 'voti') + ')' : ''}</span>
        </span>`;

    // Widget stelle (5 → 1, layout row-reverse come nel CSS)
    let starsHtml = `<div class="star-widget" id="stars_${id}">`;
    for (let s = 5; s >= 1; s--) {
        starsHtml += `
            <input type="radio"
                   id="star_${id}_${s}"
                   name="voto_${id}"
                   value="${s}"
                   ${mioVoto === s ? 'checked' : ''}
                   onchange="vota(${id}, ${s})">
            <label for="star_${id}_${s}" title="${s} ${s === 1 ? 'stella' : 'stelle'}">&#9733;</label>`;
    }
    starsHtml += `</div>`;

    return `<div class="card"
                 id="card_${id}"
                 data-titolo="${(b.titolo ?? '').toLowerCase()}"
                 data-artista="${(b.artista ?? '').toLowerCase()}"
                 data-votato="${mioVoto > 0 ? '1' : '0'}">
        <div class="card-image">
            ${b.artwork_url
                ? `<img src="${b.artwork_url}" alt="${b.titolo ?? ''}" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">`
                : `<i class="fas fa-music"></i>`}
        </div>
        <div class="card-title">${b.titolo ?? 'Senza titolo'}</div>
        <div class="card-subtitle">${b.artista ?? 'Artista sconosciuto'}</div>
        <div class="card-subtitle" style="font-size:11px; opacity:.6">
            ${b.anno ?? ''} · ${b.genere ?? ''} · ${durata}
        </div>
        <br>
        ${playButton}
        ${badgeHtml}
        ${starsHtml}
    </div>`;
}

// ──────────────────────────────────────────────────────────────────────
// RENDER GRIGLIA
// ──────────────────────────────────────────────────────────────────────

const grid = document.getElementById('rating-grid');

if (braniFull.length > 0) {
    grid.innerHTML = braniFull.map(b => getCardTemplateValutazioni(b)).join('');
} else {
    grid.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-music"></i>
            <p>Nessun brano nel catalogo.</p>
            <a href="cerca.php" class="card-action" style="display:inline-block; text-decoration:none; padding:12px 30px;">
                <i class="fas fa-search"></i> Cerca Musica
            </a>
        </div>`;
}

// ──────────────────────────────────────────────────────────────────────
// VOTAZIONE
// ──────────────────────────────────────────────────────────────────────

async function vota(branoId, voto) {
    const stars = document.getElementById('stars_' + branoId);
    stars.querySelectorAll('input').forEach(r => r.disabled = true);

    try {
        const fd = new FormData();
        fd.append('action',   'vota');
        fd.append('brano_id', branoId);
        fd.append('voto',     voto);

        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            aggiornaMedia(branoId, data.media, data.totale);
            document.getElementById('card_' + branoId).dataset.votato = '1';
        }
    } catch {}

    stars.querySelectorAll('input').forEach(r => r.disabled = false);
}

function aggiornaMedia(branoId, media, totale) {
    const badge    = document.getElementById('badge_'  + branoId);
    const mediaEl  = document.getElementById('media_'  + branoId);
    const totaleEl = document.getElementById('totale_' + branoId);

    if (media && totale > 0) {
        mediaEl.textContent  = media + ' / 5';
        totaleEl.textContent = '(' + totale + ' ' + (totale === 1 ? 'voto' : 'voti') + ')';
        badge.classList.remove('no-votes');
    } else {
        mediaEl.textContent  = 'Nessun voto';
        totaleEl.textContent = '';
        badge.classList.add('no-votes');
    }
}

// ──────────────────────────────────────────────────────────────────────
// FILTRI
// ──────────────────────────────────────────────────────────────────────

let filtroAttivo = 'tutti';

function setFilter(tipo, btn) {
    filtroAttivo = tipo;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filtra();
}

function filtra() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('#rating-grid .card').forEach(card => {
        const matchTesto  = !q || card.dataset.titolo.includes(q) || card.dataset.artista.includes(q);
        const votato      = card.dataset.votato === '1';
        const matchFiltro =
            filtroAttivo === 'tutti'      ? true :
            filtroAttivo === 'votati'     ? votato :
            filtroAttivo === 'non-votati' ? !votato : true;
        card.style.display = (matchTesto && matchFiltro) ? '' : 'none';
    });
}
</script>
</body>
</html>