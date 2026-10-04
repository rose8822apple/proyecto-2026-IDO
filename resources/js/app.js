import './bootstrap';

const isDebugEnabled = () => document.body?.dataset.debug === 'true';

const debugLog = (message, details = {}) => {
    if (isDebugEnabled()) {
        console.info(`[RedSalud] ${message}`, details);
    }
};

const debugWarn = (message, details = {}) => {
    if (isDebugEnabled()) {
        console.warn(`[RedSalud] ${message}`, details);
    }
};

const debugError = (message, error) => {
    if (isDebugEnabled()) {
        console.error(`[RedSalud] ${message}`, error);
    }
};

const showToast = (message, type = 'info') => {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    toast.innerHTML = `
        <div class="toast-icon"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'}"></i></div>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 250);
    }, 2600);
};

const setupNotifications = () => {
    const toggle = document.querySelector('[data-notification-toggle]');
    const panel = document.querySelector('[data-notification-panel]');
    if (!toggle || !panel) return;

    const closePanel = () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));

        if (!panel.hidden && document.querySelector('[data-notification-count]')) {
            debugLog('Marcando notificaciones como vistas');
            fetch(toggle.dataset.readUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            }).then((response) => {
                if (!response.ok) throw new Error('No se pudieron marcar como vistas las notificaciones');
                document.querySelector('[data-notification-count]')?.remove();
                const total = panel.querySelector('[data-notification-total]');
                if (total) total.textContent = '0';
                debugLog('Notificaciones marcadas como vistas');
            }).catch((error) => {
                debugError('No se pudieron marcar las notificaciones como vistas', error);
            });
        }

        if (!panel.hidden) debugLog('Panel de notificaciones abierto');
    });

    panel.addEventListener('click', (event) => {
        if (event.target.closest('a')) closePanel();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.notification-wrap')) closePanel();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePanel();
    });
};

const setupModuleTable = () => {
    const table = document.querySelector('[data-table-role="module-table"]');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = document.querySelector('.search-box input');
    const recordCount = document.querySelector('[data-record-count]');
    const filterToggle = document.querySelector('[data-status-filter-toggle]');
    const filterMenu = document.querySelector('[data-status-filter-menu]');
    const statusColumnIndexes = Array.from(table.querySelectorAll('thead th'))
        .map((header, index) => header.hasAttribute('data-status-column') ? index : -1)
        .filter((index) => index >= 0);
    const normalize = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase()
        .replace(/[^a-z0-9]/g, '');
    const statuses = [...new Set(rows.flatMap((row) => statusColumnIndexes
        .map((index) => row.cells[index]?.textContent.trim())
        .filter(Boolean)))].sort((first, second) => first.localeCompare(second, 'es'));
    let currentStatus = '';

    if (filterToggle && filterMenu) {
        const options = ['Todos los estados', ...statuses];
        options.forEach((status, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = `filter-option${index === 0 ? ' active' : ''}`;
            option.dataset.filterStatus = index === 0 ? '' : status;
            option.textContent = status;
            filterMenu.appendChild(option);
        });

        filterToggle.addEventListener('click', () => {
            filterMenu.hidden = !filterMenu.hidden;
            filterMenu.classList.toggle('open', !filterMenu.hidden);
            filterToggle.setAttribute('aria-expanded', String(!filterMenu.hidden));
        });

        filterMenu.addEventListener('click', (event) => {
            const option = event.target.closest('[data-filter-status]');
            if (!option) return;

            currentStatus = option.dataset.filterStatus;
            filterMenu.querySelectorAll('.filter-option').forEach((item) => {
                item.classList.toggle('active', item === option);
            });
            filterToggle.setAttribute('aria-label', currentStatus ? `Filtrar: ${currentStatus}` : 'Filtrar por estado');
            filterMenu.hidden = true;
            filterMenu.classList.remove('open');
            filterToggle.setAttribute('aria-expanded', 'false');
            applyFilters();
            debugLog('Filtro de estado aplicado', { status: currentStatus || 'todos' });
        });
    }

    const applyFilters = () => {
        const query = normalize(searchInput?.value ?? '');
        let visibleCount = 0;

        rows.forEach((row) => {
            const cedula = row.querySelector('[data-search-cedula]')?.dataset.searchCedula ?? '';
            const searchableText = normalize(`${row.textContent} ${cedula}`);
            const matchesSearch = !query || searchableText.includes(query);
            const matchesStatus = !currentStatus || statusColumnIndexes.some((index) => (
                normalize(row.cells[index]?.textContent ?? '') === normalize(currentStatus)
            ));
            const shouldDisplay = matchesSearch && matchesStatus;
            row.style.display = shouldDisplay ? '' : 'none';
            if (shouldDisplay) visibleCount += 1;
        });

        if (recordCount) recordCount.textContent = String(visibleCount);
    };

    searchInput?.addEventListener('input', applyFilters);

    applyFilters();
};

const setupDashboardActions = () => {
    document.querySelectorAll('.icon-button:not([data-notification-toggle]), .more-button, .coverage-link, .alert-list a, .text-link').forEach((element) => {
        element.addEventListener('click', (event) => {
            const actionLabel = element.textContent ? element.textContent.replace(/\s+/g, ' ').trim() : 'Acción';
            if (element.tagName === 'A' && element.getAttribute('href') && !element.hasAttribute('data-action')) {
                return;
            }
            event.preventDefault();
            showToast(`Se ejecutó: ${actionLabel}`, 'info');
        });
    });
};

const validatePositiveNumber = (input) => {
    if (!input || input.type !== 'number') {
        return true;
    }

    const raw = input.value.trim();

    if (raw === '') {
        return true;
    }

    const value = Number(raw);

    if (!Number.isFinite(value) || value < 0) {
        input.setCustomValidity('El valor no puede ser negativo');
        input.reportValidity();
        showToast('El valor no puede ser negativo', 'warning');
        return false;
    }

    input.setCustomValidity('');
    return true;
};

document.addEventListener('DOMContentLoaded', () => {
    debugLog('Vista cargada', {
        view: document.querySelector('.page-heading h1')?.textContent.trim() || document.title,
        path: window.location.pathname,
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            let isValid = true;

            form.querySelectorAll('input[type="number"]').forEach((input) => {
                if (!validatePositiveNumber(input)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                event.preventDefault();
                debugWarn('Formulario detenido por validación de valores');
                return;
            }

            const method = form.querySelector('input[name="_method"]')?.value || form.method;
            debugLog('Enviando formulario', {
                method: method.toUpperCase(),
                path: new URL(form.action, window.location.href).pathname,
            });
        });
    });

    setupDashboardActions();
    setupModuleTable();
    setupNotifications();

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-status-filter-toggle], [data-status-filter-menu]')) return;

        document.querySelectorAll('[data-status-filter-menu]').forEach((menu) => {
            menu.hidden = true;
            menu.classList.remove('open');
        });
        document.querySelectorAll('[data-status-filter-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
});
