<?php
require_once "config.php";

// Se arriva qui senza dati POST, significa che è un accesso diretto (redirect da index.php)
// In questo caso ignora e permetti a index.php di proseguire
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$user = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($user) || empty($password)) {
    $_SESSION['errato'] = 1;
    header('Location: login.php');
    exit;
}

$sql = 'SELECT password, username, livello FROM utenti WHERE username = ? AND password = ?';
$preparata = $connessione->prepare($sql);
$preparata->execute([$user, $password]);
$credenziali = $preparata->fetch(PDO::FETCH_OBJ);

if ($credenziali) {
    $_SESSION['logged'] = 1;
    $_SESSION['username'] = $credenziali->username;
    $_SESSION['livello'] = $credenziali->livello;
    header('Location: index.php');
    exit;
} else {
    $_SESSION['errato'] = 1;
    header('Location: login.php');
    exit;
}
?>