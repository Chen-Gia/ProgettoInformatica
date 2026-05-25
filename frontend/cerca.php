<?php
require_once "../backend/config.php";

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
                       b.artwork_url, b.preview_url, a.nome as artista
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
                    'artworkUrl100'    => $b['artwork_url'] ?? '',
                    'previewUrl'       => $b['preview_url'] ?? '',
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
        $titolo      = $_POST['titolo'];
        $artista     = $_POST['artista'];
        $anno        = !empty($_POST['anno'])        ? (int) $_POST['anno']        : null;
        $durata      = !empty($_POST['durata'])      ? (int) $_POST['durata']      : null;
        $genere      = !empty($_POST['genere'])      ? $_POST['genere']             : null;
        $artwork_url = !empty($_POST['img_url'])     ? trim($_POST['img_url'])      : null;
        $preview_url = !empty($_POST['preview_url']) ? trim($_POST['preview_url'])  : null;
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
                $upd = $connessione->prepare("
                    UPDATE brani
                    SET artwork_url = COALESCE(NULLIF(artwork_url,''), ?),
                        preview_url = COALESCE(NULLIF(preview_url,''), ?)
                    WHERE id_brano = ?
                ");
                $upd->execute([$artwork_url, $preview_url, $existingBrano['id_brano']]);
                echo json_encode(['status' => 'exists', 'brano_id' => $existingBrano['id_brano']]);
                exit;
            }
            $s = $connessione->prepare("INSERT INTO brani (titolo, artista_id, anno, durata, genere, artwork_url, preview_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $s->execute([$titolo, $aid, $anno, $durata, $genere, $artwork_url, $preview_url]);
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

    <script src="card.php"></script>
    <script>
        // ──────────────────────────────────────────────────────────────────────
        // LOGICA DI RICERCA - Pagina cerca.php
        // ──────────────────────────────────────────────────────────────────────

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

        // Event listener per ricerca al pressione del tasto Enter
        document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') cerca(); });
    </script>
</body>
</html>