import IMask from 'imask';

const initImask = () => {
  const elements = document.querySelectorAll('input[type="tel"]');
  if (elements.length) {
    elements.forEach((element) => {
      const maskOptions = {
        mask: '(000) 000-00-00'
      };
      const mask = IMask(element, maskOptions);
    })
  }
}

export {initImask}
