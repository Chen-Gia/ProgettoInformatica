<div class="top-bar">
    <div class="user-section">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Esci
        </a>
    </div>
</div>