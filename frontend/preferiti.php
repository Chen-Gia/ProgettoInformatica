<?php
require_once "../backend/config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'remove_favorite') {
        $branoId = (int) $_POST['brano_id'];
        try {
            $s = $connessione->prepare("DELETE FROM preferiti WHERE utente_username = ? AND brano_id = ?");
            $s->execute([$username, $branoId]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_playlists') {
        try {
            $branoId = (int) ($_POST['brano_id'] ?? 0);
            $s = $connessione->prepare("SELECT id_playlist, nome FROM playlist WHERE utente_username = ? ORDER BY nome");
            $s->execute([$username]);
            $playlists = $s->fetchAll(PDO::FETCH_ASSOC);
            foreach ($playlists as &$pl) {
                $checkStmt = $connessione->prepare("SELECT 1 FROM playlist_brani WHERE playlist_id = ? AND brano_id = ?");
                $checkStmt->execute([$pl['id_playlist'], $branoId]);
                $pl['has_brano'] = $checkStmt->fetchColumn() !== false;
            }
            echo json_encode(['status' => 'ok', 'playlists' => $playlists]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'add_to_playlist') {
        $branoId    = (int) $_POST['brano_id'];
        $playlistId = (int) $_POST['playlist_id'];
        try {
            $s = $connessione->prepare("INSERT INTO playlist_brani (playlist_id, brano_id) VALUES (?, ?)");
            $s->execute([$playlistId, $branoId]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(strpos($e->getMessage(), 'Duplicate') !== false
                ? ['status' => 'exists']
                : ['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

$stmt_preferiti = $connessione->prepare("
    SELECT b.id_brano, b.titolo, b.durata, b.anno, b.genere, b.preview_url, a.nome as artista
    FROM preferiti p
    JOIN brani b ON p.brano_id = b.id_brano
    JOIN artisti a ON b.artista_id = a.id_artista
    WHERE p.utente_username = ?
    ORDER BY p.id_preferito DESC
");
$stmt_preferiti->execute([$username]);
$brani_preferiti = $stmt_preferiti->fetchAll(PDO::FETCH_ASSOC);

$stmt_playlist = $connessione->prepare("
    SELECT id_playlist, nome
    FROM playlist
    WHERE utente_username = ?
    ORDER BY id_playlist DESC
");
$stmt_playlist->execute([$username]);
$playlist_utente = $stmt_playlist->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I Miei Preferiti - Trackly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="card.php"></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php' ?>
        <div class="main-content">
            <?php include 'topbar.php' ?>
            <div class="content-area">

                <div class="section-title">
                    <i class="fas fa-heart"></i> I Tuoi Brani Preferiti
                </div>

                <div class="grid-container" id="preferiti-grid"></div>

            </div>
        </div>
    </div>

    <style>
        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-10px); }
        }
        .empty-state { text-align: center; padding: 60px 20px; color: #b3b3b3; }
        .empty-state i { display: block; font-size: 64px; margin-bottom: 20px; opacity: 0.5; }
        .empty-state p { font-size: 18px; margin-bottom: 20px; }
    </style>

    <script>
        const braniPreferiti = <?php echo json_encode($brani_preferiti); ?>;
        const grid = document.getElementById('preferiti-grid');

        if (braniPreferiti.length > 0) {
            grid.innerHTML = braniPreferiti.map(b =>
                getCardTemplate({ ...b, durata: b.durata ? parseInt(b.durata) + 's' : 'N/A' })
            ).join('');
        } else {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1/-1;">
                    <i class="fas fa-heart"></i>
                    <p>Non hai ancora brani nei preferiti.</p>
                    <a href="cerca.php" class="hero-btn" style="text-decoration:none; display:inline-block; padding:12px 30px; background:#1DB954; color:white; border-radius:8px; font-weight:500;">
                        <i class="fas fa-search"></i> Inizia a Cercare
                    </a>
                </div>`;
        }

        async function rimuoviPreferito(btn, branoId) {
            if (!confirm('Sei sicuro di voler rimuovere questo brano dai preferiti?')) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rimozione...';

            const fd = new FormData();
            fd.append('action', 'remove_favorite');
            fd.append('brano_id', branoId);

            try {
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'ok') {
                    const card = document.getElementById('brano_' + branoId);
                    card.style.animation = 'fadeOut 0.3s ease forwards';
                    setTimeout(() => {
                        card.remove();
                        if (grid.children.length === 0) {
                            grid.innerHTML = `
                                <div class="empty-state" style="grid-column: 1/-1;">
                                    <i class="fas fa-heart"></i>
                                    <p>Non hai ancora brani nei preferiti.</p>
                                    <a href="cerca.php" class="hero-btn" style="text-decoration:none; display:inline-block; padding:12px 30px; background:#1DB954; color:white; border-radius:8px; font-weight:500;">
                                        <i class="fas fa-search"></i> Inizia a Cercare
                                    </a>
                                </div>`;
                        }
                    }, 300);
                } else {
                    alert('Errore nella rimozione del brano');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-trash"></i> Rimuovi';
                }
            } catch (err) {
                alert('Errore: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash"></i> Rimuovi';
            }
        }

        async function mostraPlaylistDialog(branoId) {
            try {
                const fd = new FormData();
                fd.append('action', 'get_playlists');
                fd.append('brano_id', branoId);
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status !== 'ok' || !data.playlists.length) {
                    alert('Non hai playlist. Creane una dalla home!');
                    return;
                }

                const playlistHtml = data.playlists.map(p =>
                    p.has_brano
                        ? `<option value="${p.id_playlist}" disabled>✅ ${p.nome} (già aggiunto)</option>`
                        : `<option value="${p.id_playlist}">${p.nome}</option>`
                ).join('');

                const dialog = document.createElement('div');
                dialog.id = 'playlist-dialog-overlay';
                dialog.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:9999;';
                dialog.innerHTML = `
                    <div style="background:linear-gradient(135deg, rgba(40,40,40,0.95), rgba(30,30,30,0.95)); padding:30px; border-radius:15px; box-shadow:0 8px 32px rgba(29,185,84,0.3); max-width:450px; width:90%; border:1px solid rgba(29,185,84,0.3);">
                        <h3 style="margin-top:0; margin-bottom:20px; color:#1DB954; font-size:20px; display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-list"></i> Aggiungi a Playlist
                        </h3>
                        <select id="playlist_select" style="padding:12px; border-radius:8px; border:1px solid rgba(29,185,84,0.5); width:100%; cursor:pointer; font-size:14px; margin-bottom:20px; background:rgba(0,0,0,0.3); color:#fff;">
                            <option value="">-- Seleziona una playlist --</option>
                            ${playlistHtml}
                        </select>
                        <div style="display:flex; gap:10px; justify-content:flex-end;">
                            <button type="button" onclick="chiudiDialog()" style="padding:10px 20px; border-radius:8px; border:1px solid rgba(255,255,255,0.3); background:rgba(0,0,0,0.3); color:#fff; cursor:pointer;">
                                <i class="fas fa-times"></i> Annulla
                            </button>
                            <button type="button" onclick="aggiungiPlaylist(${branoId})" style="padding:10px 20px; border-radius:8px; border:none; background:linear-gradient(135deg, #1DB954, #1ed760); color:#000; cursor:pointer; font-weight:600;">
                                <i class="fas fa-plus"></i> Aggiungi
                            </button>
                        </div>
                    </div>`;
                document.body.appendChild(dialog);

                dialog.addEventListener('click', e => { if (e.target === dialog) chiudiDialog(); });
                const handleEsc = e => { if (e.key === 'Escape') { chiudiDialog(); document.removeEventListener('keydown', handleEsc); } };
                document.addEventListener('keydown', handleEsc);

            } catch {
                alert('❌ Errore nel caricamento delle playlist');
            }
        }

        function chiudiDialog() {
            const dialog = document.getElementById('playlist-dialog-overlay');
            if (dialog) {
                dialog.style.display = 'none';
                setTimeout(() => dialog?.parentNode?.removeChild(dialog), 100);
            }
        }

        async function aggiungiPlaylist(branoId) {
            const select = document.getElementById('playlist_select');
            if (!select?.value) { alert('Seleziona una playlist'); return; }
            const fd = new FormData();
            fd.append('action', 'add_to_playlist');
            fd.append('brano_id', branoId);
            fd.append('playlist_id', select.value);
            try {
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                alert(data.status === 'ok'     ? '✅ Brano aggiunto alla playlist!'
                    : data.status === 'exists' ? 'ℹ️ Brano già presente in questa playlist'
                    : '❌ Errore: ' + (data.message || 'Errore sconosciuto'));
                chiudiDialog();
            } catch (err) {
                alert('❌ Errore: ' + err.message);
            }
        }
    </script>
</body>
</html>