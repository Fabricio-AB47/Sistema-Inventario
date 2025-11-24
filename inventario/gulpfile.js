const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const sourcemaps = require('gulp-sourcemaps');
const autoprefixer = require('gulp-autoprefixer');
const applyAutoprefix = autoprefixer.default || autoprefixer;
const cleanCSS = require('gulp-clean-css');
const terser = require('gulp-terser');
const plumber = require('gulp-plumber');
const browserSync = require('browser-sync').create();

const isProd = process.env.NODE_ENV === 'production';

const paths = {
  styles: {
    entry: 'src/scss/main.scss',
    watch: 'src/scss/**/*.scss',
    dest: 'public/assets/css'
  },
  scripts: {
    src: 'src/js/**/*.js',
    dest: 'public/assets/js'
  },
  views: ['public/**/*.php', 'public/**/*.html']
};

async function clean() {
  // dynamic import keeps compatibility with ESM-only "del"
  const { deleteAsync } = await import('del');
  return deleteAsync(['public/assets/css/**/*', 'public/assets/js/**/*']);
}

function styles() {
  let pipeline = gulp
    .src(paths.styles.entry)
    .pipe(plumber());

  if (!isProd) {
    pipeline = pipeline.pipe(sourcemaps.init());
  }

  pipeline = pipeline
    .pipe(sass().on('error', sass.logError))
    .pipe(applyAutoprefix());

  if (isProd) {
    pipeline = pipeline.pipe(cleanCSS({ level: 2 }));
  }

  if (!isProd) {
    pipeline = pipeline.pipe(sourcemaps.write('.'));
  }

  return pipeline.pipe(gulp.dest(paths.styles.dest)).pipe(browserSync.stream());
}

function scripts() {
  let pipeline = gulp
    .src(paths.scripts.src, { sourcemaps: !isProd })
    .pipe(plumber());

  if (isProd) {
    pipeline = pipeline.pipe(terser());
  }

  return pipeline
    .pipe(gulp.dest(paths.scripts.dest, { sourcemaps: !isProd ? '.' : false }))
    .pipe(browserSync.stream());
}

function watchFiles() {
  gulp.watch(paths.styles.watch, styles);
  gulp.watch(paths.scripts.src, scripts);
  gulp.watch(paths.views).on('change', browserSync.reload);
}

function serve() {
  browserSync.init({
    proxy: 'http://localhost/inventario',
    notify: false,
    open: false
  });

  watchFiles();
}

function setProdEnv(done) {
  process.env.NODE_ENV = 'production';
  done();
}

const dev = gulp.series(clean, gulp.parallel(styles, scripts), watchFiles);
const build = gulp.series(setProdEnv, clean, gulp.parallel(styles, scripts));

exports.clean = clean;
exports.styles = styles;
exports.scripts = scripts;
exports.watch = gulp.series(clean, gulp.parallel(styles, scripts), watchFiles);
exports.dev = dev;
exports.build = build;
exports.serve = gulp.series(clean, gulp.parallel(styles, scripts), serve);
exports.default = dev;
