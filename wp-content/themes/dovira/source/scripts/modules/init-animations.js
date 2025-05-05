export const getCoords = (elem) => {
  let box = elem.getBoundingClientRect();

  return {
    top: box.top + window.scrollY,
    right: box.right + window.scrollX,
    bottom: box.bottom + window.scrollY,
    left: box.left + window.scrollX
  };
};

const initAnimations = () => {
	const sections = document.querySelectorAll('.animate');
	if (sections.length > 0) {
		setTimeout(() => {
			const offset = 200;
			const sectionsSettings = [];
			const triggerLine = document.documentElement.clientHeight - offset;

			sections.forEach((section, index) => {
				sectionsSettings.push({
					isAnimated: false
				});

				if (!sectionsSettings[index].isAnimated && window.pageYOffset + triggerLine >= getCoords(section).top) {
					section.classList.add('animate--active');
					sectionsSettings[index].isAnimated = true;
				}
			});

			let inProcess = false;
			document.addEventListener('scroll', () => {
				if (!inProcess) {
					inProcess = !inProcess;
					sections.forEach((section, index) => {
						if (!sectionsSettings[index].isAnimated && window.scrollY + triggerLine >= getCoords(section).top) {
							section.classList.add('animate--active');
							sectionsSettings[index].isAnimated = true;
						}
					});
					inProcess = !inProcess;
				}
			});
		}, 500);
	}
};

export { initAnimations };
