<?php
require_once "../config.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h3>Login</h3>
    <?php
    if(isset($_SESSION['errato'])) {
        unset($_SESSION['errato']);
        echo "<p style='color: red'>Credenziali errate</p>";
    }
    ?>
    <form method="POST" action="check_login.php">
        <label>Utente:</label>
        <input type="text" name="username" required><br>

        <label>Password:</label>
        <input type="password" name="password" required><br>

        <input type="submit" value="Accedi">
    </form>
</body>
</html>