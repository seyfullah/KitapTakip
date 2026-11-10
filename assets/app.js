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
    const menus = [...document.querySelectorAll('.nav-menu')];
    menus.forEach(menu => menu.addEventListener('toggle', () => {
      if (menu.open) menus.forEach(other => { if (other !== menu) other.open = false; });
    }));
    document.addEventListener('click', event => {
      if (!event.target.closest('nav')) menus.forEach(menu => { menu.open = false; });
    });
    document.addEventListener('keydown', event => {
      if (event.key !== 'Escape') return;
      const open = menus.find(menu => menu.open);
      if (open) { open.open = false; open.querySelector('summary').focus(); }
    });
    const normalize = text => text.toLocaleLowerCase('tr-TR').trim();
    document.querySelectorAll('.person-select').forEach(select => {
      const label = document.createElement('label');
      label.textContent = 'Kişi listesinde ara';
      const input = document.createElement('input');
      input.type = 'search';
      label.appendChild(input);
      select.parentElement.before(label);
      input.addEventListener('input', () => {
        const query = normalize(input.value);
        [...select.options].forEach(option => {
          option.hidden = option.value !== '' && !normalize(option.textContent).includes(query);
        });
        if (select.selectedOptions[0]?.hidden) select.value = '';
      });
    });
    document.querySelectorAll('table.sortable-table').forEach(table => {
      const body = table.tBodies[0];
      const filter = table.closest('.table-wrap').previousElementSibling.querySelector('input');
      filter.addEventListener('input', () => {
        const query = normalize(filter.value);
        [...body.rows].forEach(row => {
          if (row.classList.contains('tot')) row.hidden = query !== '';
          else if (row.cells.length > 1) row.hidden = !normalize(row.textContent).includes(query);
        });
      });
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
