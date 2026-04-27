<?php
require_once "config.php";

// Verificare se l'utente è loggato
if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello = $_SESSION['livello'];

// Gestione AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // RIMUOVI DAI PREFERITI
    if ($_POST['action'] === 'remove_favorite') {
        $branoId = (int)$_POST['brano_id'];
        try {
            $s = $connessione->prepare("DELETE FROM preferiti WHERE utente_username = ? AND brano_id = ?");
            $s->execute([$username, $branoId]);
            echo json_encode(['status' => 'ok']); exit;
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

// Query per ottenere tutti i brani preferiti
$stmt_preferiti = $connessione->prepare("
    SELECT b.id, b.titolo, b.durata, b.anno, b.genere, a.nome as artista
    FROM preferiti p
    JOIN brani b ON p.brano_id = b.id
    JOIN artisti a ON b.artista_id = a.id
    WHERE p.utente_username = ?
    ORDER BY p.id DESC
");
$stmt_preferiti->execute([$username]);
$brani_preferiti = $stmt_preferiti->fetchAll(PDO::FETCH_ASSOC);

// Playlist dell'utente
$stmt_playlist = $connessione->prepare("
    SELECT id, nome
    FROM playlist
    WHERE utente_username = ?
    ORDER BY id DESC
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
                    <li><a href="cerca.php"><i class="fas fa-search"></i> Cerca</a></li>
                    <li><a href="preferiti.php" class="active"><i class="fas fa-heart"></i> I Tuoi Mi Piace</a></li>
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
                    <i class="fas fa-heart"></i> I Tuoi Brani Preferiti
                </div>

                <div class="grid-container" id="preferiti-grid">
                    <?php
                    if (count($brani_preferiti) > 0) {
                        foreach ($brani_preferiti as $brano) {
                            $durata = $brano['durata'] ? intval($brano['durata']) . "s" : "N/A";
                            echo "<div class='card' id='brano_" . $brano['id'] . "'>";
                            echo "<div class='card-image'><i class='fas fa-music'></i></div>";
                            echo "<div class='card-title'>" . htmlspecialchars($brano['titolo'] ?? 'Senza titolo') . "</div>";
                            echo "<div class='card-subtitle'>" . htmlspecialchars($brano['artista'] ?? 'Artista sconosciuto') . "</div>";
                            echo "<div class='card-subtitle' style='font-size:11px; opacity:.6'>";
                            echo ($brano['anno'] ?? '') . " · " . ($brano['genere'] ?? '') . " · " . $durata;
                            echo "</div>";
                            echo "<br>";
                            echo "<button class='card-action' style='margin-bottom:6px' onclick='mostraPlaylistDialog(" . $brano['id'] . ")'>";
                            echo "<i class='fas fa-list'></i> Aggiungi a Playlist";
                            echo "</button>";
                            echo "<button class='card-action' style='background:#e74c3c; margin-bottom:6px;' onclick='rimuoviPreferito(this, " . $brano['id'] . ")'>";
                            echo "<i class='fas fa-trash'></i> Rimuovi";
                            echo "</button>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='empty-state' style='grid-column: 1/-1;'>";
                        echo "<i class='fas fa-heart' style='font-size: 64px; margin-bottom: 20px; opacity: 0.5;'></i>";
                        echo "<p style='font-size: 18px; margin-bottom: 20px;'>Non hai ancora brani nei preferiti.</p>";
                        echo "<a href='cerca.php' class='hero-btn' style='text-decoration:none; display:inline-block; padding:12px 30px; background:#1DB954; color:white; border-radius:8px; font-weight:500;'>";
                        echo "<i class='fas fa-search'></i> Inizia a Cercare";
                        echo "</a>";
                        echo "</div>";
                    }
                    ?>
                </div>

            </div>
        </div>
    </div>

<script>
async function rimuoviPreferito(btn, branoId) {
    if (!confirm('Sei sicuro di voler rimuovere questo brano dai preferiti?')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rimozione...';
    
    const fd = new FormData();
    fd.append('action', 'remove_favorite');
    fd.append('brano_id', branoId);

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            const card = document.getElementById('brano_' + branoId);
            card.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => {
                card.remove();
                // Se non ci sono più brani, mostra il messaggio vuoto
                const grid = document.getElementById('preferiti-grid');
                if (grid.children.length === 0) {
                    grid.innerHTML = `
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <i class="fas fa-heart" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                            <p style="font-size: 18px; margin-bottom: 20px;">Non hai ancora brani nei preferiti.</p>
                            <a href="cerca.php" class="hero-btn" style="text-decoration:none; display:inline-block; padding:12px 30px; background:#1DB954; color:white; border-radius:8px; font-weight:500;">
                                <i class="fas fa-search"></i> Inizia a Cercare
                            </a>
                        </div>
                    `;
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
</script>

<style>
@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #b3b3b3;
}

.empty-state i {
    display: block;
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state p {
    font-size: 18px;
    margin-bottom: 20px;
}
</style>
</body>
</html>
