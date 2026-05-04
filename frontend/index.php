<?php
require_once "config.php";

// Verificare se l'utente è loggato
if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

// Query per ottenere dati dal database (adatta in base alle tue tabelle)
$username = $_SESSION['username'];
$livello = $_SESSION['livello'];

// Brani preferiti dell'utente (ultimi 12 = 2 righe)
$stmt_preferiti = $connessione->prepare("
    SELECT b.id, b.titolo, b.anno, a.nome as artista
    FROM preferiti p
    JOIN brani b ON p.brano_id = b.id
    JOIN artisti a ON b.artista_id = a.id
    WHERE p.utente_username = ?
    ORDER BY p.id DESC
    LIMIT 12
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
    <title>Trackly - Music Streaming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <!-- SIDEBAR -->
        <?php include 'sidebar.php' ?>
        <!-- MAIN CONTENT -->
        <div class="main-content">
            <!-- TOPBAR -->
            <?php include 'topbar.php' ?>
            <!-- CONTENT AREA -->
            <div class="content-area">
                <!-- HERO SECTION -->
                <div class="hero">
                    <h1>🎵 Benvenuto in Trackly</h1>
                    <p>Il tuo nuovo modo di scoprire musica straordinaria</p>
                    <a href="cerca.php" class="hero-btn" style="text-decoration:none; display:inline-block;">Inizia a
                        Esplorare</a>
                </div>

                <!-- ULTIMI BRANI PREFERITI -->
                <div class="section-title">
                    <i class="fas fa-heart"></i> Ultimi Brani Preferiti
                </div>
                <div class="grid-container">
                    <?php
                    if (count($brani_preferiti) > 0) {
                        foreach ($brani_preferiti as $brano) {
                            echo "<div class='card'>";
                            echo "<div class='card-image'><i class='fas fa-music'></i></div>";
                            echo "<div class='card-title'>" . htmlspecialchars($brano['titolo'] ?? 'Senza titolo') . "</div>";
                            echo "<div class='card-subtitle'>" . htmlspecialchars($brano['artista'] ?? 'Artista sconosciuto') . "</div>";
                            echo "<button class='card-action'><i class='fas fa-play'></i> Riproduci</button>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='empty-state' style='grid-column: 1/-1;'>";
                        echo "<i class='fas fa-heart'></i>";
                        echo "<p>Non hai ancora brani nei preferiti. <a href='cerca.php'>Aggiungi brani</a>!</p>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <!-- PLAYLIST UTENTE -->
                <div class="section-title">
                    <i class="fas fa-compact-disc"></i> Le Tue Playlist
                </div>
                <div class="grid-container">
                    <?php
                    if (count($playlist_utente) > 0) {
                        foreach ($playlist_utente as $item) {
                            echo "<div class='card'>";
                            echo "<div class='card-image' style='background: linear-gradient(135deg, #4ECDC4, #44A08D);'><i class='fas fa-list'></i></div>";
                            echo "<div class='card-title'>" . htmlspecialchars($item['nome']) . "</div>";
                            echo "<div class='card-subtitle'>Playlist</div>";
                            echo "<button class='card-action'><i class='fas fa-play'></i> Riproduci</button>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='empty-state' style='grid-column: 1/-1; text-align: center;'>";
                        echo "<i class='fas fa-compact-disc' style='font-size: 48px; margin-bottom: 10px; display: block;'></i>";
                        echo "<p>Non hai ancora creato playlist.</p>";
                        echo "<a href='crea_playlist.php' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #1DB954; color: white; border-radius: 8px; text-decoration: none;'>📋 Crea Ora</a>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>