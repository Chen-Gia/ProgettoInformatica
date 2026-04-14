<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscrizione</title>
</head>
<body>
    <h3>Login</h3>
    <?php
        session_start();
        if(isset($_SESSION['esiste_username'])) {
            unset($_SESSION['esiste_username']);
            echo "<p style='color: red'>Username già esistente</p>";
        }
        if(isset($_SESSION['esiste_email'])) {
            unset($_SESSION['esiste_email']);
            echo "<p style='color: red'>Email già usato</p>";
        }
    ?>
    <form method="POST" action="check_iscrizione.php">
        <label>Nome utente:</label>
        <input type="text" name="username" required><br>
        <label>Email:</label>
        <input type="email" name="email" required><br>

        <label>Password:</label>
        <input type="password" name="password" required><br>

        <input type="submit" value="Iscriviti">
    </form>
</body>
</html>
