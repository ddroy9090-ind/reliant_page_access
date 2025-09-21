(() => {
  let tooltipEl = null;
  let activeCell = null;
  let lastPointerEvent = null;

  const HIDDEN_POSITION = 'translate(-9999px, -9999px)';

  function ensureTooltip() {
    if (tooltipEl) {
      return tooltipEl;
    }
    tooltipEl = document.createElement('div');
    tooltipEl.className = 'table-tooltip-popup';
    tooltipEl.setAttribute('role', 'tooltip');
    tooltipEl.setAttribute('aria-hidden', 'true');
    tooltipEl.style.left = '0';
    tooltipEl.style.top = '0';
    tooltipEl.style.transform = HIDDEN_POSITION;
    document.body.appendChild(tooltipEl);
    return tooltipEl;
  }

  function getCellText(cell) {
    const explicit = cell.getAttribute('data-tooltip') || cell.getAttribute('data-full-text');
    if (explicit && explicit.trim() !== '') {
      return explicit.trim();
    }
    const titleAttr = cell.getAttribute('title');
    if (titleAttr && titleAttr.trim() !== '') {
      return titleAttr.trim();
    }
    return cell.innerText.replace(/\s+/g, ' ').trim();
  }

  function removeNativeTitle(cell) {
    if (cell.hasAttribute('title')) {
      cell.dataset.tableTooltipTitle = cell.getAttribute('title') || '';
      cell.removeAttribute('title');
    }
  }

  function restoreNativeTitle(cell) {
    if (cell && cell.dataset && Object.prototype.hasOwnProperty.call(cell.dataset, 'tableTooltipTitle')) {
      const value = cell.dataset.tableTooltipTitle;
      delete cell.dataset.tableTooltipTitle;
      if (value !== undefined) {
        cell.setAttribute('title', value);
      }
    }
  }

  function hideTooltip() {
    if (!tooltipEl) {
      return;
    }
    tooltipEl.classList.remove('is-visible');
    tooltipEl.textContent = '';
    tooltipEl.style.transform = HIDDEN_POSITION;
    tooltipEl.setAttribute('aria-hidden', 'true');
    if (activeCell) {
      restoreNativeTitle(activeCell);
    }
    activeCell = null;
    lastPointerEvent = null;
  }

  function positionTooltip(clientX, clientY) {
    if (!tooltipEl || !activeCell) {
      return;
    }

    const padding = 12;
    let x;
    let y;

    if (typeof clientX === 'number' && typeof clientY === 'number') {
      x = clientX + 12;
      y = clientY + 12;
    } else {
      const rect = activeCell.getBoundingClientRect();
      x = rect.left + rect.width / 2;
      y = rect.bottom + 8;
    }

    tooltipEl.style.transform = 'translate(0px, 0px)';
    tooltipEl.style.maxWidth = Math.min(480, window.innerWidth - padding * 2) + 'px';
    const rect = tooltipEl.getBoundingClientRect();

    if (x + rect.width + padding > window.innerWidth) {
      x = window.innerWidth - rect.width - padding;
    }
    if (x < padding) {
      x = padding;
    }

    if (y + rect.height + padding > window.innerHeight) {
      y = y - rect.height - 24;
      if (y < padding) {
        y = Math.max(padding, window.innerHeight - rect.height - padding);
      }
    }

    tooltipEl.style.transform = `translate(${Math.round(x)}px, ${Math.round(y)}px)`;
  }

  function showTooltip(cell, sourceEvent) {
    const tooltip = ensureTooltip();
    const text = getCellText(cell);

    if (!text) {
      hideTooltip();
      return;
    }

    activeCell = cell;
    removeNativeTitle(cell);

    tooltip.textContent = text;
    tooltip.setAttribute('aria-hidden', 'false');
    tooltip.classList.add('is-visible');

    if (sourceEvent && typeof sourceEvent.clientX === 'number' && typeof sourceEvent.clientY === 'number') {
      lastPointerEvent = sourceEvent;
      positionTooltip(sourceEvent.clientX, sourceEvent.clientY);
    } else {
      lastPointerEvent = null;
      positionTooltip();
    }
  }

  function handlePointerEnter(event) {
    const cell = event.target.closest('td');
    if (!cell || !cell.isConnected) {
      return;
    }
    showTooltip(cell, event);
  }

  function handlePointerLeave(event) {
    if (!activeCell) {
      return;
    }
    const cell = event.target.closest('td');
    if (!cell || cell !== activeCell) {
      return;
    }
    const related = event.relatedTarget;
    if (related && cell.contains(related)) {
      return;
    }
    hideTooltip();
  }

  function handlePointerMove(event) {
    if (!activeCell || !tooltipEl || !tooltipEl.classList.contains('is-visible')) {
      return;
    }
    lastPointerEvent = event;
    positionTooltip(event.clientX, event.clientY);
  }

  function handleFocusIn(event) {
    const cell = event.target.closest('td');
    if (!cell || !cell.isConnected) {
      return;
    }
    showTooltip(cell, null);
  }

  function handleFocusOut(event) {
    if (!activeCell) {
      return;
    }
    const cell = event.target.closest('td');
    if (cell && cell === activeCell) {
      hideTooltip();
    }
  }

  function repositionTooltip() {
    if (!activeCell || !tooltipEl || !tooltipEl.classList.contains('is-visible')) {
      return;
    }
    if (lastPointerEvent) {
      positionTooltip(lastPointerEvent.clientX, lastPointerEvent.clientY);
    } else {
      positionTooltip();
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    ensureTooltip();
    document.addEventListener('pointerenter', handlePointerEnter, true);
    document.addEventListener('pointerleave', handlePointerLeave, true);
    document.addEventListener('pointermove', handlePointerMove, true);
    document.addEventListener('focusin', handleFocusIn, true);
    document.addEventListener('focusout', handleFocusOut, true);
    window.addEventListener('scroll', repositionTooltip, true);
    window.addEventListener('resize', repositionTooltip);
    document.addEventListener(
      'keydown',
      (event) => {
        if (event.key === 'Escape') {
          hideTooltip();
        }
      },
      true
    );
  });
})();
