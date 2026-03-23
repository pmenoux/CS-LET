/**
 * admin.js — CS-LET Admin
 * Gestion du calendrier nuisances : clic sur un jour, sélection picto,
 * sauvegarde AJAX, mode bulk, navigation mois.
 */

'use strict';

// ── Variables globales injectées par PHP ──────────────────────────────────
// CSRF_TOKEN, API_URL, CURRENT_YEAR, CURRENT_MONTH  (définis dans calendrier.php)

// ── État local ────────────────────────────────────────────────────────────
const state = {
    activeDate:       null,   // date YYYY-MM-DD en cours d'édition
    selectedPicto:    null,   // picto sélectionné dans la modal
    bulkPicto:        null,   // picto sélectionné pour le mode bulk
    bulkMode:         false,
    loading:          false,
};

// ── Utilitaires ───────────────────────────────────────────────────────────

function formatDateFR(isoDate) {
    if (!isoDate) return '';
    const [y, m, d] = isoDate.split('-');
    const months = [
        '', 'janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre',
    ];
    return `${parseInt(d, 10)} ${months[parseInt(m, 10)]} ${y}`;
}

function showNotif(message, type = 'success') {
    const bar = document.getElementById('notif-bar');
    if (!bar) return;
    bar.className = `notif-bar notif-${type}`;
    bar.textContent = message;
    bar.style.display = 'flex';
    if (type !== 'loading') {
        clearTimeout(bar._hideTimer);
        bar._hideTimer = setTimeout(() => {
            bar.style.display = 'none';
        }, 4000);
    }
}

function hideNotif() {
    const bar = document.getElementById('notif-bar');
    if (bar) bar.style.display = 'none';
}

/**
 * Requête AJAX JSON vers api.php.
 * @param {string} action
 * @param {Object} payload  données POST
 * @returns {Promise<Object>}
 */
