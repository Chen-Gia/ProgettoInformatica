<?php
require_once "config.php";

$user     = $_POST['username'] ?? '';
$email    = $_POST['email']    ?? '';
$password = $_POST['password'] ?? '';

if (empty($user) || empty($email) || empty($password)) {
    die("Dati mancanti");
}

$sql1 = 'SELECT * FROM utenti WHERE username = ? OR email = ?';
$preparata1 = $connessione->prepare($sql1);
$preparata1->execute([$user, $email]);

if ($preparata1->rowCount() == 0) {
    $livello = 1;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql2 = 'INSERT INTO utenti (username, email, password, livello) VALUES (?, ?, ?, ?)';
    $preparata2 = $connessione->prepare($sql2);
    $preparata2->execute([$user, $email, $hash, $livello]);
    header('Location: login.php');
    exit;

} else {
    $sql3 = 'SELECT * FROM utenti WHERE username = ?';
    $preparata3 = $connessione->prepare($sql3);
    $preparata3->execute([$user]);

    if ($preparata3->rowCount() == 0) {
        $_SESSION['esiste_email'] = 1;
    } else {
        $_SESSION['esiste_username'] = 1;
    }
    header('Location: iscrizione.php');
    exit;
}
?>