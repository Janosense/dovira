window.addEventListener('load', () => {
  acf.add_filter('color_picker_args', function (args, field) {
    args.palettes = ['#1B1B1B', '#3c3c3c', '#C06F94', '#FFFFFF', '#F5F5F5'];

    return args;

  });
});
