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

    // Aggiunge un commento
    if ($_POST['action'] === 'aggiungi') {
        $branoId  = (int) $_POST['brano_id'];
        $testo    = trim($_POST['testo'] ?? '');

        if ($branoId <= 0 || $testo === '') {
            echo json_encode(['status' => 'error', 'message' => 'Dati mancanti']);
            exit;
        }
        if (mb_strlen($testo) > 500) {
            echo json_encode(['status' => 'error', 'message' => 'Commento troppo lungo (max 500 caratteri)']);
            exit;
        }

        try {
            $s = $connessione->prepare("
                INSERT INTO commenti (utente_username, brano_id, contenuto)
                VALUES (?, ?, ?)
            ");
            $s->execute([$username, $branoId, $testo]);
            $newId = $connessione->lastInsertId();

            echo json_encode([
                'status'     => 'ok',
                'id'         => $newId,
                'username'   => $username,
                'contenuto'  => htmlspecialchars($testo),
                'created_at' => date('d/m/Y H:i'),
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Elimina un commento (solo il proprio o admin livello >= 2)
    if ($_POST['action'] === 'elimina') {
        $commentoId = (int) $_POST['commento_id'];
        try {
            if ($livello >= 2) {
                // Admin: può eliminare qualsiasi commento
                $s = $connessione->prepare("DELETE FROM commenti WHERE id_commento = ?");
                $s->execute([$commentoId]);
            } else {
                // Utente normale: solo i propri
                $s = $connessione->prepare("DELETE FROM commenti WHERE id_commento = ? AND utente_username = ?");
                $s->execute([$commentoId, $username]);
            }
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ──────────────────────────────────────────────────────────────────────
// CARICA BRANI con numero commenti
// ──────────────────────────────────────────────────────────────────────

$stmt = $connessione->prepare("
    SELECT
        b.id_brano,
        b.titolo,
        b.anno,
        b.genere,
        b.artwork_url,
        a.nome                  AS artista,
        COUNT(c.id_commento)    AS totale_commenti
    FROM brani b
    LEFT JOIN artisti a   ON b.artista_id = a.id_artista
    LEFT JOIN commenti c  ON b.id_brano   = c.brano_id
    GROUP BY b.id_brano, b.titolo, b.anno, b.genere, b.artwork_url, a.nome
    ORDER BY totale_commenti DESC, b.titolo ASC
");
$stmt->execute();
$brani = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carica tutti i commenti in un colpo solo, ordinati per brano e data
$stmtC = $connessione->prepare("
    SELECT c.id_commento, c.brano_id, c.utente_username, c.contenuto,
           DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') AS data
    FROM commenti c
    ORDER BY c.brano_id ASC, c.created_at DESC
");
$stmtC->execute();
$tuttiCommenti = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// Raggruppa commenti per brano_id
$commentiPerBrano = [];
foreach ($tuttiCommenti as $c) {
    $commentiPerBrano[$c['brano_id']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commenti - Trackly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        /* ── Pannello brano ── */
        .brano-panel {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            margin-bottom: 18px;
            overflow: hidden;
            transition: border-color .2s;
        }
        .brano-panel:focus-within {
            border-color: rgba(29,185,84,0.4);
        }

        /* ── Header cliccabile del pannello ── */
        .brano-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            cursor: pointer;
            user-select: none;
        }
        .brano-header:hover { background: rgba(255,255,255,0.03); }

        .brano-thumb {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
            font-size: 20px;
        }
        .brano-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .brano-info { flex: 1; min-width: 0; }
        .brano-info .titolo {
            font-weight: 600;
            font-size: 15px;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .brano-info .sub {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }

        .commenti-count {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #888;
            flex-shrink: 0;
        }
        .commenti-count i { color: #1DB954; }

        .toggle-icon {
            color: #555;
            font-size: 13px;
            transition: transform .25s;
            flex-shrink: 0;
        }
        .brano-panel.open .toggle-icon { transform: rotate(180deg); }

        /* ── Corpo collassabile ── */
        .brano-body {
            display: none;
            padding: 0 20px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .brano-panel.open .brano-body { display: block; }

        /* ── Lista commenti ── */
        .commenti-list { margin-top: 14px; }

        .commento-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .commento-item:last-child { border-bottom: none; }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1DB954, #17a844);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: #000;
            flex-shrink: 0;
        }
        .avatar.mine { background: linear-gradient(135deg, #1a73e8, #1557c0); color: #fff; }

        .commento-content { flex: 1; min-width: 0; }
        .commento-meta {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }
        .commento-user {
            font-weight: 600;
            font-size: 13px;
            color: #fff;
        }
        .commento-user.mine { color: #1a73e8; }
        .commento-data {
            font-size: 11px;
            color: #555;
        }
        .commento-testo {
            font-size: 14px;
            color: #ccc;
            line-height: 1.5;
            word-break: break-word;
        }

        .btn-elimina {
            background: none;
            border: none;
            color: #555;
            cursor: pointer;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            transition: color .15s, background .15s;
            flex-shrink: 0;
            align-self: flex-start;
            margin-top: 2px;
        }
        .btn-elimina:hover { color: #e74c3c; background: rgba(231,76,60,0.1); }

        /* ── Form nuovo commento ── */
        .commento-form {
            margin-top: 16px;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .commento-form textarea {
            flex: 1;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            padding: 10px 14px;
            resize: none;
            outline: none;
            min-height: 60px;
            max-height: 140px;
            font-family: inherit;
            transition: border-color .2s;
            line-height: 1.5;
        }
        .commento-form textarea::placeholder { color: #555; }
        .commento-form textarea:focus { border-color: #1DB954; }

        .btn-invia {
            background: #1DB954;
            border: none;
            border-radius: 10px;
            color: #000;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 20px;
            cursor: pointer;
            transition: background .2s, transform .1s;
            white-space: nowrap;
            height: 60px;
        }
        .btn-invia:hover { background: #17a844; }
        .btn-invia:active { transform: scale(0.97); }
        .btn-invia:disabled { background: #333; color: #666; cursor: not-allowed; }

        .char-count {
            font-size: 11px;
            color: #555;
            text-align: right;
            margin-top: 4px;
        }
        .char-count.warn { color: #e67e22; }
        .char-count.over { color: #e74c3c; }

        /* ── Nessun commento ── */
        .no-commenti {
            text-align: center;
            padding: 20px;
            color: #555;
            font-size: 13px;
        }

        /* ── Filtro ricerca ── */
        .filters-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filter-input {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            border-radius: 20px;
            padding: 8px 18px;
            font-size: 13px;
            outline: none;
            flex: 1;
            min-width: 200px;
        }
        .filter-input::placeholder { color: #555; }
        .filter-input:focus { border-color: #1DB954; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #b3b3b3;
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
                <i class="fas fa-comments"></i> Commenti
            </div>

            <?php if (empty($brani)): ?>
                <div class="empty-state">
                    <i class="fas fa-music"></i>
                    <p>Nessun brano nel catalogo.</p>
                </div>
            <?php else: ?>

                <div class="filters-bar">
                    <input class="filter-input" id="searchInput" type="text"
                           placeholder="Cerca brano o artista..."
                           oninput="filtra()">
                </div>

                <div id="panels-container">
                <?php foreach ($brani as $b): ?>
                    <?php
                        $id       = (int) $b['id_brano'];
                        $commenti = $commentiPerBrano[$id] ?? [];
                        $nC       = count($commenti);
                    ?>
                    <div class="brano-panel"
                         id="panel_<?= $id ?>"
                         data-titolo="<?= htmlspecialchars(strtolower($b['titolo'])) ?>"
                         data-artista="<?= htmlspecialchars(strtolower($b['artista'] ?? '')) ?>">

                        <!-- Header cliccabile -->
                        <div class="brano-header" onclick="togglePanel(<?= $id ?>)">
                            <div class="brano-thumb">
                                <?php if ($b['artwork_url']): ?>
                                    <img src="<?= htmlspecialchars($b['artwork_url']) ?>"
                                         alt="<?= htmlspecialchars($b['titolo']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-music"></i>
                                <?php endif; ?>
                            </div>
                            <div class="brano-info">
                                <div class="titolo"><?= htmlspecialchars($b['titolo']) ?></div>
                                <div class="sub">
                                    <?= htmlspecialchars($b['artista'] ?? 'Artista sconosciuto') ?>
                                    <?= $b['anno'] ? ' · ' . $b['anno'] : '' ?>
                                    <?= $b['genere'] ? ' · ' . htmlspecialchars($b['genere']) : '' ?>
                                </div>
                            </div>
                            <div class="commenti-count">
                                <i class="fas fa-comment"></i>
                                <span id="count_<?= $id ?>"><?= $nC ?></span>
                            </div>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>

                        <!-- Corpo collassabile -->
                        <div class="brano-body">

                            <!-- Lista commenti esistenti -->
                            <div class="commenti-list" id="list_<?= $id ?>">
                                <?php if (empty($commenti)): ?>
                                    <div class="no-commenti" id="nocomm_<?= $id ?>">
                                        <i class="far fa-comment-dots"></i> Nessun commento. Sii il primo!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($commenti as $c): ?>
                                        <?php $isMine = ($c['utente_username'] === $username); ?>
                                        <div class="commento-item" id="comm_<?= $c['id_commento'] ?>">
                                            <div class="avatar <?= $isMine ? 'mine' : '' ?>">
                                                <?= strtoupper(mb_substr($c['utente_username'], 0, 1)) ?>
                                            </div>
                                            <div class="commento-content">
                                                <div class="commento-meta">
                                                    <span class="commento-user <?= $isMine ? 'mine' : '' ?>">
                                                        <?= htmlspecialchars($c['utente_username']) ?>
                                                        <?= $isMine ? ' (tu)' : '' ?>
                                                    </span>
                                                    <span class="commento-data"><?= $c['data'] ?></span>
                                                </div>
                                                <div class="commento-testo"><?= nl2br(htmlspecialchars($c['contenuto'])) ?></div>
                                            </div>
                                            <?php if ($isMine || $livello >= 2): ?>
                                                <button class="btn-elimina"
                                                        title="Elimina commento"
                                                        onclick="eliminaCommento(<?= $c['id_commento'] ?>, <?= $id ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Form nuovo commento -->
                            <div class="commento-form">
                                <div style="flex:1">
                                    <textarea
                                        id="textarea_<?= $id ?>"
                                        placeholder="Scrivi un commento..."
                                        maxlength="500"
                                        rows="2"
                                        oninput="aggiornaContatore(<?= $id ?>)"
                                        onkeydown="inviaConCtrlEnter(event, <?= $id ?>)"
                                    ></textarea>
                                    <div class="char-count" id="chars_<?= $id ?>">0 / 500</div>
                                </div>
                                <button class="btn-invia"
                                        id="btn_<?= $id ?>"
                                        onclick="inviaCommento(<?= $id ?>)">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ──────────────────────────────────────────────────────────────────────
// TOGGLE PANNELLO
// ──────────────────────────────────────────────────────────────────────

function togglePanel(branoId) {
    const panel = document.getElementById('panel_' + branoId);
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        // Focus sulla textarea quando si apre
        setTimeout(() => {
            const ta = document.getElementById('textarea_' + branoId);
            if (ta) ta.focus();
        }, 50);
    }
}

// ──────────────────────────────────────────────────────────────────────
// CONTATORE CARATTERI
// ──────────────────────────────────────────────────────────────────────

function aggiornaContatore(branoId) {
    const ta    = document.getElementById('textarea_' + branoId);
    const el    = document.getElementById('chars_'    + branoId);
    const btn   = document.getElementById('btn_'      + branoId);
    const len   = ta.value.length;

    el.textContent = len + ' / 500';
    el.className   = 'char-count' + (len > 480 ? ' over' : len > 400 ? ' warn' : '');
    btn.disabled   = len === 0 || len > 500;
}

// Invia con Ctrl+Enter
function inviaConCtrlEnter(e, branoId) {
    if (e.key === 'Enter' && e.ctrlKey) {
        e.preventDefault();
        inviaCommento(branoId);
    }
}

// ──────────────────────────────────────────────────────────────────────
// INVIA COMMENTO
// ──────────────────────────────────────────────────────────────────────

async function inviaCommento(branoId) {
    const ta  = document.getElementById('textarea_' + branoId);
    const btn = document.getElementById('btn_'      + branoId);
    const testo = ta.value.trim();
    if (!testo) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const fd = new FormData();
        fd.append('action',   'aggiungi');
        fd.append('brano_id', branoId);
        fd.append('testo',    testo);

        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            // Rimuovi eventuale "nessun commento"
            const noComm = document.getElementById('nocomm_' + branoId);
            if (noComm) noComm.remove();

            // Prepend il nuovo commento in cima alla lista
            const list = document.getElementById('list_' + branoId);
            const html = creaHtmlCommento(data, branoId);
            list.insertAdjacentHTML('afterbegin', html);

            // Aggiorna contatore nel header
            const countEl = document.getElementById('count_' + branoId);
            countEl.textContent = parseInt(countEl.textContent) + 1;

            // Reset form
            ta.value = '';
            aggiornaContatore(branoId);
        }
    } catch {}

    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
}

function creaHtmlCommento(data, branoId) {
    const canDelete = true; // è sempre il proprio
    const deleteBtn = `<button class="btn-elimina" title="Elimina commento"
                               onclick="eliminaCommento(${data.id}, ${branoId})">
                           <i class="fas fa-trash-alt"></i>
                       </button>`;
    return `
        <div class="commento-item" id="comm_${data.id}">
            <div class="avatar mine">${data.username.charAt(0).toUpperCase()}</div>
            <div class="commento-content">
                <div class="commento-meta">
                    <span class="commento-user mine">${data.username} (tu)</span>
                    <span class="commento-data">${data.created_at}</span>
                </div>
                <div class="commento-testo">${data.contenuto.replace(/\n/g, '<br>')}</div>
            </div>
            ${deleteBtn}
        </div>`;
}

// ──────────────────────────────────────────────────────────────────────
// ELIMINA COMMENTO
// ──────────────────────────────────────────────────────────────────────

async function eliminaCommento(commentoId, branoId) {
    if (!confirm('Eliminare questo commento?')) return;

    try {
        const fd = new FormData();
        fd.append('action',      'elimina');
        fd.append('commento_id', commentoId);

        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            const el = document.getElementById('comm_' + commentoId);
            if (el) {
                el.style.transition = 'opacity .3s, transform .3s';
                el.style.opacity    = '0';
                el.style.transform  = 'translateX(-10px)';
                setTimeout(() => {
                    el.remove();
                    // Aggiorna contatore
                    const countEl = document.getElementById('count_' + branoId);
                    const nuovo   = Math.max(0, parseInt(countEl.textContent) - 1);
                    countEl.textContent = nuovo;

                    // Mostra "nessun commento" se la lista è vuota
                    const list = document.getElementById('list_' + branoId);
                    if (!list.querySelector('.commento-item')) {
                        list.innerHTML = `<div class="no-commenti" id="nocomm_${branoId}">
                            <i class="far fa-comment-dots"></i> Nessun commento. Sii il primo!
                        </div>`;
                    }
                }, 300);
            }
        }
    } catch {}
}

// ──────────────────────────────────────────────────────────────────────
// FILTRO RICERCA
// ──────────────────────────────────────────────────────────────────────

function filtra() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('#panels-container .brano-panel').forEach(panel => {
        const match = !q
            || panel.dataset.titolo.includes(q)
            || panel.dataset.artista.includes(q);
        panel.style.display = match ? '' : 'none';
    });
}
</script>
</body>
</html>