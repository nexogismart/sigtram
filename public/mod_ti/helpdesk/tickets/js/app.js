let allTickets = {};
let staff = [];
let previousSituation1Count = 0;
let audioEnabled = false;

const alertSound = new Audio(window.TICKETS_PANTALLA?.audioAlerta || '');
alertSound.preload = 'auto';
alertSound.volume = 1.0;

function enableAudioSimple() {
  if (!audioEnabled) {
    alertSound
      .play()
      .then(() => {
        alertSound.pause();
        alertSound.currentTime = 0;
        audioEnabled = true;
        document.body.style.borderTop = '3px solid #28a745';
        setTimeout(() => (document.body.style.borderTop = ''), 2000);
      })
      .catch(() => {});
  }
}

function playAlert() {
  if (audioEnabled) {
    alertSound.currentTime = 0;
    alertSound.play().catch(() => {});
  }
}

function loadTicketsFromDB() {
  const url = window.TICKETS_PANTALLA?.routes?.datos;
  if (!url) return;

  fetch(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const newSituation1Count = (data.situation1 || []).length;
        if (previousSituation1Count > 0 && newSituation1Count > previousSituation1Count) {
          playAlert();
        }
        previousSituation1Count = newSituation1Count;

        allTickets = {
          1: data.situation1 || [],
          2: data.situation2 || [],
          3: data.situation3 || [],
          4: data.situation4 || [],
          5: data.situation5 || [],
        };

        staff = data.staff || [];
        updateDisplay(data);
        checkClosingTime();
      } else {
        console.error('Error del servidor:', data.error);
      }
    })
    .catch((error) => {
      console.error('Error cargando tickets:', error);
    });
}

