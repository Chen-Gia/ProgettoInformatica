<?php
require_once "config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

// Carica le playlist dell'utente per la sidebar
$stmt_playlist = $connessione->prepare("
    SELECT id, nome
    FROM playlist
    WHERE utente_username = ?
    ORDER BY id DESC
");
$stmt_playlist->execute([$username]);
$playlist_utente = $stmt_playlist->fetchAll(PDO::FETCH_ASSOC);

// ── GESTIONE AJAX ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // CERCA
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
            if ($r === false) {
                echo json_encode(['results' => []]);
            } else {
                echo $r;
            }
        } catch (Exception $e) {
            echo json_encode(['results' => []]);
        }
        exit;
    }

    // CERCA NEL DATABASE
    if ($_POST['action'] === 'search_db') {
        $query = '%' . $_POST['query'] . '%';
        try {
            $s = $connessione->prepare("
                SELECT b.id, b.titolo, b.durata, b.anno, b.genere, 
                       a.nome as artista
                FROM brani b
                JOIN artisti a ON b.artista_id = a.id
                WHERE b.titolo LIKE ? OR a.nome LIKE ?
                ORDER BY b.titolo
                LIMIT 50
            ");
            $s->execute([$query, $query]);
            $brani = $s->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatta i risultati simile a iTunes per il frontend
            $results = array_map(function($b) {
                return [
                    'trackId' => $b['id'],
                    'trackName' => $b['titolo'],
                    'artistName' => $b['artista'],
                    'releaseDate' => $b['anno'] . '-01-01',
                    'primaryGenreName' => $b['genere'],
                    'trackTimeMillis' => ($b['durata'] ?? 0) * 1000,
                    'artworkUrl100' => '',
                    'isFromDB' => true
                ];
            }, $brani);
            
            echo json_encode(['results' => $results]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['results' => []]); 
            exit;
        }
    }

    // SALVA NEL DB
    if ($_POST['action'] === 'save') {
        $titolo  = $_POST['titolo'];
        $artista = $_POST['artista'];
        $anno    = !empty($_POST['anno']) ? (int)$_POST['anno'] : null;
        $durata  = !empty($_POST['durata']) ? (int)$_POST['durata'] : null;
        $genere  = !empty($_POST['genere']) ? $_POST['genere'] : null;

        try {
            // Artista: trova o crea
            $s = $connessione->prepare("SELECT id FROM artisti WHERE nome = ?");
            $s->execute([$artista]);
            $result = $s->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $aid = $result['id'];
            } else {
                $s = $connessione->prepare("INSERT INTO artisti (nome) VALUES (?)");
                $s->execute([$artista]);
                $aid = $connessione->lastInsertId();
            }

            // Brano: controlla duplicato per titolo + artista
            $s = $connessione->prepare("SELECT id FROM brani WHERE titolo = ? AND artista_id = ?");
            $s->execute([$titolo, $aid]);
            $existingBrano = $s->fetch(PDO::FETCH_ASSOC);
            if ($existingBrano) {
                echo json_encode(['status' => 'exists', 'brano_id' => $existingBrano['id']]); exit;
            }

            // Inserisci brano
            $s = $connessione->prepare("INSERT INTO brani (titolo, artista_id, anno, durata, genere) VALUES (?, ?, ?, ?, ?)");
            $s->execute([$titolo, $aid, $anno, $durata, $genere]);
            $branoId = $connessione->lastInsertId();
            echo json_encode(['status' => 'ok', 'brano_id' => $branoId]); exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
        }
    }

    // CARICA PLAYLIST
    if ($_POST['action'] === 'get_playlists') {
        try {
            $s = $connessione->prepare("SELECT id, nome FROM playlist WHERE utente_username = ? ORDER BY nome");
            $s->execute([$username]);
            $playlists = $s->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'playlists' => $playlists]); exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
        }
    }

    // AGGIUNGI AI PREFERITI
    if ($_POST['action'] === 'add_favorite') {
        $branoId = (int)$_POST['brano_id'];
        try {
            $s = $connessione->prepare("INSERT INTO preferiti (utente_username, brano_id) VALUES (?, ?)");
            $s->execute([$username, $branoId]);
            echo json_encode(['status' => 'ok']); exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo json_encode(['status' => 'exists']); exit;
            }
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
        }
    }

    // AGGIUNGI A PLAYLIST
    if ($_POST['action'] === 'add_to_playlist') {
        $branoId = (int)$_POST['brano_id'];
        $playlistId = (int)$_POST['playlist_id'];
        try {
            $s = $connessione->prepare("INSERT INTO playlist_brani (playlist_id, brano_id) VALUES (?, ?)");
            $s->execute([$playlistId, $branoId]);
            echo json_encode(['status' => 'ok']); exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo json_encode(['status' => 'exists']); exit;
            }
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
        }
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
                    <li><a href="preferiti.php"><i class="fas fa-heart"></i> I Tuoi Mi Piace</a></li>
                    <li><a href="#"><i class="fas fa-list"></i> Coda</a></li>
                </ul>
            </div>
            <div class="nav-section">
                <h3>Playlist</h3>
                <ul>
                    <?php foreach ($playlist_utente as $pl): ?>
                        <li><a href="#"><i class="fas fa-headphones"></i> <?php echo htmlspecialchars($pl['nome']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="crea_playlist.php"><i class="fas fa-plus-circle"></i> Crea Playlist</a></li>
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
// Variabile globale con il livello utente (passato dal PHP)
const userLevel = <?php echo $livello; ?>;

async function cerca() {
    const query = document.getElementById('q').value.trim();
    if (!query) return;

    document.getElementById('risultati').innerHTML = '<p>Ricerca in corso...</p>';

    try {
        const fd = new FormData();
        
        // Se livello 0: cerca su iTunes, se livello 1: cerca nel DB
        if (userLevel === 0) {
            fd.append('action', 'search');
        } else {
            fd.append('action', 'search_db');
        }
        fd.append('query', query);

        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        const brani = data.results ?? [];

        if (!brani.length) {
            document.getElementById('risultati').innerHTML = '<p>Nessun risultato.</p>';
            return;
        }

        // Renderizza i risultati
        document.getElementById('risultati').innerHTML = brani.map(b => {
            const isFromDB = b.isFromDB === true;
            
            // Per livello 0 (iTunes): mostra il pulsante "Aggiungi al DB"
            if (userLevel === 0) {
                return `
                    <div class="card">
                        <div class="card-image">
                            <img src="${b.artworkUrl100 ?? ''}" style="width:100%; border-radius:8px;">
                        </div>
                        <div class="card-title">${b.trackName}</div>
                        <div class="card-subtitle">${b.artistName}</div>
                        <div class="card-subtitle" style="font-size:11px; opacity:.6">
                            ${b.collectionName ?? ''} · ${b.releaseDate?.slice(0,4) ?? ''}
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
                        <div class="card-actions" data-track-id="${b.trackId}"></div>
                    </div>
                `;
            } 
            // Per livello 1 (DB): mostra le card con pulsanti di azione
            else {
                return `
                    <div class="card">
                        <div class="card-image">
                            <img src="${b.artworkUrl100 ?? ''}" style="width:100%; border-radius:8px; background:#f0f0f0;">
                        </div>
                        <div class="card-title">${b.trackName}</div>
                        <div class="card-subtitle">${b.artistName}</div>
                        <div class="card-subtitle" style="font-size:11px; opacity:.6">
                            ${b.releaseDate?.slice(0,4) ?? ''} · ${b.primaryGenreName ?? ''}
                        </div>
                        <br>
                        <button class="card-action" onclick="aggiungiPreferito(this, ${b.trackId})">
                            <i class="fas fa-heart"></i> Aggiungi ai Preferiti
                        </button>
                        <button class="card-action" style="margin-top:6px" onclick="mostraPlaylistDialog(${b.trackId})">
                            <i class="fas fa-list"></i> Aggiungi a Playlist
                        </button>
                        <div class="card-actions" data-track-id="${b.trackId}"></div>
                    </div>
                `;
            }
        }).join('');

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

        if (data.status === 'ok' || data.status === 'exists') {
            const branoId = data.brano_id;
            const card = btn.closest('.card');
            const actionsDiv = card.querySelector('[data-track-id]');
            
            btn.textContent = data.status === 'ok' ? '✅ Aggiunto!' : 'ℹ️ Già presente';
            
            // Mostra i pulsanti per preferiti e playlist
            await mostraAzioni(actionsDiv, branoId);
        } else {
            btn.textContent = '❌ Errore'; 
            btn.disabled = false;
        }
    } catch (err) {
        btn.textContent = '❌ Errore'; 
        btn.disabled = false;
    }
}

async function mostraAzioni(container, branoId) {
    try {
        let html = `
            <button class="card-action" style="margin-top:8px; background:#e74c3c; padding:10px; border:none; border-radius:8px; color:white; cursor:pointer; width:100%; font-weight:500; transition:all 0.3s ease;" onclick="aggiungiPreferito(this, ${branoId})">
                <i class="fas fa-heart"></i> Aggiungi ai Preferiti
            </button>
            <button class="card-action" style="margin-top:6px;" onclick="mostraPlaylistDialog(${branoId})">
                <i class="fas fa-list"></i> Aggiungi a Playlist
            </button>
        `;

        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = '<p style="color:red; font-size:12px;">Errore nel caricamento</p>';
    }
}

async function aggiungiPreferito(btn, branoId) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'add_favorite');
    fd.append('brano_id', branoId);

    try {
        const res = await fetch('', { method: 'POST', body: fd });
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
    } catch (err) {
        btn.textContent = '❌ Errore';
        btn.disabled = false;
    }
}

