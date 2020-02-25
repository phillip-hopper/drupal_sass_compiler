const node_modules = process.env['SCSS_COMPILER_NODE_MODULES_PATH'];
const drupal_root = process.env['SCSS_COMPILER_DRUPAL_ROOT'];
const cache_folder = process.env['SCSS_COMPILER_CACHE_FOLDER'];

if (!node_modules || !drupal_root || !cache_folder) {
  return;
}

const fs = require('fs');
const sass = require(`${node_modules}/node-sass`);

let files = [];
let data = fs.readFileSync(cache_folder + '/libsass_temp.json', { encoding: 'utf-8' });
files = JSON.parse(data);

files.forEach((file) => {
  sass.render({
    file: drupal_root + '/' + file.source_path,
  }, (err, result) => {
    fs.writeFile(drupal_root + '/' + file.css_path, result.css, (err) => {
      if (err) {
        throw err;
      }
    });
  });
});
