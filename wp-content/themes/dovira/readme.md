## Get started

---
0. Importantly! This theme requires the ACF PRO plugin.
1. Download this repository to your WordPress project in the themes directory using the WP CLI or download directly.
```
wp theme install https://github.com/Syndicode/syndicode-wp-dovira/archive/refs/heads/master.zip
```
2. Rename the directory with the theme to the desired one according to the project's name.
3. Run the "Find and Replace" lines "dovira" and "starter_theme" on all directories and subdirectories using your IDE. Replace them with your theme name. Importantly! If the theme name consists of two words, use an underscore between them.
4. Change the theme's name in the file style.css at its root.
5. Run `npm install`
6. Copy the file `.env.example` to `.env`. You can do it by running `npm run copy-env`. By default, if there is no `.env` file, the theme will use the `production` environment.
7. Run `npm start` to start development. Run `npm run build` to build assets for production. Notice, if you run `npm run build` and want to check a website out, don't forget to change `WP_ENVIRONMENT_TYPE` to `production`. By default, it should adjust automatically.
8. Keep in mind `http://localhost:3000` is for Vite purposes only. To see the website, use the server of your choice (e.g., Laravel Valet, WP CLI, etc.)

## Theme structure

---

Please use the following directory structure in your WordPress projects:

```
dovira
├─ assets/
│   ├─ fonts/
│   ├─ images/
│   ├─ main.js
│   └─ main.css
├─ inc/
│   ├─ acf/ (optional)
│   ├─ post-types/ (optional)
│   ├─ taxonomies/ (optional)
│   ├─ utils/
│   ├─ acf.php
│   ├─ register-post-types.php
│   ├─ register-taxonomies.php
│   ├─ template-functions.php
│   └─ utils.php
├─  source/
│   ├─ main.js (entry file)
│   ├─ sсripts/
│   │   ├─ modules/
│   │   └─ app.js
│   └─ styles/
│       ├─ blocks/
│       ├─ admin/ (optional)
│       ├─ rewrites/ (optional)
│       ├─ admin.scss (optional)
│       └─ app.scss
├─ template-parts/
├─ templates/  (optional)
├─ vendor/  (optional)
├─ composer.json (optional)
├─ composer.lock (optional)
├─ footer.php
├─ functions.php
├─ header.php
├─ index.php
├─ screenshot.png
└─ styles.css
```