async function mostraPlaylistDialog(branoId) {
    try {
        const fd = new FormData();
        fd.append('action', 'get_playlists');

        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.status !== 'ok' || !data.playlists.length) {
            alert('Non hai playlist. Creane una dalla home!');
            return;
        }

        let playlistHtml = data.playlists.map(p => `<option value="${p.id}">${p.nome}</option>`).join('');
        
        const dialog = document.createElement('div');
        dialog.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999;';
        dialog.innerHTML = `
            <div style="background:white; padding:30px; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1); max-width:400px; width:90%;">
                <h3 style="margin-top:0; color:#333;">Seleziona Playlist</h3>
                <select id="playlist_select" style="padding:10px; border-radius:4px; border:1px solid #ddd; width:100%; cursor:pointer; font-size:14px; margin-bottom:20px;">
                    <option value="">-- Seleziona una playlist --</option>
                    ${playlistHtml}
                </select>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button onclick="this.closest('div').parentElement.remove()" style="padding:10px 20px; border-radius:4px; border:1px solid #ddd; background:#f0f0f0; cursor:pointer;">Annulla</button>
                    <button onclick="aggiungiPlaylist(${branoId})" style="padding:10px 20px; border-radius:4px; border:none; background:#1db954; color:white; cursor:pointer; font-weight:500;">Aggiungi</button>
                </div>
            </div>
        `;
        document.body.appendChild(dialog);
    } catch (err) {
        alert('Errore nel caricamento delle playlist');
    }
}

async function aggiungiPlaylist(branoId) {
    const select = document.getElementById('playlist_select');
    const playlistId = select.value;
    
    if (!playlistId) {
        alert('Seleziona una playlist');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'add_to_playlist');
    fd.append('brano_id', branoId);
    fd.append('playlist_id', playlistId);

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            alert('✅ Brano aggiunto alla playlist!');
            document.querySelector('div[style*="position:fixed"]').remove();
        } else if (data.status === 'exists') {
            alert('ℹ️ Brano già presente in questa playlist');
        } else {
            alert('❌ Errore nell\'aggiunta');
        }
    } catch (err) {
        alert('❌ Errore: ' + err.message);
    }
}

document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') cerca(); });
</script>
</body>
</html>