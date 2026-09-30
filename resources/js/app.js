import './bootstrap';

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

const setupModuleTable = () => {
    const table = document.querySelector('[data-table-role="module-table"]');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const searchInput = document.querySelector('.search-box input');
    const recordCount = document.querySelector('[data-record-count]');
    const filterButton = document.querySelector('[data-action="filter-toggle"]');
    const exportButton = document.querySelector('[data-action="export"]');
    const filterOptions = ['todos', 'activos', 'completos', 'pendientes'];
    let currentFilter = 'todos';

    const getRowText = (row) => row.textContent.replace(/\s+/g, ' ').trim().toLowerCase();

    const applyFilters = () => {
        let visibleCount = 0;

        rows.forEach((row) => {
            const text = getRowText(row);
            const matchesSearch = !searchInput || !searchInput.value.trim() || text.includes(searchInput.value.trim().toLowerCase());
            const matchesFilter =
                currentFilter === 'todos' ||
                (currentFilter === 'activos' && (row.innerHTML.includes('tag-green') || row.innerHTML.includes('status-pill'))) ||
                (currentFilter === 'completos' && row.innerHTML.includes('tag-green')) ||
                (currentFilter === 'pendientes' && (row.innerHTML.includes('tag-red') || row.innerHTML.includes('tag-yellow') || row.innerHTML.includes('warning')));

            const shouldDisplay = matchesSearch && matchesFilter;
            row.style.display = shouldDisplay ? '' : 'none';
            if (shouldDisplay) visibleCount += 1;
        });

        if (recordCount) {
            recordCount.textContent = String(visibleCount);
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (filterButton) {
        const menu = document.createElement('div');
        menu.className = 'filter-menu';
        menu.innerHTML = filterOptions.map((option) => `
            <button type="button" class="filter-option ${option === currentFilter ? 'active' : ''}" data-filter-value="${option}">
                ${option.charAt(0).toUpperCase() + option.slice(1)}
            </button>
        `).join('');

        filterButton.parentNode.appendChild(menu);

        menu.addEventListener('click', (event) => {
            const option = event.target.closest('[data-filter-value]');
            if (!option) return;

            currentFilter = option.dataset.filterValue;
            menu.querySelectorAll('.filter-option').forEach((item) => {
                item.classList.toggle('active', item === option);
            });

            const label = filterButton.querySelector('span') || document.createElement('span');
            label.textContent = currentFilter === 'todos' ? 'Filtrar' : currentFilter.charAt(0).toUpperCase() + currentFilter.slice(1);
            filterButton.insertBefore(label, filterButton.firstChild);
            applyFilters();
            showToast(`Filtro aplicado: ${currentFilter}`, 'success');
        });

        filterButton.addEventListener('click', () => {
            menu.classList.toggle('open');
        });
    }

    if (exportButton) {
        exportButton.addEventListener('click', () => {
            const visibleRows = rows.filter((row) => row.style.display !== 'none');
            const headers = Array.from(table.querySelectorAll('thead th')).slice(0, -1).map((cell) => cell.textContent.trim());
            const csv = [headers.join(',')].concat(
                visibleRows.map((row) => Array.from(row.querySelectorAll('td')).slice(0, -1).map((cell) => `"${cell.textContent.replace(/\s+/g, ' ').trim()}"`).join(','))
            ).join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'registros.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            showToast('Archivo exportado con éxito', 'success');
        });
    }

    rows.forEach((row) => {
        const menuButton = row.querySelector('[data-action="row-menu"]');
        if (!menuButton) return;

        const contextMenu = document.createElement('div');
        contextMenu.className = 'context-menu';
        contextMenu.innerHTML = `
            <button type="button" data-menu-action="view">Ver detalle</button>
            <button type="button" data-menu-action="edit">Editar</button>
            <button type="button" data-menu-action="delete">Eliminar</button>
        `;
        row.appendChild(contextMenu);

        menuButton.addEventListener('click', (event) => {
            event.stopPropagation();
            document.querySelectorAll('.context-menu').forEach((menu) => menu.classList.remove('open'));
            contextMenu.classList.toggle('open');
        });

        contextMenu.addEventListener('click', (event) => {
            const action = event.target.closest('[data-menu-action]');
            if (!action) return;

            const label = action.dataset.menuAction;
            showToast(`Acción: ${label}`, 'info');
            contextMenu.classList.remove('open');
        });
    });

    applyFilters();
};

const setupDashboardActions = () => {
    document.querySelectorAll('.icon-button, .more-button, .coverage-link, .alert-list a, .text-link').forEach((element) => {
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
            }
        });
    });

    setupDashboardActions();
    setupModuleTable();

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.filter-menu') && !event.target.closest('[data-action="filter-toggle"]')) {
            document.querySelectorAll('.filter-menu').forEach((menu) => menu.classList.remove('open'));
        }

        if (!event.target.closest('.context-menu') && !event.target.closest('[data-action="row-menu"]')) {
            document.querySelectorAll('.context-menu').forEach((menu) => menu.classList.remove('open'));
        }
    });
});

