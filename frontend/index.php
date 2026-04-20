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

// Esempi di query per ottenere dati (modifica secondo le tue tabelle)
$brani_recenti = $connessione->query("SELECT * FROM brani LIMIT 6");
$artisti = $connessione->query("SELECT * FROM artisti LIMIT 6");
$playlist = $connessione->query("SELECT * FROM playlist LIMIT 6");
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
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-music"></i>
                <span>Trackly</span>
            </div>

            <div class="nav-section">
                <h3>Menu</h3>
                <ul>
                    <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#"><i class="fas fa-search"></i> Scopri</a></li>
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
                <!-- HERO SECTION -->
                <div class="hero">
                    <h1>🎵 Benvenuto in Trackly</h1>
                    <p>Il tuo nuovo modo di scoprire musica straordinaria</p>
                    <button class="hero-btn">Inizia a Esplorare</button>
                </div>

                <!-- BRANI RECENTI -->
                <div class="section-title">
                    <i class="fas fa-star"></i> Brani Recenti
                </div>
                <div class="grid-container">
                    <?php
                    if ($brani_recenti && $brani_recenti->num_rows > 0) {
                        while ($brano = $brani_recenti->fetch_assoc()) {
                            echo "<div class='card'>";
                            echo "<div class='card-image'><i class='fas fa-music'></i></div>";
                            echo "<div class='card-title'>" . htmlspecialchars($brano['titolo'] ?? 'Senza titolo') . "</div>";
                            echo "<div class='card-subtitle'>" . htmlspecialchars($brano['artista'] ?? 'Artista sconosciuto') . "</div>";
                            echo "<button class='card-action'><i class='fas fa-play'></i> Riproduci</button>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='empty-state' style='grid-column: 1/-1;'>";
                        echo "<i class='fas fa-music'></i>";
                        echo "<p>Nessun brano disponibile. Aggiungi brani al catalogo!</p>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <!-- ARTISTI TOP -->
                <div class="section-title">
                    <i class="fas fa-microphone-alt"></i> Artisti Top
                </div>
                <div class="grid-container">
                    <?php
                    if ($artisti && $artisti->num_rows > 0) {
                        while ($artista = $artisti->fetch_assoc()) {
                            echo "<div class='card'>";
                            echo "<div class='card-image' style='background: linear-gradient(135deg, #FF6B6B, #FF8E72);'><i class='fas fa-user'></i></div>";
                            echo "<div class='card-title'>" . htmlspecialchars($artista['nome'] ?? 'Artista sconosciuto') . "</div>";
                            echo "<div class='card-subtitle'>Artista</div>";
                            echo "<button class='card-action'><i class='fas fa-play'></i> Ascolta</button>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='empty-state' style='grid-column: 1/-1;'>";
                        echo "<i class='fas fa-microphone-alt'></i>";
                        echo "<p>Nessun artista disponibile.</p>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <!-- PLAYLIST -->
                <div class="section-title">
                    <i class="fas fa-compact-disc"></i> Playlist Consigliate
                </div>
                <div class="grid-container">
                    <?php
                    if ($playlist && $playlist->num_rows > 0) {
                        while ($item = $playlist->fetch_assoc()) {
                            echo "<div class='card'>";
                            echo "<div class='card-image' style='background: linear-gradient(135deg, #4ECDC4, #44A08D);'><i class='fas fa-list'></i></div>";
                            echo "<div class='card-title'>" . htmlspecialchars($item['nome'] ?? 'Playlist') . "</div>";
                            echo "<div class='card-subtitle'>" . htmlspecialchars($item['descrizione'] ?? 'Playlist') . "</div>";
                            echo "<button class='card-action'><i class='fas fa-play'></i> Riproduci</button>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='empty-state' style='grid-column: 1/-1;'>";
                        echo "<i class='fas fa-compact-disc'></i>";
                        echo "<p>Nessuna playlist disponibile.</p>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
