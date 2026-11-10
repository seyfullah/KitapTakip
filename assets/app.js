// Tüm .sortable-table tablolarını başlığa tıklayarak sıralanabilir yapar (toplam satırı sabit kalır).
(() => {
  const parse = text => {
    const t = text.replace(/\s*(TL|gün)\s*$/i, '').trim();
    if (/^\d{2}\.\d{2}\.\d{4}/.test(t)) return {n: +t.slice(6, 10) * 1e4 + +t.slice(3, 5) * 100 + +t.slice(0, 2)};
    if (/^-?[\d.]+(,\d+)?$/.test(t)) return {n: parseFloat(t.replace(/\./g, '').replace(',', '.'))};
    if (/^#\d+$/.test(t)) return {n: +t.slice(1)};
    return {s: t};
  };
  const collator = new Intl.Collator('tr', {numeric: true, sensitivity: 'base'});

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-stock-quantity]').forEach(form => {
      const units = form.querySelector('[data-stock-units]');
      const cartons = form.querySelector('[data-stock-cartons]');
      const mode = form.querySelector('[name="miktar_tipi"]');
      const book = form.querySelector('[data-carton-book]');
      const label = form.querySelector('[data-carton-label]');
      const cartonSize = () => Number(book.selectedOptions[0]?.dataset.cartonSize || 0);
      const syncCartonLabel = () => {
        label.textContent = cartonSize() || '—';
        if (mode.value === 'koli') units.value = Math.round((Number(cartons.value) || 0) * cartonSize()) || '';
      };

      units.addEventListener('input', () => {
        if (units.value) {
          cartons.value = '';
          units.readOnly = false;
          mode.value = 'adet';
        } else mode.value = '';
      });
      cartons.addEventListener('input', () => {
        if (cartons.value) {
          units.value = Math.round((Number(cartons.value) || 0) * cartonSize()) || '';
          units.readOnly = true;
          mode.value = 'koli';
        } else {
          units.value = '';
          units.readOnly = false;
          mode.value = '';
        }
      });
      book.addEventListener('change', syncCartonLabel);
      syncCartonLabel();
    });

    document.querySelectorAll('table.sortable-table').forEach(table => {
      const body = table.tBodies[0];
      table.querySelectorAll('thead .sort-button').forEach((btn, col) => {
        btn.addEventListener('click', () => {
          const dir = btn.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
          table.querySelectorAll('.sort-button').forEach(b => { b.removeAttribute('aria-sort'); b.lastElementChild.textContent = '↕'; });
          btn.setAttribute('aria-sort', dir);
          btn.lastElementChild.textContent = dir === 'ascending' ? '↑' : '↓';
          const all = [...body.rows];
          const total = all.filter(r => r.classList.contains('tot'));
          const rows = all.filter(r => !r.classList.contains('tot') && r.cells.length > 1);
          const sign = dir === 'ascending' ? 1 : -1;
          rows.sort((a, b) => {
            const x = parse(a.cells[col].textContent), y = parse(b.cells[col].textContent);
            if (x.n !== undefined && y.n !== undefined) return (x.n - y.n) * sign;
            return collator.compare(a.cells[col].textContent.trim(), b.cells[col].textContent.trim()) * sign;
          });
          rows.forEach(r => body.appendChild(r));
          total.forEach(r => body.appendChild(r));
        });
      });
    });
  });
})();
