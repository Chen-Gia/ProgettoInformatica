<?php
require_once "../config.php";

$user = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($user) || empty($email) || empty($password)) {
    die("Dati mancanti");
}

$sql1 = 'SELECT * FROM utenti WHERE username = ? OR email = ?';
$preparata1 = $connessione->prepare($sql1);
if (!$preparata1) {
    die("Errore prepare: " . $connessione->error);
}
$preparata1->execute([$user, $email]);
$result = $preparata1->get_result();  
if($result->num_rows == 0) {
    $livello = 1;
    $sql2 = 'INSERT INTO utenti (username, email, password, livello) VALUES (?,?,?,?)';
    $preparata2 = $connessione->prepare($sql2);
    if (!$preparata2) {
        die("Errore prepare: " . $connessione->error);
    }
    $preparata2->bind_param("sssi", $user, $email, $password, $livello);
    $preparata2->execute();
    header('Location: login.php');
    exit;
} else {
    $sql3 = 'SELECT * FROM utenti WHERE username = ?';
    $preparata3 = $connessione->prepare($sql3);
    if (!$preparata3) {
        die("Errore prepare: " . $connessione->error);
    }
    $preparata3->bind_param("s", $user);
    $preparata3->execute();
    $result2 = $preparata3->get_result();  
    if($result2->num_rows == 0) {
        $_SESSION['esiste_email'] = 1;
        header('Location: iscrizione.php');
        exit;
    } else {
        $_SESSION['esiste_username'] = 1;
        header('Location: iscrizione.php');
        exit;
    }
}
?>
