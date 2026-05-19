<?php
session_start();

$host = "localhost";
$db = "catalogo_musicale";
$user = "root";
$password = "";
try {
    $connessione = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $connessione->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore nella gestione del database $db: " . $e->getMessage());
}
?>