async function apiPost(action, payload = {}) {
    const body = { action, csrf_token: CSRF_TOKEN, ...payload };
    const resp = await fetch(API_URL, {
        method:  'POST',
        headers: {
            'Content-Type':    'application/json',
            'X-CSRF-Token':    CSRF_TOKEN,
            'X-Requested-With':'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    if (!resp.ok) {
        throw new Error(`HTTP ${resp.status}`);
    }
    return resp.json();
}

// ── Mise à jour visuelle d'une cellule ───────────────────────────────────

function updateCell(dateStr, picto) {
    const cell = document.querySelector(`.cal-cell-day[data-date="${dateStr}"]`);
    if (!cell) return;

    cell.dataset.picto = picto || '';

    // Supprimer l'ancienne image et le label
    cell.querySelectorAll('.cal-picto-thumb, .cal-picto-label').forEach(el => el.remove());

    if (picto) {
        cell.classList.add('has-entry');
        const img = document.createElement('img');
        img.src     = `../assets/img/${picto}`;
        img.alt     = picto.replace('.png', '').replace(/_/g, ' ');
        img.className = 'cal-picto-thumb';
        img.loading = 'lazy';
        cell.appendChild(img);

        const label = document.createElement('span');
        label.className = 'cal-picto-label';
        label.textContent = img.alt;
        cell.appendChild(label);
    } else {
        cell.classList.remove('has-entry');
    }
}

function flashCell(dateStr, type) {
    const cell = document.querySelector(`.cal-cell-day[data-date="${dateStr}"]`);
    if (!cell) return;
    cell.classList.add(type === 'success' ? 'save-success' : 'save-error');
    setTimeout(() => {
        cell.classList.remove('save-success', 'save-error');
    }, 1200);
}

// ── Modal ─────────────────────────────────────────────────────────────────

const modal = {
    overlay:  null,
    dateDisp: null,
    dateVal:  null,
    saveBtn:  null,
    delBtn:   null,

    init() {
        this.overlay  = document.getElementById('modal-overlay');
        this.dateDisp = document.getElementById('modal-date-display');
        this.dateVal  = document.getElementById('modal-date-value');
        this.saveBtn  = document.getElementById('modal-save');
        this.delBtn   = document.getElementById('modal-delete');

        if (!this.overlay) return;

        document.getElementById('modal-close')?.addEventListener('click', () => this.close());
        document.getElementById('modal-cancel')?.addEventListener('click', () => this.close());

        this.overlay.addEventListener('click', e => {
            if (e.target === this.overlay) this.close();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && this.overlay.style.display !== 'none') {
                this.close();
            }
        });

        this.saveBtn?.addEventListener('click', () => this.save());
        this.delBtn?.addEventListener('click',  () => this.deleteEntry());

        // Pictos dans la modal
        document.querySelectorAll('#modal-picto-grid .picto-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#modal-picto-grid .picto-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                state.selectedPicto = btn.dataset.picto;
                this.saveBtn.disabled = false;
            });
        });
    },

    open(dateStr, currentPicto) {
        state.activeDate    = dateStr;
        state.selectedPicto = currentPicto || null;

        this.dateDisp.textContent = formatDateFR(dateStr);
        this.dateVal.value        = dateStr;
        this.saveBtn.disabled     = true;

        // Pré-sélectionner le picto courant
        document.querySelectorAll('#modal-picto-grid .picto-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.dataset.picto === currentPicto);
        });

        if (currentPicto) {
            state.selectedPicto = currentPicto;
            this.saveBtn.disabled = false;
        }

        // Bouton supprimer : affiché seulement si une entrée existe
        if (this.delBtn) {
            this.delBtn.style.display = currentPicto ? 'inline-flex' : 'none';
        }

        this.overlay.style.display = 'flex';
        this.overlay.querySelector('.modal-box')?.focus?.();
    },

    close() {
        this.overlay.style.display = 'none';
        state.activeDate    = null;
        state.selectedPicto = null;
    },

    async save() {
        if (!state.activeDate || !state.selectedPicto) return;
        if (state.loading) return;

        state.loading = true;
        this.saveBtn.disabled = true;
        this.saveBtn.innerHTML = '<span class="spinner"></span> Enregistrement…';

        try {
            const result = await apiPost('set_nuisance', {
                date:  state.activeDate,
                picto: state.selectedPicto,
            });

            if (result.success) {
                updateCell(state.activeDate, state.selectedPicto);
                flashCell(state.activeDate, 'success');
                showNotif('Picto enregistre pour le ' + formatDateFR(state.activeDate));
                this.close();
            } else {
                showNotif(result.error || 'Erreur lors de l\'enregistrement.', 'error');
            }
        } catch (err) {
            showNotif('Erreur reseau. Veuillez reessayer.', 'error');
            console.error('[CS-LET] api error:', err);
        } finally {
            state.loading = false;
            this.saveBtn.disabled = false;
            this.saveBtn.innerHTML = 'Enregistrer';
        }
    },

    async deleteEntry() {
        if (!state.activeDate) return;
        if (!confirm(`Supprimer l'entree du ${formatDateFR(state.activeDate)} ?`)) return;
        if (state.loading) return;

        state.loading = true;
        this.delBtn.disabled = true;
        this.delBtn.innerHTML = '<span class="spinner"></span>';

        try {
            const result = await apiPost('delete_nuisance', { date: state.activeDate });

            if (result.success) {
                updateCell(state.activeDate, null);
                flashCell(state.activeDate, 'success');
                showNotif('Entree supprimee pour le ' + formatDateFR(state.activeDate));
                this.close();
            } else {
                showNotif(result.error || 'Erreur lors de la suppression.', 'error');
            }
        } catch (err) {
            showNotif('Erreur reseau. Veuillez reessayer.', 'error');
            console.error('[CS-LET] api error:', err);
        } finally {
            state.loading = false;
            this.delBtn.disabled = false;
            this.delBtn.innerHTML = 'Supprimer l\'entree';
        }
    },
};

// ── Clic sur une cellule de calendrier ───────────────────────────────────

function initCalendarCells() {
    const grid = document.getElementById('cal-grid');
    if (!grid) return;

    grid.addEventListener('click', e => {
        const cell = e.target.closest('.cal-cell-day');
        if (!cell) return;
        const dateStr     = cell.dataset.date;
        const currentPicto = cell.dataset.picto || null;
        modal.open(dateStr, currentPicto);
    });

    grid.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            const cell = e.target.closest('.cal-cell-day');
            if (!cell) return;
            e.preventDefault();
            const dateStr      = cell.dataset.date;
            const currentPicto = cell.dataset.picto || null;
            modal.open(dateStr, currentPicto);
        }
    });
}

