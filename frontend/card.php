let currentAudio = null;

function togglePreview(trackId) {
    const audio = document.getElementById('audio_' + trackId);
    const icon  = document.getElementById('icon_'  + trackId);
    if (currentAudio && currentAudio !== audio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        const prevIcon = document.getElementById('icon_' + currentAudio.id.replace('audio_', ''));
        if (prevIcon) prevIcon.className = 'fas fa-play';
    }
    if (audio.paused) {
        audio.play();
        icon.className = 'fas fa-pause';
        currentAudio = audio;
        audio.onended = () => { icon.className = 'fas fa-play'; };
    } else {
        audio.pause();
        audio.currentTime = 0;
        icon.className = 'fas fa-play';
        currentAudio = null;
    }
}

function salvaFromButton(btn) {
    salva(btn, JSON.parse(btn.getAttribute('data-brano')));
}

function getCardTemplateLV0(b) {
    const branoData = {
        titolo:      b.trackName,
        artista:     b.artistName,
        anno:        b.releaseDate?.slice(0, 4) ?? '',
        durata:      Math.round((b.trackTimeMillis ?? 0) / 1000),
        genere:      b.primaryGenreName ?? '',
        img_url:     b.artworkUrl100 ?? '',
        preview_url: b.previewUrl ?? ''
    };
    return `<div class="card">
        <div class="card-image">
            <img src="${b.artworkUrl100 ?? ''}" style="width:100%; border-radius:8px;">
        </div>
        <div class="card-title">${b.trackName}</div>
        <div class="card-subtitle">${b.artistName}</div>
        <div class="card-subtitle" style="font-size:11px; opacity:.6">
            ${b.collectionName ?? ''} · ${b.releaseDate?.slice(0, 4) ?? ''}
        </div>
        <div class="card-subtitle" style="font-size:11px; opacity:.6">
            ${b.primaryGenreName ?? ''}
        </div>
        <br>
        ${b.previewUrl ? `
        <audio id="audio_${b.trackId}" src="${b.previewUrl}"></audio>
        <button class="card-action" style="margin-bottom:6px" onclick="togglePreview(${b.trackId})">
            <i class="fas fa-play" id="icon_${b.trackId}"></i> Anteprima
        </button>` : ''}
        <button class="card-action" data-brano='${JSON.stringify(branoData)}' onclick="salvaFromButton(this)">
            <i class="fas fa-plus"></i> Aggiungi al DB
        </button>
        <div class="card-actions" data-track-id="${b.trackId}"></div>
    </div>`;
}

function getCardTemplateLV1(b) {
    return `<div class="card">
        <div class="card-image">
            <img src="${b.artworkUrl100 ?? ''}" style="width:100%; border-radius:8px; background:#f0f0f0;">
        </div>
        <div class="card-title">${b.trackName}</div>
        <div class="card-subtitle">${b.artistName}</div>
        <div class="card-subtitle" style="font-size:11px; opacity:.6">
            ${b.releaseDate?.slice(0, 4) ?? ''} · ${b.primaryGenreName ?? ''}
        </div>
        <br>
        <button class="card-action" onclick="aggiungiPreferito(this, ${b.trackId})">
            <i class="fas fa-heart"></i> Aggiungi ai Preferiti
        </button>
        <button class="card-action" style="margin-top:6px" onclick="mostraPlaylistDialog(${b.trackId})">
            <i class="fas fa-list"></i> Aggiungi a Playlist
        </button>
        <div class="card-actions" data-track-id="${b.trackId}"></div>
    </div>`;
}

