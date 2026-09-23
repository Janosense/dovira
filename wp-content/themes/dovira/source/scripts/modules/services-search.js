import {debouncedRecorder} from '@scripts/features/search-stats/record';

const servicesSearch = () => {
  const input = document.querySelector('#services-search-input');
  if (input) {
    const resetButton = document.querySelector('#services-search-reset');
    const services = document.querySelectorAll('.services__item');
    if (services.length) {
      // Feature search-stats: records what is searched here (the page hosting the block).
      debouncedRecorder(input, 'services', Number(input.dataset.searchStatsContext));

      input.addEventListener('input', (evt) => {
        if (input.value.length > 0) {
          resetButton.classList.add('active');
        } else {
          resetButton.classList.remove('active');
        }

        if (input.value.length >= 3) {
          services.forEach((service) => {
            if (!service.dataset.search.includes(input.value.toLowerCase())) {
              service.style.display = 'none';
            } else {
              service.style.display = 'block';
            }
          });
        }

        if (input.value.length < 3) {
          services.forEach((service) => {
            service.style.display = 'block';
          });
        }
      });

      resetButton.addEventListener('click', (evt) => {

        if (input.value === '') {
          input.focus();
        } else {
          input.value = '';
          services.forEach((service) => {
            service.style.display = 'block';
          });
          resetButton.classList.remove('active');
        }
      });
    }
  }
}

export {servicesSearch}
