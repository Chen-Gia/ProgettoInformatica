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
            $branoId = (int)$_POST['brano_id'] ?? 0;
            $s = $connessione->prepare("SELECT id, nome FROM playlist WHERE utente_username = ? ORDER BY nome");
            $s->execute([$username]);
            $playlists = $s->fetchAll(PDO::FETCH_ASSOC);
            
            // Per ogni playlist, controlla se il brano è già presente
            foreach ($playlists as &$pl) {
                $checkStmt = $connessione->prepare("SELECT 1 FROM playlist_brani WHERE playlist_id = ? AND brano_id = ?");
                $checkStmt->execute([$pl['id'], $branoId]);
                $pl['has_brano'] = $checkStmt->fetchColumn() !== false;
            }
            
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
        <?php include 'sidebar.php' ?>
        <!-- MAIN CONTENT -->
        <div class="main-content">
            <!-- TOP BAR -->
            <?php include 'topbar.php' ?>

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
        fd.append('brano_id', branoId);

        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.status !== 'ok' || !data.playlists.length) {
            alert('Non hai playlist. Creane una dalla home!');
            return;
        }

        // Crea le opzioni del select, disabilitando le playlist che contengono già il brano
        let playlistHtml = data.playlists.map(p => {
            if (p.has_brano) {
                return `<option value="${p.id}" disabled>✅ ${p.nome} (già aggiunto)</option>`;
            } else {
                return `<option value="${p.id}">${p.nome}</option>`;
            }
        }).join('');
        
        const dialog = document.createElement('div');
        dialog.id = 'playlist-dialog-overlay';
        dialog.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:9999;';
        dialog.innerHTML = `
            <div style="background:linear-gradient(135deg, rgba(40,40,40,0.95), rgba(30,30,30,0.95)); padding:30px; border-radius:15px; box-shadow:0 8px 32px rgba(29,185,84,0.3); max-width:450px; width:90%; border:1px solid rgba(29,185,84,0.3);">
                <h3 style="margin-top:0; margin-bottom:20px; color:#1DB954; font-size:20px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-list"></i> Aggiungi a Playlist
                </h3>
                <select id="playlist_select" style="padding:12px; border-radius:8px; border:1px solid rgba(29,185,84,0.5); width:100%; cursor:pointer; font-size:14px; margin-bottom:20px; background:rgba(0,0,0,0.3); color:#fff; transition:all 0.3s ease;">
                    <option value="" style="background:#222; color:#fff;">-- Seleziona una playlist --</option>
                    ${playlistHtml}
                </select>
                <style>
                    #playlist_select option:disabled {
                        background: rgba(100,100,100,0.5);
                        color: #888;
                    }
                    #playlist_select option:enabled {
                        background: #1a1a1a;
                        color: #fff;
                    }
                    #playlist_select:hover {
                        border-color: #1ed760;
                        box-shadow: 0 0 15px rgba(29,185,84,0.2);
                    }
                    #playlist_select:focus {
                        outline: none;
                        border-color: #1DB954;
                        box-shadow: 0 0 20px rgba(29,185,84,0.4);
                    }
                </style>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="chiudiDialog(); return false;" style="padding:10px 20px; border-radius:8px; border:1px solid rgba(255,255,255,0.3); background:rgba(0,0,0,0.3); color:#fff; cursor:pointer; transition:all 0.3s ease;">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" onclick="aggiungiPlaylist(${branoId}); return false;" style="padding:10px 20px; border-radius:8px; border:none; background:linear-gradient(135deg, #1DB954, #1ed760); color:#000; cursor:pointer; font-weight:600; transition:all 0.3s ease;">
                        <i class="fas fa-plus"></i> Aggiungi
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(dialog);
        
        // Chiudi il dialog se clicchi fuori
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                e.preventDefault();
                chiudiDialog();
            }
        });
        
        // Aggiungi anche un handler per il tasto Esc
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                chiudiDialog();
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
    } catch (err) {
        alert('❌ Errore nel caricamento delle playlist');
    }
}

function chiudiDialog() {
    try {
        const dialog = document.getElementById('playlist-dialog-overlay');
        if (dialog) {
            dialog.style.display = 'none';
            setTimeout(() => {
                const d = document.getElementById('playlist-dialog-overlay');
                if (d && d.parentNode) {
                    d.parentNode.removeChild(d);
                }
            }, 100);
        }
    } catch (err) {
        console.error('Errore nella chiusura del dialog:', err);
    }
}

async function aggiungiPlaylist(branoId) {
    try {
        const select = document.getElementById('playlist_select');
        if (!select) {
            console.error('Select element not found');
            return;
        }
        
        const playlistId = select.value;
        
        if (!playlistId) {
            alert('Seleziona una playlist');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'add_to_playlist');
        fd.append('brano_id', branoId);
        fd.append('playlist_id', playlistId);

        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            alert('✅ Brano aggiunto alla playlist!');
            chiudiDialog();
        } else if (data.status === 'exists') {
            alert('ℹ️ Brano già presente in questa playlist');
            chiudiDialog();
        } else {
            alert('❌ Errore nell\'aggiunta: ' + (data.message || 'Errore sconosciuto'));
        }
    } catch (err) {
        console.error('Errore:', err);
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