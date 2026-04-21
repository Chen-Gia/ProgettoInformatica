<?php
require_once "config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

// ── GESTIONE AJAX ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // CERCA
    if ($_POST['action'] === 'search') {
        $url = 'https://itunes.apple.com/search?' . http_build_query([
            'term'    => $_POST['query'],
            'media'   => 'music',
            'limit'   => 10,
            'country' => 'it',
            'lang'    => 'it_it'
        ]);
        $r = file_get_contents($url);
        echo $r;
        exit;
    }

    // SALVA NEL DB
    if ($_POST['action'] === 'save') {
        $titolo  = $_POST['titolo'];
        $artista = $_POST['artista'];
        $anno    = $_POST['anno']   ?: null;
        $durata  = $_POST['durata'] ?: null;
        $genere  = $_POST['genere'] ?: null;

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

        // Brano: controlla duplicato per titolo + artista
        $s = $connessione->prepare("SELECT id FROM brani WHERE titolo = ? AND artista_id = ?");
        $s->bind_param("si", $titolo, $aid); $s->execute();
        if ($s->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'exists']); exit;
        }

        // Inserisci brano
        $s = $connessione->prepare("INSERT INTO brani (titolo, artista_id, anno, durata, genere) VALUES (?, ?, ?, ?, ?)");
        $s->bind_param("siiss", $titolo, $aid, $anno, $durata, $genere);
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
    <title>Cerca Musica - Trackly</title>
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
async function cerca() {
    const query = document.getElementById('q').value.trim();
    if (!query) return;

    document.getElementById('risultati').innerHTML = '<p>Ricerca in corso...</p>';

    try {
        const fd = new FormData();
        fd.append('action', 'search');
        fd.append('query', query);

        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        const brani = data.results ?? [];

        if (!brani.length) {
            document.getElementById('risultati').innerHTML = '<p>Nessun risultato.</p>';
            return;
        }

        document.getElementById('risultati').innerHTML = brani.map(b => `
            <div class="card">
                <div class="card-image">
                    <img src="${b.artworkUrl100 ?? ''}" style="width:100%; border-radius:8px;">
                </div>
                <div class="card-title">${b.trackName}</div>
                <div class="card-subtitle">${b.artistName}</div>
                <div class="card-subtitle" style="font-size:11px; opacity:.6">
                    ${b.collectionName} · ${b.releaseDate?.slice(0,4)}
                </div>
                <div class="card-subtitle" style="font-size:11px; opacity:.6">
                    ${b.primaryGenreName ?? ''}
                </div>
                <br>
                ${b.previewUrl ? `
                <audio id="audio_${b.trackId}" src="${b.previewUrl}"></audio>
                <button class="card-action" style="margin-bottom:6px" onclick="togglePreview(${b.trackId})">
                    <i class="fas fa-play" id="icon_${b.trackId}"></i> Anteprima
                </button>` : ''}
                <button class="card-action" onclick='salva(this, ${JSON.stringify({
                    titolo:  b.trackName,
                    artista: b.artistName,
                    anno:    b.releaseDate?.slice(0,4) ?? '',
                    durata:  Math.round((b.trackTimeMillis ?? 0) / 1000),
                    genere:  b.primaryGenreName ?? ''
                })})'>
                    <i class="fas fa-plus"></i> Aggiungi al DB
                </button>
            </div>
        `).join('');

    } catch (err) {
        document.getElementById('risultati').innerHTML = '<p style="color:red;">❌ Errore: ' + err.message + '</p>';
    }
}

// Anteprima audio 30 secondi
let currentAudio = null;
function togglePreview(trackId) {
    const audio = document.getElementById('audio_' + trackId);
    const icon  = document.getElementById('icon_'  + trackId);

    if (currentAudio && currentAudio !== audio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        const prevId   = currentAudio.id.replace('audio_', '');
        const prevIcon = document.getElementById('icon_' + prevId);
        if (prevIcon) prevIcon.className = 'fas fa-play';
    }

    if (audio.paused) {
        audio.play();
        icon.className = 'fas fa-pause';
        currentAudio   = audio;
        audio.onended  = () => { icon.className = 'fas fa-play'; };
    } else {
        audio.pause();
        audio.currentTime = 0;
        icon.className    = 'fas fa-play';
        currentAudio      = null;
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

        if (data.status === 'ok')          { btn.textContent = '✅ Aggiunto!'; }
        else if (data.status === 'exists') { btn.textContent = 'ℹ️ Già presente'; }
        else                               { btn.textContent = '❌ Errore'; btn.disabled = false; }
    } catch (err) {
        btn.textContent = '❌ Errore'; btn.disabled = false;
    }
}

document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') cerca(); });
</script>
</body>
</html>