<?php
require_once "config.php";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trackly - Login</title>
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
            
            <h1 class="login-title">Accedi</h1>
            
            <?php
            if(isset($_SESSION['errato'])) {
                unset($_SESSION['errato']);
                echo "<div class='error-message'><i class='fas fa-exclamation-circle'></i> Credenziali errate. Riprova!</div>";
            }
            ?>
            
            <form method="POST" action="check_login.php" class="login-form">
                <div class="form-group">
                    <label for="username">Utente</label>
                    <input type="text" id="username" name="username" placeholder="Inserisci il tuo username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Inserisci la tua password" required>
                </div>

                <button type="submit" class="login-btn">Accedi</button>
            </form>
        </div>
    </div>
</body>
</html>