// ── Mode bulk ────────────────────────────────────────────────────────────

function initBulkMode() {
    const toggleBtn   = document.getElementById('bulk-toggle');
    const bulkPanel   = document.getElementById('bulk-panel');
    const applyBtn    = document.getElementById('bulk-apply');
    const cancelBtn   = document.getElementById('bulk-cancel');
    const startInput  = document.getElementById('bulk-start');
    const endInput    = document.getElementById('bulk-end');
    const hiddenPicto = document.getElementById('bulk-selected-picto');

    if (!toggleBtn || !bulkPanel) return;

    function checkBulkReady() {
        const ready = state.bulkPicto && startInput.value && endInput.value;
        if (applyBtn) applyBtn.disabled = !ready;
    }

    toggleBtn.addEventListener('click', () => {
        state.bulkMode = !state.bulkMode;
        bulkPanel.style.display = state.bulkMode ? 'block' : 'none';
        toggleBtn.textContent = state.bulkMode ? 'Masquer la selection multiple' : 'Mode selection multiple';
    });

    cancelBtn?.addEventListener('click', () => {
        state.bulkMode = false;
        state.bulkPicto = null;
        bulkPanel.style.display = 'none';
        toggleBtn.textContent = 'Mode selection multiple';
        document.querySelectorAll('#bulk-picto-grid .picto-btn').forEach(b => b.classList.remove('selected'));
        if (hiddenPicto) hiddenPicto.value = '';
    });

    // Sélection picto bulk
    document.querySelectorAll('#bulk-picto-grid .picto-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#bulk-picto-grid .picto-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            state.bulkPicto = btn.dataset.picto;
            if (hiddenPicto) hiddenPicto.value = state.bulkPicto;
            checkBulkReady();
        });
    });

    startInput?.addEventListener('change', checkBulkReady);
    endInput?.addEventListener('change', checkBulkReady);

    applyBtn?.addEventListener('click', async () => {
        if (!state.bulkPicto || !startInput.value || !endInput.value) return;
        if (state.loading) return;

        const start = startInput.value;
        const end   = endInput.value;

        if (end < start) {
            showNotif('La date de fin doit etre superieure ou egale a la date de debut.', 'error');
            return;
        }

        const confirmed = confirm(
            `Appliquer le picto "${state.bulkPicto.replace('.png','').replace(/_/g,' ')}" du ${formatDateFR(start)} au ${formatDateFR(end)} ?`
        );
        if (!confirmed) return;

        state.loading = true;
        applyBtn.disabled = true;
        applyBtn.innerHTML = '<span class="spinner"></span> Application…';
        showNotif('Application en cours…', 'loading');

        try {
            const result = await apiPost('bulk_nuisance', {
                start_date: start,
                end_date:   end,
                picto:      state.bulkPicto,
            });

            if (result.success) {
                // Mettre à jour toutes les cellules de la plage
                const current = new Date(start + 'T00:00:00');
                const last    = new Date(end   + 'T00:00:00');
                while (current <= last) {
                    const ds = current.toISOString().slice(0, 10);
                    updateCell(ds, state.bulkPicto);
                    current.setDate(current.getDate() + 1);
                }
                showNotif(result.message || `${result.count} entrees enregistrees.`, 'success');
                // Reset
                startInput.value = '';
                endInput.value   = '';
                state.bulkPicto  = null;
                if (hiddenPicto) hiddenPicto.value = '';
                document.querySelectorAll('#bulk-picto-grid .picto-btn').forEach(b => b.classList.remove('selected'));
            } else {
                showNotif(result.error || 'Erreur lors de l\'application.', 'error');
            }
        } catch (err) {
            showNotif('Erreur reseau. Veuillez reessayer.', 'error');
            console.error('[CS-LET] bulk api error:', err);
        } finally {
            state.loading = false;
            applyBtn.disabled = false;
            applyBtn.innerHTML = 'Appliquer a la plage';
            checkBulkReady();
        }
    });
}

// ── Initialisation ────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    modal.init();
    initCalendarCells();
    initBulkMode();
});
