(() => {
  function updateFields() {
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
      updateFields();
    }
  });

  updateFields();
})();
