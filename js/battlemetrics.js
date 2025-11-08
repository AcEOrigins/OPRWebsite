(function (window) {
    const STATUS_ONLINE = 'online';

    const Battlemetrics = {
        renderCards(container, servers) {
            if (!container) {
                return;
            }

            container.classList.add('bm-grid');
            container.innerHTML = '';

            if (!servers || servers.length === 0) {
                container.innerHTML = '<p class="bm-empty-state">No servers configured yet.</p>';
                return;
            }

            servers.forEach(server => {
                if (!server || !server.battlemetricsId) {
                    return;
                }

                const card = createServerCard(server);
                container.appendChild(card);
                fetchServerData(server, card);
            });
        }
    };

    function escapeHtml(str = '') {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createServerCard(server) {
        const serverName = escapeHtml(server.displayName || server.name || 'Untitled Server');
        const card = document.createElement('div');
        card.className = 'bm-card';
        card.dataset.serverId = server.id;

        card.innerHTML = `
            <div class="bm-status-line" data-role="status-line"></div>
            <div class="bm-card-header">
                <h2 class="bm-card-title">${serverName}</h2>
                <div class="bm-status" data-role="status">
                    <span class="bm-status-dot" data-role="status-dot"></span>
                    <span class="bm-status-text" data-role="status-text">Loading...</span>
                </div>
            </div>
            <div class="bm-players-row">
                <div class="bm-info-card square">
                    <div class="bm-info-label">Players</div>
                    <div class="bm-info-value" data-role="players">-</div>
                </div>
                <div class="bm-info-card square">
                    <div class="bm-info-label">Max Players</div>
                    <div class="bm-info-value" data-role="max-players">-</div>
                </div>
            </div>
            <div class="bm-meta-row">
                <div class="bm-info-card">
                    <div class="bm-info-label">Map</div>
                    <div class="bm-info-value" data-role="map">Loading...</div>
                </div>
                <div class="bm-info-card">
                    <div class="bm-info-label">Game Mode</div>
                    <div class="bm-info-value" data-role="game-mode">Loading...</div>
                </div>
            </div>
            <div class="bm-uptime-card">
                <div class="bm-info-card">
                    <div class="bm-info-label">Uptime</div>
                    <div class="bm-info-value" data-role="uptime">N/A</div>
                </div>
            </div>
            <div class="bm-ip-wrapper">
                <div class="bm-ip-text">
                    <span class="bm-info-label">Server IP</span>
                    <span class="bm-ip-address" data-role="ip-address">Loading...</span>
                </div>
                <button class="bm-copy-btn" type="button" data-role="copy-btn">Copy</button>
            </div>
        `;

        const copyBtn = card.querySelector('[data-role="copy-btn"]');
        copyBtn.addEventListener('click', () => copyIpToClipboard(card));

        return card;
    }

    function fetchServerData(server, card) {
        const battlemetricsId = server.battlemetricsId;
        if (!battlemetricsId) {
            displayError(card);
            return;
        }

        const apiUrl = `https://api.battlemetrics.com/servers/${battlemetricsId}?include=player`;

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }
                return response.json();
            })
            .then(data => updateServerDisplay(data, card))
            .catch(err => {
                console.error(`Error fetching server data for ${battlemetricsId}:`, err);
                displayError(card);
            });
    }

    function updateServerDisplay(data, card) {
        const attributes = data?.data?.attributes || {};
        const details = attributes.details || {};

        setStatus(card, attributes.status);
        setText(card, 'players', attributes.players ?? '0');
        setText(card, 'max-players', attributes.maxPlayers ?? '0');
        setText(card, 'map', details.map || 'Unknown');
        setText(card, 'game-mode', details.mode || details.gameMode || 'Unknown');

        if (details.uptime) {
            setText(card, 'uptime', formatUptime(details.uptime));
        } else if (attributes.startTime) {
            setText(card, 'uptime', calculateUptime(attributes.startTime));
        } else {
            setText(card, 'uptime', 'N/A');
        }

        const ipPort = `${attributes.ip || 'N/A'}:${attributes.port || 'N/A'}`;
        setText(card, 'ip-address', ipPort);
    }

    function setStatus(card, status) {
        const statusDot = card.querySelector('[data-role="status-dot"]');
        const statusText = card.querySelector('[data-role="status-text"]');
        const statusLine = card.querySelector('[data-role="status-line"]');

        const isOnline = status === STATUS_ONLINE;
        statusDot.classList.toggle('online', isOnline);
        statusDot.classList.toggle('offline', !isOnline);
        statusLine.classList.toggle('online', isOnline);
        statusLine.classList.toggle('offline', !isOnline);

        statusText.textContent = isOnline ? 'Online' : 'Offline';
    }

    function setText(card, role, value) {
        const el = card.querySelector(`[data-role="${role}"]`);
        if (el) {
            el.textContent = value;
        }
    }

    function displayError(card) {
        setStatus(card, 'offline');
        setText(card, 'players', '0');
        setText(card, 'max-players', '0');
        setText(card, 'map', 'Unknown');
        setText(card, 'game-mode', 'Unknown');
        setText(card, 'uptime', 'N/A');
        setText(card, 'ip-address', 'N/A');
    }

    function copyIpToClipboard(card) {
        const ipEl = card.querySelector('[data-role="ip-address"]');
        if (!ipEl) return;

        const value = ipEl.textContent.trim();
        if (!value || value === 'Loading...' || value === 'N/A') return;

        navigator.clipboard.writeText(value).then(() => {
            const btn = card.querySelector('[data-role="copy-btn"]');
            if (!btn) return;
            const original = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => (btn.textContent = original), 1500);
        }).catch(error => {
            console.error('Copy failed:', error);
        });
    }

    function calculateUptime(startTime) {
        try {
            const start = new Date(startTime);
            const diff = Date.now() - start.getTime();
            return formatDuration(diff / 1000);
        } catch {
            return 'N/A';
        }
    }

    function formatUptime(seconds) {
        if (!seconds && seconds !== 0) {
            return 'N/A';
        }
        return formatDuration(seconds);
    }

    function formatDuration(seconds) {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);

        if (days > 0) {
            return `${days}d ${hours}h ${minutes}m`;
        }
        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m`;
    }

    window.Battlemetrics = Battlemetrics;
})(window);