function updateTime() {
  const now = new Date();
  const timeString = now.toLocaleTimeString('es-ES', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
  const dateString = now.toLocaleDateString('es-ES', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  const el = document.getElementById('currentTime');
  if (el) el.textContent = `${dateString} - ${timeString}`;
}

function getFirstName(fullName) {
  if (!fullName) return 'Sin asignar';
  const names = fullName.trim().split(' ');
  return names[0].toUpperCase();
}

function createTicketCard(ticket) {
  const staffName = getFirstName(ticket.staff || ticket.solicitante);
  return `
                <div class="ticket-card">
                    <div class="ticket-staff-name">${escapeHtml(staffName)}</div>
                    <div class="ticket-footer">
                        <div class="ticket-assignee-container">
                            <div class="ticket-id">${escapeHtml(ticket.id)}</div>
                            <div class="ticket-assignee">${escapeHtml(ticket.asunto || ticket.detalle || 'Sin título')}</div>
                            <div class="ticket-time">${escapeHtml(ticket.fecha)}</div>
                        </div>
                    </div>
                </div>
            `;
}

function updateDisplay() {
  const requerimientoContainer = document.getElementById('tickets-1');
  const requerimientoCount = document.getElementById('count-1');
  const requerimientoTickets = allTickets[1] || [];

  requerimientoContainer.innerHTML = requerimientoTickets.map((ticket) => createTicketCard(ticket)).join('');
  requerimientoCount.textContent = requerimientoTickets.length;

  const staffContainer = document.getElementById('staffGrid');
  const sortedStaff = [...staff].sort((a, b) => (b.resolved || 0) - (a.resolved || 0));
  const medals = ['🥇', '🥈', '🥉'];

  let currentRank = 0;
  let currentResolved = -1;
  let activeIndex = 0;

  const rankedStaff = sortedStaff.map((member) => {
    const resolved = member.resolved || 0;
    if (resolved === 0) {
      return { ...member, olympicRank: null };
    }
    if (resolved !== currentResolved) {
      currentRank = activeIndex;
      currentResolved = resolved;
    }
    activeIndex++;
    return { ...member, olympicRank: currentRank };
  });

  staffContainer.innerHTML = rankedStaff
    .map((member) => {
      const resolved = member.resolved || 0;
      const cases = member.cases || 0;
      const shortName = getFirstName(member.name);
      const rank = member.olympicRank;
      const medal = rank !== null && rank < 3 ? `<div class="staff-medal">${medals[rank]}</div>` : '';
      const isFirst = rank === 0;
      const rankClass = isFirst ? 'rank-one' : member.status;
      const crown = isFirst ? `<div class="crown-icon">👑</div>` : '';

      return `
                    <div class="staff-member ${rankClass}">
                        ${crown}
                        ${medal}
                        <div class="staff-name">${escapeHtml(shortName)}</div>
                        <div class="staff-numbers">
                            <div class="staff-number cases">
                                <span class="label">A:</span>${cases}
                            </div>
                            <div class="staff-number resolved">
                                <span class="label">R:</span>${resolved}
                            </div>
                        </div>
                    </div>
                `;
    })
    .join('');
}

function refreshData() {
  loadTicketsFromDB();
}

function isClosingTime() {
  const now = new Date();
  const h = now.getHours();
  return h >= 18 && h < 24;
}

function getInitials(fullName) {
  if (!fullName) return '?';
  const parts = fullName.trim().split(' ');
  return parts.length >= 2
    ? (parts[0][0] + parts[1][0]).toUpperCase()
    : parts[0][0].toUpperCase();
}

function buildStars() {
  const container = document.getElementById('starsContainer');
  container.innerHTML = '';
  for (let i = 0; i < 80; i++) {
    const star = document.createElement('div');
    star.className = 'star';
    const size = Math.random() * 3 + 1;
    const delay = Math.random() * 4;
    const dur = (Math.random() * 3 + 2).toFixed(1) + 's';
    const op = (Math.random() * 0.6 + 0.2).toFixed(2);
    star.style.cssText = `
                    width:${size}px; height:${size}px;
                    top:${Math.random() * 100}%; left:${Math.random() * 100}%;
                    --d:${dur}; --op:${op};
                    animation-delay:${delay}s;
                `;
    container.appendChild(star);
  }
}

function renderClosingScreen() {
  const screen = document.getElementById('closingScreen');
  const podiumEl = document.getElementById('closingPodium');
  const dateEl = document.getElementById('closingDate');
  const medals = ['🥇', '🥈', '🥉'];
  const rankColors = ['podium-rank-0', 'podium-rank-1', 'podium-rank-2'];

  const now = new Date();
  dateEl.textContent = now.toLocaleDateString('es-ES', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  const sorted = [...staff].sort((a, b) => (b.resolved || 0) - (a.resolved || 0));
  let curRank = 0,
    curResolved = -1,
    activeIdx = 0;
  const ranked = sorted.map((m) => {
    const r = m.resolved || 0;
    if (r === 0) return { ...m, olympicRank: null };
    if (r !== curResolved) {
      curRank = activeIdx;
      curResolved = r;
    }
    activeIdx++;
    return { ...m, olympicRank: curRank };
  });

  const withMedal = ranked.filter((m) => m.olympicRank !== null && m.olympicRank < 3);

  screen.classList.add('active');
  buildStars();

  if (withMedal.length === 0) {
    podiumEl.innerHTML = `
                    <div class="closing-empty">
                        <div class="empty-icon">📭</div>
                        <div class="empty-msg">Sin tickets resueltos hoy</div>
                        <div class="empty-sub">¡Mañana es una nueva oportunidad!</div>
                    </div>`;
    return;
  }

  const byRank = {};
  withMedal.forEach((m) => {
    if (!byRank[m.olympicRank]) byRank[m.olympicRank] = [];
    byRank[m.olympicRank].push(m);
  });

  const visualOrder = [1, 0, 2];
  const podiumContainer = document.createElement('div');
  podiumContainer.className = 'podium-container';

  visualOrder.forEach((rank) => {
    if (!byRank[rank]) return;
    const members = byRank[rank];
    const col = document.createElement('div');
    col.className = 'podium-card';

    if (members.length === 1) {
      const m = members[0];
      col.className += ' ' + rankColors[rank];
      col.innerHTML = `
                        <div class="podium-avatar">
                            ${escapeHtml(getInitials(m.name))}
                            <span class="podium-medal-emoji">${medals[rank]}</span>
                        </div>
                        <div class="podium-name">${escapeHtml(getFirstName(m.name))}</div>
                        <div class="podium-resolved">${m.resolved} resueltos</div>
                        <div class="podium-base">${rank + 1}°</div>
                    `;
    } else {
      const tiedAvatars = members
        .map(
          (m) => `
                        <div style="display:flex;flex-direction:column;align-items:center;">
                            <div class="podium-tied-avatar podium-tied-rank-${rank}">
                                ${escapeHtml(getInitials(m.name))}
                                <span class="podium-medal-emoji">${medals[rank]}</span>
                            </div>
                            <div class="podium-tied-name">${escapeHtml(getFirstName(m.name))}</div>
                        </div>
                    `,
        )
        .join('');

      col.innerHTML = `
                        <div class="podium-tied-row">${tiedAvatars}</div>
                        <div class="podium-resolved" style="color:rgba(255,255,255,0.6);text-align:center;margin-bottom:4px;">${members[0].resolved} resueltos c/u</div>
                        <div class="podium-base">${rank + 1}°</div>
                    `;
    }

    podiumContainer.appendChild(col);
  });

  podiumEl.appendChild(podiumContainer);
}

function checkClosingTime() {
  const screen = document.getElementById('closingScreen');
  if (isClosingTime()) {
    document.getElementById('closingPodium').innerHTML = '';
    renderClosingScreen();
  } else if (screen.classList.contains('active')) {
    screen.classList.remove('active');
    document.getElementById('closingPodium').innerHTML = '';
  }
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
  updateTime();
  setInterval(updateTime, 1000);

  document.addEventListener('click', function enableOnce() {
    enableAudioSimple();
    document.removeEventListener('click', enableOnce);
  });

  loadTicketsFromDB();
  setInterval(refreshData, 10000);

  setTimeout(() => {
    if (!audioEnabled) {
      const clickMessage = document.createElement('div');
      clickMessage.style.cssText = `
                    position: fixed;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    z-index: 9999;
                    font-weight: bold;
                    text-align: center;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    cursor: pointer;
                    animation: pulse 2s infinite;
                `;
      clickMessage.innerHTML = '🔊 Haz clic aquí para activar alertas de sonido';

      const style = document.createElement('style');
      style.textContent = `
                    @keyframes pulse {
                        0% { transform: translateX(-50%) scale(1); }
                        50% { transform: translateX(-50%) scale(1.05); }
                        100% { transform: translateX(-50%) scale(1); }
                    }
                `;
      document.head.appendChild(style);

      clickMessage.onclick = () => {
        enableAudioSimple();
        document.body.removeChild(clickMessage);
      };

      document.body.appendChild(clickMessage);
    }
  }, 1000);

  setInterval(checkClosingTime, 60000);
  checkClosingTime();
});
