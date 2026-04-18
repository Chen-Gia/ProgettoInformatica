<?php
session_start();

// Verificare se l'utente è loggato
if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="fas fa-music"></i>
                <span>Trackly</span>
            </div>

            <h1 class="login-title">Crea un account</h1>

            <?php
            if (isset($_SESSION['esiste_username'])) {
                unset($_SESSION['esiste_username']);
                echo "<div class='error-message'>Username già esistente.</div>";
            }
            if (isset($_SESSION['esiste_email'])) {
                unset($_SESSION['esiste_email']);
                echo "<div class='error-message'>Email già utilizzata.</div>";
            }
            ?>

            <form method="POST" action="check_iscrizione.php" class="login-form">
                <div class="form-group">
                    <label for="username">Nome utente</label>
                    <input type="text" id="username" name="username" placeholder="Scegli un username" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Inserisci la tua email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Crea una password" required>
                </div>

                <button type="submit" class="login-btn">Registrati</button>
            </form>

            <p class="register-link">Hai già un account? <a href="index.php">Accedi</a></p>
        </div>
    </div>
</body>
</html>