<?php
require_once "../backend/config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

// Recupera l'ID della playlist da $_GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_playlist = (int) $_GET['id'];

// Recupera il nome della playlist (verificando che appartenga all'utente)
$stmt_playlist_info = $connessione->prepare("
    SELECT id_playlist, nome
    FROM playlist
    WHERE id_playlist = ? AND utente_username = ?
");
$stmt_playlist_info->execute([$id_playlist, $username]);
$playlist_info = $stmt_playlist_info->fetch(PDO::FETCH_ASSOC);

if (!$playlist_info) {
    header('Location: index.php');
    exit;
}

$nome_playlist = $playlist_info['nome'];

// Recupera tutte le playlist dell'utente (per la sidebar)
$stmt_playlist = $connessione->prepare("
    SELECT id_playlist, nome
    FROM playlist
    WHERE utente_username = ?
    ORDER BY id_playlist DESC
");
$stmt_playlist->execute([$username]);
$playlist_utente = $stmt_playlist->fetchAll(PDO::FETCH_ASSOC);

// Recupera i brani della playlist
$stmt_brani = $connessione->prepare("
    SELECT b.id_brano, b.titolo, b.artwork_url, b.preview_url, 
           b.anno, b.genere, b.durata, a.nome as artista
    FROM playlist_brani pb
    JOIN brani b ON pb.brano_id = b.id_brano
    JOIN artisti a ON b.artista_id = a.id_artista
    WHERE pb.playlist_id = ?
");
$stmt_brani->execute([$id_playlist]);
$brani_playlist = $stmt_brani->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nome_playlist); ?> - Trackly</title>
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

                <!-- Header della Playlist -->
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:30px; padding:20px; background:linear-gradient(135deg, rgba(29,185,84,0.1), rgba(29,185,84,0.05)); border-radius:15px; border-left:4px solid #1DB954;">
                    <div style="flex:1;">
                        <div class="section-title" style="margin:0; color:#1DB954;">
                            <i class="fas fa-list"></i> <?php echo htmlspecialchars($nome_playlist); ?>
                        </div>
                        <div style="color:#b3b3b3; margin-top:8px; font-size:14px;">
                            <?php echo count($brani_playlist); ?> brano<?php echo count($brani_playlist) !== 1 ? 'i' : ''; ?>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button id="playAllBtn" class="card-action" style="padding:12px 24px; background:linear-gradient(135deg, #1DB954, #1ed760); color:#000; font-weight:600; border:none;">
                            <i class="fas fa-play"></i> Riproduci Tutto
                        </button>
                        <button id="shuffleBtn" class="card-action" style="padding:12px 24px; background:rgba(29,185,84,0.2); color:#1DB954; border:1px solid #1DB954;">
                            <i class="fas fa-random"></i> Casuale
                        </button>
                    </div>
                </div>

                <!-- Griglia di Brani -->
                <div class="grid-container" id="brani-grid"></div>

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
        // Variabili globali per il player sequenziale
        const playlistId = <?php echo $id_playlist; ?>;
        let braniPlaylist = <?php echo json_encode($brani_playlist); ?>;
        let queue = [];
        let currentIndex = 0;
        const grid = document.getElementById('brani-grid');

        // Renderizza la griglia di brani
        if (braniPlaylist.length > 0) {
            grid.innerHTML = braniPlaylist.map(b =>
                getCardTemplate(b, 'playlist')
            ).join('');
        } else {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1/-1;">
                    <i class="fas fa-music"></i>
                    <p>Questa playlist non contiene brani.</p>
                    <a href="cerca.php" class="hero-btn" style="text-decoration:none; display:inline-block; padding:12px 30px; background:#1DB954; color:white; border-radius:8px; font-weight:500;">
                        <i class="fas fa-plus"></i> Aggiungi Brani
                    </a>
                </div>`;
        }

        // Funzione per avviare la riproduzione sequenziale
        function playAll() {
            queue = braniPlaylist.map(b => b.id_brano);
            currentIndex = 0;
            if (queue.length > 0) {
                playNextInQueue();
            }
        }

        // Funzione per avviare la riproduzione casuale
        function shuffle() {
            queue = [...braniPlaylist].sort(() => Math.random() - 0.5).map(b => b.id_brano);
            currentIndex = 0;
            if (queue.length > 0) {
                playNextInQueue();
            }
        }

        // Funzione per riprodurre il brano successivo in coda
        function playNextInQueue() {
            if (currentIndex < queue.length) {
                const branoId = queue[currentIndex];
                togglePreview(branoId);
                
                // Intercetta l'evento onended per avanzare al brano successivo
                const audio = document.getElementById('audio_' + branoId);
                if (audio) {
                    audio.onended = () => {
                        currentIndex++;
                        playNextInQueue();
                    };
                }
            }
        }

        // Event listeners per i pulsanti
        document.getElementById('playAllBtn').addEventListener('click', playAll);
        document.getElementById('shuffleBtn').addEventListener('click', shuffle);
    </script>
</body>
</html>
