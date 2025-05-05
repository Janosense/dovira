import Swiper from 'swiper';
import { Navigation, Thumbs } from 'swiper/modules';

const initSwiper = () => {
  const swiper = new Swiper("#swiper-about-years", {
    loop: true,
    spaceBetween: 10,
    slidesPerView: 2,
    freeMode: true,
    watchSlidesProgress: true,
    breakpoints: {
      // when window width is >= 640px
      640: {
        slidesPerView: 4,
      },
      1024: {
        slidesPerView: 7,
      },
      1280: {
        slidesPerView: 10,
      }
    }
  });
  const swiper2 = new Swiper("#swiper-about-content", {
    modules: [Navigation, Thumbs],
    loop: true,
    spaceBetween: 10,
    navigation: {
      nextEl: ".swiper-button-next",
      prevEl: ".swiper-button-prev",
    },
    thumbs: {
      swiper: swiper,
    },
  });
}

export {initSwiper};
