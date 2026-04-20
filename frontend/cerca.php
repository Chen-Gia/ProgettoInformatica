<?php
require_once "config.php";

// Verificare se l'utente è loggato
if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello = $_SESSION['livello'];

// ── CONFIG ──────────────────────────────────────────────────────
$CLIENT_ID     = '0a9c97b529bf491faab931ba8959c990';
$CLIENT_SECRET = 'c3f6eba6f2ba4f49a906112834db8491';

// ── PRENDI TOKEN SPOTIFY ─────────────────────────────────────────
function getToken($id, $secret) {
    $r = file_get_contents('https://accounts.spotify.com/api/token', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Authorization: Basic ' . base64_encode("$id:$secret") . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => 'grant_type=client_credentials'
        ]]));
    return json_decode($r, true)['access_token'];
}

// ── GESTIONE AJAX ────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $token = getToken($CLIENT_ID, $CLIENT_SECRET);

    // CERCA
    if ($_POST['action'] === 'search') {
        $url = 'https://api.spotify.com/v1/search?' . http_build_query([
            'q' => $_POST['query'], 'type' => 'track', 'limit' => 10, 'market' => 'IT'
        ]);
        $r = file_get_contents($url, false, stream_context_create(['http' => [
            'header' => "Authorization: Bearer $token\r\n"
        ]]));
        echo $r; // manda risposta Spotify direttamente al frontend
        exit;
    }

    // SALVA NEL DB
    if ($_POST['action'] === 'save') {
        $titolo  = $_POST['titolo'];
        $artista = $_POST['artista'];
        $isrc    = $_POST['isrc'] ?: null;
        $anno    = $_POST['anno'] ?: null;
        $durata  = $_POST['durata'] ?: null;

        // Artista: trova o crea
        $s = $connessione->prepare("SELECT id FROM artisti WHERE nome = ?");
        $s->bind_param("s", $artista); $s->execute();
        $r = $s->get_result();
        if ($r->num_rows > 0) {
            $aid = $r->fetch_assoc()['id'];
        } else {
            $s = $connessione->prepare("INSERT INTO artisti (nome) VALUES (?)");
            $s->bind_param("s", $artista); $s->execute();
            $aid = $connessione->insert_id;
        }

        // Brano: controlla duplicato poi inserisci
        if ($isrc) {
            $s = $connessione->prepare("SELECT id FROM brani WHERE isrc = ?");
            $s->bind_param("s", $isrc); $s->execute();
            if ($s->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'exists']); exit;
            }
        }

        $s = $connessione->prepare("INSERT INTO brani (titolo, artista_id, isrc, anno, durata) VALUES (?, ?, ?, ?, ?)");
        $s->bind_param("sisii", $titolo, $aid, $isrc, $anno, $durata);
        $s->execute();
        echo json_encode(['status' => 'ok']); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerca su Spotify - Trackly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-music"></i>
                <span>Trackly</span>
            </div>

            <div class="nav-section">
                <h3>Menu</h3>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="cerca.php" class="active"><i class="fas fa-search"></i> Cerca</a></li>
                    <li><a href="#"><i class="fas fa-heart"></i> I Tuoi Mi Piace</a></li>
                    <li><a href="#"><i class="fas fa-list"></i> Coda</a></li>
                </ul>
            </div>

            <div class="nav-section">
                <h3>Playlist</h3>
                <ul>
                    <li><a href="#"><i class="fas fa-plus-circle"></i> Crea Playlist</a></li>
                    <li><a href="#"><i class="fas fa-headphones"></i> Playlist 1</a></li>
                    <li><a href="#"><i class="fas fa-headphones"></i> Playlist 2</a></li>
                    <li><a href="#"><i class="fas fa-headphones"></i> Playlist 3</a></li>
                </ul>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <!-- TOP BAR -->
            <div class="top-bar">
                <div class="search-box">
                    <input type="text" placeholder="Cerca brani, artisti, playlist...">
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Esci
                    </a>
                </div>
            </div>

            <!-- CONTENT AREA -->
            <div class="content-area">
                <div class="section-title">
                    <i class="fab fa-spotify" style="color:#1DB954"></i> Cerca su Spotify
                </div>

                <div style="display:flex; gap:10px; margin-bottom:30px;">
                    <input id="q" type="text" placeholder="Cerca brano o artista..." style="flex:1; padding:10px; border-radius:8px; border:1px solid #ccc;">
                    <button onclick="cerca()" class="card-action"><i class="fas fa-search"></i> Cerca</button>
                </div>

                <div id="risultati" class="grid-container"></div>
            </div>
        </div>
    </div>

<script>
async function cerca() {
    const query = document.getElementById('q').value.trim();
    if (!query) return;

    document.getElementById('risultati').innerHTML = '<p>Ricerca in corso...</p>';

    const fd = new FormData();
    fd.append('action', 'search');
    fd.append('query', query);

    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    const brani = data.tracks?.items ?? [];

    if (!brani.length) {
        document.getElementById('risultati').innerHTML = '<p>Nessun risultato.</p>';
        return;
    }

    document.getElementById('risultati').innerHTML = brani.map(b => `
        <div class="card">
            <div class="card-image">
                <img src="${b.album.images[1]?.url ?? ''}" style="width:100%; border-radius:8px;">
            </div>
            <div class="card-title">${b.name}</div>
            <div class="card-subtitle">${b.artists.map(a => a.name).join(', ')}</div>
            <div class="card-subtitle" style="font-size:11px; opacity:.6">${b.album.name} · ${b.album.release_date?.slice(0,4)}</div>
            <br>
            <button class="card-action" onclick='salva(this, ${JSON.stringify({
                titolo:  b.name,
                artista: b.artists[0].name,
                isrc:    b.external_ids?.isrc ?? '',
                anno:    b.album.release_date?.slice(0,4) ?? '',
                durata:  Math.round(b.duration_ms / 1000)
            })})'>
                <i class="fas fa-plus"></i> Aggiungi al DB
            </button>
        </div>
    `).join('');
}

async function salva(btn, brano) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'save');
    Object.entries(brano).forEach(([k, v]) => fd.append(k, v));

    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.status === 'ok')          { btn.textContent = '✅ Aggiunto!'; }
    else if (data.status === 'exists') { btn.textContent = 'ℹ️ Già presente'; }
    else                               { btn.textContent = '❌ Errore'; btn.disabled = false; }
}

document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') cerca(); });
</script>
</body>
</html>