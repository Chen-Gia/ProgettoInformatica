<?php
require_once "../backend/config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello  = $_SESSION['livello'];

$stmt_preferiti = $connessione->prepare("
    SELECT b.id_brano, b.titolo, b.durata, b.anno, b.genere, b.preview_url, b.artwork_url, a.nome as artista
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
    <script><?php include 'card.php'; ?></script>
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
                getCardTemplate({ ...b, durata: b.durata ? parseInt(b.durata) + 's' : 'N/A' }, 'preferiti')
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
    </script>
</body>
</html>