<div class="sidebar">
            <div class="logo">
                <i class="fas fa-music"></i>
                <span>Trackly</span>
            </div>

            <div class="nav-section">
                <h3>Menu</h3>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="cerca.php"><i class="fas fa-search"></i> Cerca</a></li>
                    <li><a href="preferiti.php" class="active"><i class="fas fa-heart"></i> I Tuoi Mi Piace</a></li>
                </ul>
            </div>

            <div class="nav-section">
                <h3>Playlist</h3>
                <ul>
                    <?php foreach ($playlist_utente as $pl): ?>
                        <li><a href="#"><i class="fas fa-headphones"></i> <?php echo htmlspecialchars($pl['nome']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="crea_playlist.php"><i class="fas fa-plus-circle"></i> Crea Playlist</a></li>
                </ul>
            </div>
        </div>