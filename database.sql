CREATE DATABASE catalogo_musicale;
USE catalogo_musicale;

CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    livello INT NOT NULL DEFAULT 1
);

CREATE TABLE artisti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL
);

CREATE TABLE album (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(150) NOT NULL,
    artista_id INT,
    anno YEAR,
    genere VARCHAR(50),
    FOREIGN KEY (artista_id) REFERENCES artisti(id) ON DELETE SET NULL
);

CREATE TABLE brani (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(150) NOT NULL,
    artista_id INT,
    album_id INT,
    isrc VARCHAR(15) UNIQUE,
    anno YEAR,
    genere VARCHAR(50),
    durata INT,
    FOREIGN KEY (artista_id) REFERENCES artisti(id) ON DELETE SET NULL,
    FOREIGN KEY (album_id) REFERENCES album(id) ON DELETE SET NULL
);

CREATE TABLE valutazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT,
    brano_id INT,
    album_id INT,
    voto INT CHECK (voto >= 1 AND voto <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (brano_id) REFERENCES brani(id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES album(id) ON DELETE CASCADE
);

CREATE TABLE preferiti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT,
    brano_id INT,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (brano_id) REFERENCES brani(id) ON DELETE CASCADE
);

CREATE TABLE playlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT,
    nome VARCHAR(100),
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
);

CREATE TABLE playlist_brani (
    playlist_id INT,
    brano_id INT,
    PRIMARY KEY (playlist_id, brano_id),
    FOREIGN KEY (playlist_id) REFERENCES playlist(id) ON DELETE CASCADE,
    FOREIGN KEY (brano_id) REFERENCES brani(id) ON DELETE CASCADE
);

CREATE TABLE commenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT NOT NULL,
    brano_id INT NOT NULL,
    contenuto TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (brano_id) REFERENCES brani(id) ON DELETE CASCADE
);