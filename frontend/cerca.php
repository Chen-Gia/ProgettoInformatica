<?php
require_once "config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

$stmt_playlist = $connessione->prepare("
    SELECT id_playlist, nome
    FROM playlist
    WHERE utente_username = ?
    ORDER BY id_playlist DESC
");
$stmt_playlist->execute([$username]);
$playlist_utente = $stmt_playlist->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'search') {
        $url = 'https://itunes.apple.com/search?' . http_build_query([
            'term'    => $_POST['query'],
            'media'   => 'music',
            'limit'   => 50,
            'country' => 'it',
            'lang'    => 'it_it'
        ]);
        try {
            $r = file_get_contents($url);
            echo $r !== false ? $r : json_encode(['results' => []]);
        } catch (Exception $e) {
            echo json_encode(['results' => []]);
        }
        exit;
    }

    if ($_POST['action'] === 'search_db') {
        $query = '%' . $_POST['query'] . '%';
        try {
            $s = $connessione->prepare("
                SELECT b.id_brano, b.titolo, b.durata, b.anno, b.genere,
                       a.nome as artista
                FROM brani b
                JOIN artisti a ON b.artista_id = a.id_artista
                WHERE b.titolo LIKE ? OR a.nome LIKE ?
                ORDER BY b.titolo
                LIMIT 50
            ");
            $s->execute([$query, $query]);
            $brani = $s->fetchAll(PDO::FETCH_ASSOC);
            $results = array_map(function ($b) {
                return [
                    'trackId'          => $b['id_brano'],
                    'trackName'        => $b['titolo'],
                    'artistName'       => $b['artista'],
                    'releaseDate'      => $b['anno'] . '-01-01',
                    'primaryGenreName' => $b['genere'],
                    'trackTimeMillis'  => ($b['durata'] ?? 0) * 1000,
                    'artworkUrl100'    => '',
                    'isFromDB'         => true
                ];
            }, $brani);
            echo json_encode(['results' => $results]);
        } catch (PDOException $e) {
            echo json_encode(['results' => []]);
        }
        exit;
    }

    if ($_POST['action'] === 'save') {
        $titolo = $_POST['titolo'];
        $artista = $_POST['artista'];
        $anno   = !empty($_POST['anno'])   ? (int) $_POST['anno']   : null;
        $durata = !empty($_POST['durata']) ? (int) $_POST['durata'] : null;
        $genere = !empty($_POST['genere']) ? $_POST['genere']        : null;
        try {
            $s = $connessione->prepare("SELECT id_artista FROM artisti WHERE nome = ?");
            $s->execute([$artista]);
            $result = $s->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $aid = $result['id_artista'];
            } else {
                $s = $connessione->prepare("INSERT INTO artisti (nome) VALUES (?)");
                $s->execute([$artista]);
                $aid = $connessione->lastInsertId();
            }
            $s = $connessione->prepare("SELECT id_brano FROM brani WHERE titolo = ? AND artista_id = ?");
            $s->execute([$titolo, $aid]);
            $existingBrano = $s->fetch(PDO::FETCH_ASSOC);
            if ($existingBrano) {
                echo json_encode(['status' => 'exists', 'brano_id' => $existingBrano['id_brano']]);
                exit;
            }
            $s = $connessione->prepare("INSERT INTO brani (titolo, artista_id, anno, durata, genere) VALUES (?, ?, ?, ?, ?)");
            $s->execute([$titolo, $aid, $anno, $durata, $genere]);
            echo json_encode(['status' => 'ok', 'brano_id' => $connessione->lastInsertId()]);
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

    if ($_POST['action'] === 'add_favorite') {
        $branoId = (int) $_POST['brano_id'];
        try {
            $s = $connessione->prepare("INSERT INTO preferiti (utente_username, brano_id) VALUES (?, ?)");
            $s->execute([$username, $branoId]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(strpos($e->getMessage(), 'Duplicate') !== false
                ? ['status' => 'exists']
                : ['status' => 'error', 'message' => $e->getMessage()]);
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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerca Musica - Trackly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script><?php include 'card.php'; ?></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php' ?>
        <div class="main-content">
            <?php include 'topbar.php' ?>
            <div class="content-area">

                <div class="section-title">
                    <i class="fas fa-search"></i> Cerca Musica
                </div>

                <div style="display:flex; gap:10px; margin-bottom:30px;">
                    <input id="q" type="text" placeholder="Cerca brano o artista..."
                        style="flex:1; padding:10px; border-radius:8px; border:1px solid #ccc;">
                    <button onclick="cerca()" class="card-action">
                        <i class="fas fa-search"></i> Cerca
                    </button>
                </div>

                <div id="risultati" class="grid-container"></div>

            </div>
        </div>
    </div>

    <script>
        const userLevel = <?php echo $livello; ?>;

        async function cerca() {
            const query = document.getElementById('q').value.trim();
            if (!query) return;

            document.getElementById('risultati').innerHTML = '<p>Ricerca in corso...</p>';

            try {
                const fd = new FormData();
                fd.append('action', userLevel === 0 ? 'search' : 'search_db');
                fd.append('query', query);

                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                const brani = data.results ?? [];

                if (!brani.length) {
                    document.getElementById('risultati').innerHTML = '<p>Nessun risultato.</p>';
                    return;
                }

                document.getElementById('risultati').innerHTML = brani.map(b =>
                    userLevel === 0 ? getCardTemplateLV0(b) : getCardTemplateLV1(b)
                ).join('');

            } catch (err) {
                document.getElementById('risultati').innerHTML = '<p style="color:red;">❌ Errore: ' + err.message + '</p>';
            }
        }

        async function salva(btn, brano) {
            btn.disabled = true;
            const fd = new FormData();
            fd.append('action', 'save');
            const branoObj = typeof brano === 'string' ? JSON.parse(brano) : brano;
            Object.entries(branoObj).forEach(([k, v]) => fd.append(k, v));
            try {
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'ok' || data.status === 'exists') {
                    const actionsDiv = btn.closest('.card').querySelector('[data-track-id]');
                    btn.textContent = data.status === 'ok' ? '✅ Aggiunto!' : 'ℹ️ Già presente';
                    await mostraAzioni(actionsDiv, data.brano_id);
                } else {
                    btn.textContent = '❌ Errore';
                    btn.disabled = false;
                }
            } catch {
                btn.textContent = '❌ Errore';
                btn.disabled = false;
            }
        }

        async function mostraAzioni(container, branoId) {
            container.innerHTML = `
                <button class="card-action" style="margin-top:8px; background:#e74c3c; padding:10px; border:none; border-radius:8px; color:white; cursor:pointer; width:100%; font-weight:500;" onclick="aggiungiPreferito(this, ${branoId})">
                    <i class="fas fa-heart"></i> Aggiungi ai Preferiti
                </button>
                <button class="card-action" style="margin-top:6px;" onclick="mostraPlaylistDialog(${branoId})">
                    <i class="fas fa-list"></i> Aggiungi a Playlist
                </button>`;
        }

        async function aggiungiPreferito(btn, branoId) {
            btn.disabled = true;
            const fd = new FormData();
            fd.append('action', 'add_favorite');
            fd.append('brano_id', branoId);
            try {
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'ok') {
                    btn.textContent = '❤️ Aggiunto ai Preferiti!';
                    btn.style.color = '#e74c3c';
                } else if (data.status === 'exists') {
                    btn.textContent = '❤️ Già nei Preferiti';
                    btn.style.color = '#e74c3c';
                } else {
                    btn.textContent = '❌ Errore';
                    btn.disabled = false;
                }
            } catch {
                btn.textContent = '❌ Errore';
                btn.disabled = false;
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

        document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') cerca(); });
    </script>
</body>
</html>