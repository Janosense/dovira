import IMask from 'imask';

const initImask = () => {
  const elements = document.querySelectorAll('input[name="phone"]');
  if (elements.length) {
    console.log(elements);
    elements.forEach((element) => {
      const maskOptions = {
        mask: '(000) 000-00-00'
      };
      const mask = IMask(element, maskOptions);
    })
  }
}

export {initImask}
