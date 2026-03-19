<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "catalogo_musicale";

$connessione = new mysqli($host, $user, $pass, $db);

if($connessione->connect_error){
    die("Errore connessione DB: " . $connessione->connect_error);
}
?>
