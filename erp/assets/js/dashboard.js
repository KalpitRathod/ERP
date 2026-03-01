// ================================================================
//  ERP Dashboard Widget System – dashboard.js
//  Fetches widget data from /erp/api/widgets.php,
//  renders tiles on the page, supports drag-to-reorder + save.
// ================================================================

const ERP_API = (typeof ERP_BASE_URL !== 'undefined') ? ERP_BASE_URL + '/api/widgets.php' : '/erp/api/widgets.php';

// ---- Fetch a single widget value and update its tile -----------
async function fetchWidget(code, tileEl) {
    try {
        const res  = await fetch(ERP_API + '?w=' + encodeURIComponent(code));
        const data = await res.json();
        if (data.error) {
            tileEl.querySelector('.kv').textContent = 'Err';
            return;
        }
        const kv = tileEl.querySelector('.kv');
        const kl = tileEl.querySelector('.kl');
        if (kv) kv.textContent = data.value ?? '—';
        if (kl) kl.textContent = data.label ?? '';
        if (data.alert) tileEl.classList.add('red');

        // Render list data if present (e.g. cinema shows)
        if (data.data && data.data.length) {
            let extra = tileEl.querySelector('.widget-data');
            if (!extra) { extra = document.createElement('div'); extra.className = 'widget-data'; tileEl.appendChild(extra); }
            extra.innerHTML = data.data.slice(0, 5).map(function(r) {
                return '<div style="font-size:11px;margin-top:3px;">' + JSON.stringify(r) + '</div>';
            }).join('');
        }
    } catch (e) {
        console.warn('Widget fetch failed:', code, e);
    }
}

// ---- Load and render the user's saved dashboard layout ---------
async function loadDashboard(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    const res    = await fetch(ERP_API + '?w=__layout');
    const data   = await res.json();
    const layout = data.layout;

    if (!layout || !layout.length) {
        // No saved layout – render default tiles from data-default-widgets attr
        const defaults = (container.dataset.defaultWidgets || '').split(',').filter(Boolean);
        defaults.forEach(function(code) {
            container.appendChild(createWidgetTile(code));
        });
    } else {
        // Sort by saved position
        layout.sort(function(a, b) { return (a.position||0) - (b.position||0); });
        layout.forEach(function(item) {
            container.appendChild(createWidgetTile(item.code, item));
        });
    }

    // Fetch live data for all rendered tiles
    container.querySelectorAll('.kpi-card[data-widget]').forEach(function(tile) {
        fetchWidget(tile.dataset.widget, tile);
    });

    // Attach drag-and-drop
    enableDragDrop(container);
}

// ---- Build a widget tile DOM element ---------------------------
function createWidgetTile(code, cfg) {
    cfg = cfg || {};
    const tile = document.createElement('div');
    tile.className = 'kpi-card';
    tile.dataset.widget = code;
    tile.setAttribute('draggable', 'true');
    tile.innerHTML = [
        '<span class="kl" style="font-size:10px;color:#aaa;float:right;cursor:grab" title="Drag to reorder">&#8597;</span>',
        '<span class="kv">...</span>',
        '<span class="kl">Loading...</span>',
    ].join('');
    // Apply saved size
    if (cfg.size === '2x1') tile.style.gridColumn = 'span 2';
    return tile;
}

// ---- Drag-and-drop reorder + persist ---------------------------
function enableDragDrop(container) {
    let dragging = null;

    container.addEventListener('dragstart', function(e) {
        dragging = e.target.closest('.kpi-card');
        if (dragging) { dragging.style.opacity = '0.5'; }
    });

    container.addEventListener('dragend', function() {
        if (dragging) dragging.style.opacity = '';
        dragging = null;
        saveLayout(container);
    });

    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        const target = e.target.closest('.kpi-card');
        if (target && target !== dragging) {
            const rect   = target.getBoundingClientRect();
            const after  = e.clientX > rect.left + rect.width / 2;
            if (after) container.insertBefore(dragging, target.nextSibling);
            else       container.insertBefore(dragging, target);
        }
    });
}

// ---- Persist current tile order to API -------------------------
async function saveLayout(container) {
    const tiles  = container.querySelectorAll('.kpi-card[data-widget]');
    const layout = Array.from(tiles).map(function(t, i) {
        return { code: t.dataset.widget, position: i };
    });
    await fetch(ERP_API + '?w=__save_layout', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(layout),
    });
}

// ---- Add/Remove widget (called from widget picker modal) -------
window.ERP = window.ERP || {};
window.ERP.addWidget = function(code, containerSel) {
    const container = document.querySelector(containerSel || '#widget-grid');
    if (!container) return;
    const tile = createWidgetTile(code);
    container.appendChild(tile);
    fetchWidget(code, tile);
    saveLayout(container);
};

window.ERP.removeWidget = function(code, containerSel) {
    const container = document.querySelector(containerSel || '#widget-grid');
    if (!container) return;
    const tile = container.querySelector('[data-widget="' + code + '"]');
    if (tile) { tile.remove(); saveLayout(container); }
};

// ---- Auto-init on pages with #widget-grid ----------------------
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('widget-grid')) {
        loadDashboard('#widget-grid');
    }
});
