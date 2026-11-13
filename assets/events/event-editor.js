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

  const editor = document.querySelector('#bckfc-event-layout .bckfc-event-editor');

  if (!editor) {
    return;
  }

  // connect our image pickers to WP native media picker.
  editor.querySelectorAll('[data-bckfc-image]').forEach((field) => {
    const input = field.querySelector('input');
    const preview = field.querySelector('[data-bckfc-image-preview]');
    const remove = field.querySelector('[data-bckfc-image-remove]');

    field.querySelector('[data-bckfc-image-select]').addEventListener('click', () => {
      const picker = wp.media({
        title: 'Select Image',
        library: { type: 'image' },
        multiple: false,
      });

      picker.on('select', () => {
        const attachment = picker.state().get('selection').first().toJSON();
        const image = document.createElement('img');
        image.src = attachment.sizes?.thumbnail?.url || attachment.url;
        image.alt = attachment.alt || '';
        preview.replaceChildren(image);
        input.value = attachment.id;
        remove.hidden = false;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });

      picker.open();
    });

    remove.addEventListener('click', () => {
      input.value = '0';
      preview.replaceChildren();
      remove.hidden = true;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Include our layout values in WordPress's preview/autosave request.
  jQuery(document).on('before-autosave', (event, data) => {
    const values = {};
    editor.querySelectorAll('[name^="_bckfc_event_layout["]').forEach((input) => {
      const key = input.name.match(/\[([^\]]+)\]/)[1];
      values[key] = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value;
    });
    data._bckfc_event_layout = values;
  });
})();