function getCardTemplate(b) {
    const playButton = b.preview_url
        ? `<audio id="audio_${b.id_brano}" src="${b.preview_url}"></audio>
           <button class="card-action" style="margin-bottom:6px" onclick="togglePreview(${b.id_brano})">
               <i class="fas fa-play" id="icon_${b.id_brano}"></i> Riproduci
           </button>`
        : `<button class="card-action" style="margin-bottom:6px; opacity:.5; cursor:not-allowed;">
               <i class="fas fa-play"></i> Riproduci
           </button>`;

    return `<div class="card" id="brano_${b.id_brano}">
        <div class="card-image"><i class="fas fa-music"></i></div>
        <div class="card-title">${b.titolo ?? 'Senza titolo'}</div>
        <div class="card-subtitle">${b.artista ?? 'Artista sconosciuto'}</div>
        <div class="card-subtitle" style="font-size:11px; opacity:.6">
            ${b.anno ?? ''} · ${b.genere ?? ''} · ${b.durata ?? 'N/A'}
        </div>
        <br>
        ${playButton}
        <button class="card-action" style="margin-bottom:6px" onclick="mostraPlaylistDialog(${b.id_brano})">
            <i class="fas fa-list"></i> Aggiungi a Playlist
        </button>
        <button class="card-action" style="background:#e74c3c; margin-bottom:6px;" onclick="rimuoviPreferito(this, ${b.id_brano})">
            <i class="fas fa-trash"></i> Rimuovi
        </button>
    </div>`;
}

// ──────────────────────────────────────────────────────────────────────
// AZIONI CARD - Funzioni centralizzate per riutilizzo in tutte le pagine
// ──────────────────────────────────────────────────────────────────────

async function salva(btn, brano) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'save');
    const branoObj = typeof brano === 'string' ? JSON.parse(brano) : brano;
    Object.entries(branoObj).forEach(([k, v]) => fd.append(k, v));
    try {
        const res  = await fetch('cerca.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok' || data.status === 'exists') {
            const branoId = data.brano_id;
            const card = btn.closest('.card');
            const actionsDiv = card.querySelector('[data-track-id]');

            btn.textContent = data.status === 'ok' ? '✅ Aggiunto!' : 'ℹ️ Già presente';

            await mostraAzioni(actionsDiv, branoId);
        } else {
            btn.textContent = '❌ Errore';
            btn.disabled = false;
        }
    } catch {
        btn.textContent = '❌ Errore';
        btn.disabled = false;
    }
}

async function mostraAzioni(container, branoId) {
    container.innerHTML = `
        <button class="card-action" style="margin-top:8px; background:#e74c3c; padding:10px; border:none; border-radius:8px; color:white; cursor:pointer; width:100%; font-weight:500;" onclick="aggiungiPreferito(this, ${branoId})">
            <i class="fas fa-heart"></i> Aggiungi ai Preferiti
        </button>
        <button class="card-action" style="margin-top:6px;" onclick="mostraPlaylistDialog(${branoId})">
            <i class="fas fa-list"></i> Aggiungi a Playlist
        </button>`;
}

async function aggiungiPreferito(btn, branoId) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'add_favorite');
    fd.append('brano_id', branoId);
    try {
        const res  = await fetch('cerca.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') {
            btn.textContent = '❤️ Aggiunto ai Preferiti!';
            btn.style.color = '#e74c3c';
        } else if (data.status === 'exists') {
            btn.textContent = '❤️ Già nei Preferiti';
            btn.style.color = '#e74c3c';
        } else {
            btn.textContent = '❌ Errore';
            btn.disabled = false;
        }
    } catch {
        btn.textContent = '❌ Errore';
        btn.disabled = false;
    }
}

