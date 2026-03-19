<?php
require_once "../config.php";

$user = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($user) || empty($password)) {
    die("Dati mancanti");
}

$sql = 'SELECT password, username, livello FROM utenti WHERE username = ? AND password = ?';
$preparata = $connessione->prepare($sql);
if (!$preparata) {
    die("Errore prepare: " . $connessione->error);
}
$preparata->execute([$user, $password]);
$result = $preparata->get_result();  
$credenziali = $result->fetch_object();  

if ($credenziali) {
    $_SESSION['logged'] = 1;
    $_SESSION['username'] = $credenziali->username;
    $_SESSION['livello'] = $credenziali->livello;
    header('Location: ../index.php');
    exit;
} else {
    $_SESSION['errato'] = 1;
    header('Location: login.php');
}
?>
