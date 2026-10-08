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
    const pagination = document.querySelector('[data-table-pagination]');
    const rangeStart = pagination?.querySelector('[data-range-start]');
    const rangeEnd = pagination?.querySelector('[data-range-end]');
    const pageIndicator = pagination?.querySelector('[data-page-indicator]');
    const previousPage = pagination?.querySelector('[data-page-previous]');
    const nextPage = pagination?.querySelector('[data-page-next]');
    const pageSize = Number.parseInt(table.dataset.pageSize ?? '10', 10);
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
    let currentPage = 1;

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
            applyFilters(true);
            debugLog('Filtro de estado aplicado', { status: currentStatus || 'todos' });
        });
    }

    const applyFilters = (resetPage = false) => {
        if (resetPage) currentPage = 1;
        const query = normalize(searchInput?.value ?? '');
        const filteredRows = rows.filter((row) => {
            const cedula = row.querySelector('[data-search-cedula]')?.dataset.searchCedula ?? '';
            const searchableText = normalize(`${row.textContent} ${cedula}`);
            const matchesSearch = !query || searchableText.includes(query);
            const matchesStatus = !currentStatus || statusColumnIndexes.some((index) => (
                normalize(row.cells[index]?.textContent ?? '') === normalize(currentStatus)
            ));
            return matchesSearch && matchesStatus;
        });

        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const firstIndex = (currentPage - 1) * pageSize;
        const pageRows = new Set(filteredRows.slice(firstIndex, firstIndex + pageSize));

        rows.forEach((row) => {
            row.style.display = pageRows.has(row) ? '' : 'none';
        });

        if (recordCount) recordCount.textContent = String(filteredRows.length);
        if (rangeStart) rangeStart.textContent = filteredRows.length ? String(firstIndex + 1) : '0';
        if (rangeEnd) rangeEnd.textContent = String(Math.min(firstIndex + pageSize, filteredRows.length));
        if (pageIndicator) pageIndicator.textContent = `${currentPage} / ${pageCount}`;
        if (previousPage) previousPage.disabled = currentPage === 1;
        if (nextPage) nextPage.disabled = currentPage === pageCount;
        if (pagination) pagination.hidden = pageCount <= 1;
    };

    searchInput?.addEventListener('input', () => applyFilters(true));
    previousPage?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            applyFilters();
        }
    });
    nextPage?.addEventListener('click', () => {
        currentPage += 1;
        applyFilters();
    });

    applyFilters();
};

const setupPersonPreview = () => {
    const select = document.querySelector('[data-person-select]');
    const preview = document.querySelector('[data-person-preview]');
    if (!select || !preview) return;

    const fields = {
        name: preview.querySelector('[data-person-preview-name]'),
        cedula: preview.querySelector('[data-person-preview-cedula]'),
        role: preview.querySelector('[data-person-preview-role]'),
        phone: preview.querySelector('[data-person-preview-phone]'),
        email: preview.querySelector('[data-person-preview-email]'),
        status: preview.querySelector('[data-person-preview-status]'),
    };

    const updatePreview = () => {
        const option = select.selectedOptions[0];
        if (!option?.value) {
            preview.hidden = true;
            return;
        }

        Object.entries(fields).forEach(([field, element]) => {
            if (element) element.textContent = option.dataset[`person${field[0].toUpperCase()}${field.slice(1)}`] || 'No registrado';
        });
        preview.hidden = false;
    };

    select.addEventListener('change', updatePreview);
    updatePreview();
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

const setupPersonFieldRestrictions = () => {
    const nameInputs = document.querySelectorAll('[data-name-only]');
    const phoneInput = document.querySelector('[data-numeric-only]');

    const removeMatchingCharacters = (input, pattern) => {
        input.addEventListener('input', () => {
            const cursor = input.selectionStart ?? input.value.length;
            const removedBeforeCursor = input.value.slice(0, cursor).match(pattern)?.join('').length ?? 0;
            const sanitizedValue = input.value.replace(pattern, '');

            if (sanitizedValue === input.value) return;

            input.value = sanitizedValue;
            const nextCursor = cursor - removedBeforeCursor;
            input.setSelectionRange(nextCursor, nextCursor);
        });
    };

    nameInputs.forEach((input) => removeMatchingCharacters(input, /[0-9]/g));
    if (phoneInput) removeMatchingCharacters(phoneInput, /[^0-9]/g);
};

const setupDateFieldRestrictions = () => {
    const startDate = document.querySelector('[data-date-field="date"]');
    const endDate = document.querySelector('[data-date-field="end_date"]');

    if (!startDate || !endDate) return;

    const updateEndDateMinimum = () => {
        endDate.min = startDate.value > startDate.min ? startDate.value : startDate.min;
    };

    startDate.addEventListener('change', updateEndDateMinimum);
    updateEndDateMinimum();
};

const setupTerritorySelectors = () => {
    const stateSelect = document.querySelector('[data-territory-state]');
    const municipalitySelect = document.querySelector('[data-territory-municipality]');
    const parishSelect = document.querySelector('[data-territory-parish]');
    const catalogElement = document.querySelector('[data-territory-catalog]');
    const emptyMessage = document.querySelector('[data-territory-empty-message]');

    if (!stateSelect || !municipalitySelect || !parishSelect || !catalogElement) return;

    const catalog = JSON.parse(catalogElement.textContent);

    const replaceOptions = (select, values, placeholder) => {
        select.replaceChildren(new Option(placeholder, ''));
        values.forEach((value) => select.add(new Option(value, value)));
    };

    const updateParishes = (selectedParish = '') => {
        const parishes = catalog[stateSelect.value]?.[municipalitySelect.value] || [];
        replaceOptions(parishSelect, parishes, parishes.length ? 'Selecciona una parroquia' : 'Sin parroquias disponibles');
        parishSelect.value = selectedParish;
        parishSelect.disabled = parishes.length === 0;
        parishSelect.required = parishes.length > 0;
        if (emptyMessage) emptyMessage.hidden = parishes.length > 0 || !municipalitySelect.value;
    };

    const updateMunicipalities = (selectedMunicipality = '') => {
        const municipalities = Object.keys(catalog[stateSelect.value] || {});
        replaceOptions(municipalitySelect, municipalities, 'Selecciona un municipio');
        municipalitySelect.value = selectedMunicipality;
        municipalitySelect.disabled = municipalities.length === 0;
        updateParishes();
    };

    stateSelect.addEventListener('change', () => updateMunicipalities());
    municipalitySelect.addEventListener('change', () => updateParishes());
    updateParishes(parishSelect.value);
};

const setupLiveClock = () => {
    const clock = document.querySelector('[data-live-clock]');
    if (!clock) return;

    const updateClock = () => {
        const now = new Date();
        clock.querySelector('span').textContent = new Intl.DateTimeFormat('es-VE', {
            timeZone: 'America/Caracas',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).format(now);
    };

    updateClock();
    window.setInterval(updateClock, 1000);
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
    setupPersonPreview();
    setupPersonFieldRestrictions();
    setupDateFieldRestrictions();
    setupTerritorySelectors();
    setupLiveClock();
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