async function mostraPlaylistDialog(branoId) {
    try {
        const fd = new FormData();
        fd.append('action', 'get_playlists');
        fd.append('brano_id', branoId);
        const res  = await fetch('cerca.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status !== 'ok' || !data.playlists.length) {
            alert('Non hai playlist. Creane una dalla home!');
            return;
        }

        const playlistHtml = data.playlists.map(p =>
            p.has_brano
                ? `<option value="${p.id_playlist}" disabled>✅ ${p.nome} (già aggiunto)</option>`
                : `<option value="${p.id_playlist}">${p.nome}</option>`
        ).join('');

        const dialog = document.createElement('div');
        dialog.id = 'playlist-dialog-overlay';
        dialog.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:9999;';
        dialog.innerHTML = `
            <div style="background:linear-gradient(135deg, rgba(40,40,40,0.95), rgba(30,30,30,0.95)); padding:30px; border-radius:15px; box-shadow:0 8px 32px rgba(29,185,84,0.3); max-width:450px; width:90%; border:1px solid rgba(29,185,84,0.3);">
                <h3 style="margin-top:0; margin-bottom:20px; color:#1DB954; font-size:20px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-list"></i> Aggiungi a Playlist
                </h3>
                <select id="playlist_select" style="padding:12px; border-radius:8px; border:1px solid rgba(29,185,84,0.5); width:100%; cursor:pointer; font-size:14px; margin-bottom:20px; background:rgba(0,0,0,0.3); color:#fff;">
                    <option value="">-- Seleziona una playlist --</option>
                    ${playlistHtml}
                </select>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="chiudiDialog()" style="padding:10px 20px; border-radius:8px; border:1px solid rgba(255,255,255,0.3); background:rgba(0,0,0,0.3); color:#fff; cursor:pointer;">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" onclick="aggiungiPlaylist(${branoId})" style="padding:10px 20px; border-radius:8px; border:none; background:linear-gradient(135deg, #1DB954, #1ed760); color:#000; cursor:pointer; font-weight:600;">
                        <i class="fas fa-plus"></i> Aggiungi
                    </button>
                </div>
            </div>`;
        document.body.appendChild(dialog);

        dialog.addEventListener('click', e => { if (e.target === dialog) chiudiDialog(); });
        const handleEsc = e => { if (e.key === 'Escape') { chiudiDialog(); document.removeEventListener('keydown', handleEsc); } };
        document.addEventListener('keydown', handleEsc);

    } catch {
        alert('❌ Errore nel caricamento delle playlist');
    }
}

function chiudiDialog() {
    const dialog = document.getElementById('playlist-dialog-overlay');
    if (dialog) {
        dialog.style.display = 'none';
        setTimeout(() => dialog?.parentNode?.removeChild(dialog), 100);
    }
}

async function aggiungiPlaylist(branoId) {
    const select = document.getElementById('playlist_select');
    if (!select?.value) { alert('Seleziona una playlist'); return; }
    const fd = new FormData();
    fd.append('action', 'add_to_playlist');
    fd.append('brano_id', branoId);
    fd.append('playlist_id', select.value);
    try {
        const res  = await fetch('cerca.php', { method: 'POST', body: fd });
        const data = await res.json();
        alert(data.status === 'ok'     ? '✅ Brano aggiunto alla playlist!'
            : data.status === 'exists' ? 'ℹ️ Brano già presente in questa playlist'
            : '❌ Errore: ' + (data.message || 'Errore sconosciuto'));
        chiudiDialog();
    } catch (err) {
        alert('❌ Errore: ' + err.message);
    }
}

async function rimuoviPreferito(btn, branoId) {
    if (!confirm('Sei sicuro di voler rimuovere questo brano dai preferiti?')) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rimozione...';

    const fd = new FormData();
    fd.append('action', 'remove_favorite');
    fd.append('brano_id', branoId);

    try {
        const res  = await fetch('cerca.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') {
            const card = document.getElementById('brano_' + branoId);
            if (card) {
                card.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => {
                    card.remove();
                    // Controlla se ci sono griglie vuote e mostra empty-state
                    document.querySelectorAll('.grid-container').forEach(grid => {
                        if (grid.children.length === 0) {
                            grid.innerHTML = `
                                <div class="empty-state" style="grid-column: 1/-1;">
                                    <i class="fas fa-heart"></i>
                                    <p>Non hai ancora brani nei preferiti.</p>
                                    <a href="cerca.php" class="hero-btn" style="text-decoration:none; display:inline-block; padding:12px 30px; background:#1DB954; color:white; border-radius:8px; font-weight:500;">
                                        <i class="fas fa-search"></i> Inizia a Cercare
                                    </a>
                                </div>`;
                        }
                    });
                }, 300);
            }
        } else {
            alert('Errore nella rimozione del brano');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i> Rimuovi';
        }
    } catch (err) {
        alert('Errore: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Rimuovi';
    }
}