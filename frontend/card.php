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