const search = document.getElementById('search');
search.addEventListener('input', function () {
    const term = this.value.trim().toLocaleLowerCase('pt-BR');
    let visible = 0;
    document.querySelectorAll('.project').forEach(card => {
        const show = card.dataset.search.toLocaleLowerCase('pt-BR').includes(term);
        card.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    document.getElementById('empty').classList.toggle('d-none', visible !== 0);
});

const logKey = 'core-docs-index:logs:v1';
const unreadKey = 'core-docs-index:logs-unread:v1';
if (document.body.dataset.clearPanel === '1') {
    try {
        localStorage.removeItem(logKey);
        localStorage.removeItem(unreadKey);
    } catch (error) {
        // O painel também é reiniciado quando o armazenamento está indisponível.
    }
}
let unreadCount = 0;
try {
    unreadCount = Number.parseInt(localStorage.getItem(unreadKey) || '0', 10) || 0;
} catch (error) {
    unreadCount = 0;
}
let logs = [];
try {
    const saved = JSON.parse(localStorage.getItem(logKey) || '[]');
    if (Array.isArray(saved)) logs = saved;
} catch (error) {
    logs = [];
}

const eventElement = document.getElementById('scan-event');
const event = eventElement ? JSON.parse(eventElement.textContent) : null;
if (event && event.project && event.message) {
    logs.unshift({
        time: new Date().toISOString(),
        project: event.project,
        message: event.message
    });
    logs = logs.slice(0, 100);
    unreadCount++;
    try {
        localStorage.setItem(unreadKey, String(unreadCount));
        localStorage.setItem(logKey, JSON.stringify(logs));
    } catch (error) {
        // O modal continua funcionando nesta página se o navegador bloquear o armazenamento.
    }
}

function updateLogBadge() {
    const badge = document.getElementById('logs-count');
    badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
    badge.classList.toggle('d-none', unreadCount === 0);
}
updateLogBadge();

document.getElementById('logsModal').addEventListener('show.bs.modal', () => {
    unreadCount = 0;
    try { localStorage.setItem(unreadKey, '0'); } catch (error) {}
    updateLogBadge();
});

function renderLogs() {
    const container = document.getElementById('log-cards');
    container.replaceChildren();
    if (!logs.length) {
        const empty = document.createElement('p');
        empty.className = 'text-secondary mb-0';
        empty.textContent = 'Nenhuma interação registrada neste navegador.';
        container.appendChild(empty);
        return;
    }
    logs.forEach(log => {
        const column = document.createElement('div');
        column.className = 'col-12';
        const card = document.createElement('div');
        card.className = 'card border shadow-sm';
        const body = document.createElement('div');
        body.className = 'card-body py-3';
        const time = document.createElement('time');
        time.className = 'small text-secondary d-block';
        time.dateTime = log.time;
        time.textContent = new Date(log.time).toLocaleString('pt-BR');
        const title = document.createElement('strong');
        title.className = 'd-block';
        title.textContent = log.project;
        const message = document.createElement('p');
        message.className = 'mb-0';
        message.textContent = log.message;
        body.append(time, title, message);
        card.appendChild(body);
        column.appendChild(card);
        container.appendChild(column);
    });
}
renderLogs();

document.getElementById('clear-logs').addEventListener('click', () => {
    logs = [];
    unreadCount = 0;
    updateLogBadge();
    try {
        localStorage.removeItem(logKey);
        localStorage.removeItem(unreadKey);
    } catch (error) {
        // O histórico em memória também é limpo.
    }
    renderLogs();
});
