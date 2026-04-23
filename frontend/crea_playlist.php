<?php
require_once "config.php";

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$livello = $_SESSION['livello'];
$message = '';
$message_type = '';

// Carica le playlist dell'utente per la sidebar
$stmt_playlist = $connessione->prepare("
    SELECT id, nome
    FROM playlist
    WHERE utente_username = ?
    ORDER BY id DESC
");
$stmt_playlist->execute([$username]);
$playlist_utente = $stmt_playlist->fetchAll(PDO::FETCH_ASSOC);

// Gestisci il submit del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    
    if (empty($nome)) {
        $message = 'Il nome della playlist non può essere vuoto.';
        $message_type = 'error';
    } else {
        try {
            $stmt = $connessione->prepare("INSERT INTO playlist (utente_username, nome) VALUES (?, ?)");
            $stmt->execute([$username, $nome]);
            $message = '✅ Playlist creata con successo!';
            $message_type = 'success';
            // Reindirizza a index dopo 2 secondi
            header('Refresh: 2; url=index.php');
        } catch (PDOException $e) {
            $message = '❌ Errore nella creazione della playlist.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Playlist - Trackly</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            background-color: #ffffff;
            color: #000000;
            font-weight: 500;
        }
        .form-group input::placeholder {
            color: #999999;
            font-weight: 400;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1DB954;
            background-color: #f9f9f9;
            box-shadow: 0 0 0 3px rgba(29, 185, 84, 0.1);
        }
        .form-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .form-buttons button, .form-buttons a {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-create {
            background: #1DB954;
            color: white;
        }
        .btn-create:hover {
            background: #1ed760;
            transform: scale(1.05);
        }
        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }
        .btn-cancel:hover {
            background: #e0e0e0;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            color: #333;
        }
        .form-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 15px;
            color: #1DB954;
        }
    </style>
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
                    <li><a href="#"><i class="fas fa-heart"></i> I Tuoi Mi Piace</a></li>
                    <li><a href="#"><i class="fas fa-list"></i> Coda</a></li>
                </ul>
            </div>
            <div class="nav-section">
                <h3>Playlist</h3>
                <ul>
                    <?php foreach ($playlist_utente as $pl): ?>
                        <li><a href="#"><i class="fas fa-headphones"></i> <?php echo htmlspecialchars($pl['nome']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="crea_playlist.php" class="active"><i class="fas fa-plus-circle"></i> Crea Playlist</a></li>
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
                <div class="form-container">
                    <div class="form-icon">
                        <i class="fas fa-compact-disc"></i>
                    </div>
                    <h1 class="form-title">Crea Playlist</h1>

                    <?php if ($message): ?>
                        <div class="message <?php echo $message_type; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="nome">Nome Playlist</label>
                            <input 
                                type="text" 
                                id="nome" 
                                name="nome" 
                                placeholder="Es. Le mie canzoni preferite"
                                autocomplete="off"
                                required
                            >
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="btn-create">
                                <i class="fas fa-check"></i> Crea Playlist
                            </button>
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Annulla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
