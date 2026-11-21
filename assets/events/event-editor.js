(() => {
  function updateChampionshipVisibility() {
    const series = document.getElementById('bckfc-series');
    const championship = document.querySelector('[data-bckfc-championship]');

    if (series && championship) {
      championship.hidden = series.value === '0';

      if (championship.hidden) {
        championship.querySelector('input').checked = false;
      }
    }
  }

  document.addEventListener('change', (event) => {
    if (event.target.id === 'bckfc-series') {
      updateChampionshipVisibility();
    }
  });

  updateChampionshipVisibility();

  document.querySelectorAll('[data-bckfc-size-limits]').forEach((editor) => {
    const rows = editor.querySelector('tbody');
    const template = editor.querySelector('template');
    let nextIndex = rows.children.length;

    editor.querySelector('[data-bckfc-add-size-limit]').addEventListener('click', () => {
      const row = template.content.cloneNode(true);

      row.querySelectorAll('input').forEach((input) => {
        input.name = input.name.replace('__index__', nextIndex);
      });

      nextIndex += 1;
      rows.append(row);
      rows.lastElementChild.querySelector('input').focus();
    });

    rows.addEventListener('click', (event) => {
      if (event.target.closest('[data-bckfc-remove-size-limit]')) {
        event.target.closest('tr').remove();
        editor.querySelector('[data-bckfc-add-size-limit]').focus();
      }
    });
  });

  document.querySelectorAll('[data-bckfc-time-tbd]').forEach((checkbox) => {
    const input = document.getElementById(checkbox.dataset.bckfcTimeTbd);

    checkbox.addEventListener('change', () => {
      input.disabled = checkbox.checked;
    });
  });

  document.querySelectorAll('.bckfc-event-editor').forEach((panel) => {
    const tabs = [...panel.querySelectorAll('[role="tab"]')];

    function selectTab(tab) {
      tabs.forEach((item) => {
        const selected = item === tab;
        item.setAttribute('aria-selected', String(selected));
        item.tabIndex = selected ? 0 : -1;
        document.getElementById(item.getAttribute('aria-controls')).hidden = !selected;
      });
    }

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => selectTab(tab));
      tab.addEventListener('keydown', (event) => {
        const offsets = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
        let next;

        if (event.key in offsets) {
          next = (index + offsets[event.key] + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
          next = 0;
        } else if (event.key === 'End') {
          next = tabs.length - 1;
        } else {
          return;
        }

        event.preventDefault();
        selectTab(tabs[next]);
        tabs[next].focus();
      });
    });
  });
})();
