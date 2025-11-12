(() => {
    const STATUS_ONLINE = 'online';
    const API_PROXY_URL = 'Api/battlemetrics.php';

    function escapeHtml(value = '') {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createServerCard(server) {
        const name = server.displayName || server.gameTitle || 'Untitled Server';
        const card = document.createElement('div');
        card.className = 'server-card';
        card.dataset.serverId = server.id ?? '';
        card.dataset.battlemetricsId = server.battlemetricsId ?? '';

        const subtitle = server.gameTitle || server.region ? `
            <p class="server-card-subtitle">
                ${escapeHtml(server.gameTitle || '')}
                ${server.gameTitle && server.region ? ' • ' : ''}
                ${escapeHtml(server.region || '')}
            </p>
        ` : '';

        card.innerHTML = `
            <div class="server-status-line" data-role="status-line"></div>

            <div class="server-card-header">
                <div>
                    <h2 class="server-card-title">${escapeHtml(name)}</h2>
                    ${subtitle}
                </div>
                <div class="status-indicator" data-role="status">
                    <span class="status-dot" data-role="status-dot"></span>
                    <span class="status-text" data-role="status-text">Loading...</span>
                </div>
            </div>

            <div class="server-card-content">
                <div class="players-pair">
                    <div class="info-card square-card">
                        <div class="info-label">Population</div>
                        <div class="info-value" data-role="population">-</div>
                    </div>
                    <div class="info-card square-card">
                        <div class="info-label">Max Players</div>
                        <div class="info-value" data-role="max-players">-</div>
                    </div>
                </div>

                <div class="server-info-grid">
                    <div class="info-card">
                        <div class="info-label">Map</div>
                        <div class="info-value" data-role="map">Loading...</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Game Mode</div>
                        <div class="info-value" data-role="game-mode">${escapeHtml(server.gameTitle || 'Loading...')}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Queue</div>
                        <div class="info-value small" data-role="queue-size">-</div>
                    </div>
                </div>

                <div class="uptime-card-container">
                    <div class="info-card uptime-card">
                        <div class="info-label">Uptime</div>
                        <div class="info-value" data-role="uptime">N/A</div>
                    </div>
                </div>

                <div class="server-connect-section">
                    <div class="server-ip-display">
                        <span>Server IP:</span>
                        <span class="ip-address" data-role="ip-address">Loading...</span>
                        <button class="copy-btn" type="button" data-role="copy-btn">Copy</button>
                    </div>
                </div>

                <div class="server-mods">
                    <h3>Server Mods</h3>
                    <div class="mods-list" data-role="mods"></div>
                </div>

                <div class="server-conflicts">
                    <h3>Active Conflicts</h3>
                    <ul class="conflict-list" data-role="conflicts"></ul>
                </div>
            </div>
        `;

        const copyBtn = card.querySelector('[data-role="copy-btn"]');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => copyIpToClipboard(card));
        }

        return card;
    }

    async function hydrateCard(card, server) {
        const battlemetricsId = server.battlemetricsId;
        if (!battlemetricsId) {
            displayError(card, 'Missing BattleMetrics ID');
            return;
        }

        try {
            const response = await fetch(`${API_PROXY_URL}?serverId=${encodeURIComponent(battlemetricsId)}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            updateServerDisplay(card, server, data);
        } catch (error) {
            console.error(`Error fetching server data for ${battlemetricsId}:`, error);
            displayError(card, 'Unavailable');
        }
    }

    function updateServerDisplay(card, server, payload) {
        const attributes = payload?.data?.attributes || {};
        const details = attributes.details || {};

        setStatus(card, attributes.status);
        setText(card, 'population', attributes.players ?? '0');
        setText(card, 'max-players', attributes.maxPlayers ?? '0');
        setText(card, 'map', details.map || 'Unknown');
        setText(card, 'queue-size', details.queue ?? attributes.queue ?? '0');

        const gameMode =
            details.mode ||
            details.gameMode ||
            server.gameTitle ||
            server.region ||
            'Unknown';
        setText(card, 'game-mode', gameMode);

        if (details.uptime) {
            setText(card, 'uptime', formatUptime(details.uptime));
        } else if (attributes.startTime) {
            setText(card, 'uptime', calculateUptime(attributes.startTime));
        } else {
            setText(card, 'uptime', 'N/A');
        }

        const ipAddress = formatIpPort(attributes.ip, attributes.port);
        setText(card, 'ip-address', ipAddress);

        const mods = extractMods(details);
        renderMods(card, mods);

        const conflicts = extractConflicts(payload);
        renderConflicts(card, conflicts);
    }

    function formatIpPort(ip, port) {
        if (!ip && !port) {
            return 'N/A';
        }
        if (ip && port) {
            return `${ip}:${port}`;
        }
        return ip || port || 'N/A';
    }

    function setStatus(card, status) {
        const statusDot = card.querySelector('[data-role="status-dot"]');
        const statusText = card.querySelector('[data-role="status-text"]');
        const statusLine = card.querySelector('[data-role="status-line"]');

        const isOnline = status === STATUS_ONLINE;

        if (statusDot) {
            statusDot.classList.toggle('online', isOnline);
            statusDot.classList.toggle('offline', !isOnline);
        }

        if (statusLine) {
            statusLine.classList.toggle('online', isOnline);
            statusLine.classList.toggle('offline', !isOnline);
        }

        if (statusText) {
            statusText.textContent = isOnline ? 'Online' : 'Offline';
        }
    }

    function setText(card, role, value) {
        const el = card.querySelector(`[data-role="${role}"]`);
        if (el) {
            el.textContent = value;
        }
    }

    function renderMods(card, mods) {
        const container = card.querySelector('[data-role="mods"]');
        if (!container) {
            return;
        }

        if (!mods.length) {
            container.innerHTML = '<span class="mods-empty">No mods detected</span>';
            return;
        }

        container.innerHTML = mods.map(mod => `
            <span class="mod-pill" title="${escapeHtml(mod.description || mod.name || '')}">
                ${escapeHtml(mod.name || mod)}
            </span>
        `).join('');
    }

    function renderConflicts(card, conflicts) {
        const container = card.querySelector('[data-role="conflicts"]');
        if (!container) {
            return;
        }

        if (!conflicts.length) {
            container.innerHTML = '<li class="conflict-empty">No current conflicts</li>';
            return;
        }

        container.innerHTML = conflicts.map(conflict => `
            <li class="conflict-item">
                <span class="conflict-name">${escapeHtml(conflict.name)}</span>
                ${conflict.status ? `<span class="conflict-status">${escapeHtml(conflict.status)}</span>` : ''}
                ${conflict.faction ? `<span class="conflict-faction">${escapeHtml(conflict.faction)}</span>` : ''}
            </li>
        `).join('');
    }

    function extractMods(details = {}) {
        const mods = new Set();

        if (Array.isArray(details.mods)) {
            details.mods.forEach(mod => mods.add(mod));
        }

        if (Array.isArray(details.settings?.mods)) {
            details.settings.mods.forEach(mod => mods.add(mod));
        }

        if (Array.isArray(details.settings?.installedMods)) {
            details.settings.installedMods.forEach(mod => mods.add(mod));
        }

        return Array.from(mods).map(mod => {
            if (typeof mod === 'string') {
                return { name: mod };
            }
            if (mod && typeof mod === 'object') {
                return {
                    name: mod.name || mod.title || 'Unknown Mod',
                    description: mod.description || mod.summary || ''
                };
            }
            return { name: String(mod) };
        });
    }

    function extractConflicts(payload = {}) {
        const included = Array.isArray(payload.included) ? payload.included : [];

        return included
            .filter(item => item?.type === 'conflict')
            .map(item => {
                const attributes = item.attributes || {};
                return {
                    name: attributes.name || attributes.title || 'Conflict',
                    status: attributes.state || attributes.status || '',
                    faction: attributes.faction || attributes.team || ''
                };
            })
            .filter(conflict => conflict.name);
    }

    function displayError(card, reason) {
        setStatus(card, 'offline');
        setText(card, 'population', '0');
        setText(card, 'max-players', '0');
        setText(card, 'map', 'Unknown');
        setText(card, 'queue-size', '0');
        setText(card, 'game-mode', reason || 'Unknown');
        setText(card, 'uptime', 'N/A');
        setText(card, 'ip-address', 'N/A');

        renderMods(card, []);
        renderConflicts(card, []);
    }

    function copyIpToClipboard(card) {
        const ipEl = card.querySelector('[data-role="ip-address"]');
        if (!ipEl) {
            return;
        }

        const value = ipEl.textContent.trim();
        if (!value || value === 'Loading...' || value === 'N/A') {
            return;
        }

        navigator.clipboard.writeText(value).then(() => {
            const btn = card.querySelector('[data-role="copy-btn"]');
            if (!btn) {
                return;
            }

            const original = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => {
                btn.textContent = original;
            }, 1500);
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
        const totalSeconds = Math.max(0, Math.floor(seconds));
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);

        if (days > 0) {
            return `${days}d ${hours}h ${minutes}m`;
        }
        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m`;
    }

    const Battlemetrics = {
        async renderCards(container, servers) {
            if (!container) {
                return;
            }

            container.classList.add('bm-grid');
            container.innerHTML = '';

            if (!Array.isArray(servers) || servers.length === 0) {
                container.innerHTML = '<p class="bm-empty-state">No servers configured yet.</p>';
                return;
            }

            servers.forEach(server => {
                const card = createServerCard(server);
                container.appendChild(card);
                hydrateCard(card, server);
            });
        }
    };

    window.Battlemetrics = Battlemetrics;
})();

