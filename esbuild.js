/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

// Сборка фронтенда админ-панели: assets/index.ts → dist/admin-palette.js (+ .css рядом).
// Внешних зависимостей нет — палитра намеренно самодостаточна: она обязана работать на любой странице
// админки, в том числе если модуль-сосед сломал свой JS.

import * as esbuild from 'esbuild';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function build() {
  try {
    await esbuild.build({
      entryPoints: [path.resolve(__dirname, 'assets/index.ts')],
      bundle: true,
      outfile: path.resolve(__dirname, 'dist/admin-palette.js'),
      format: 'esm',
      legalComments: 'none',
      target: 'ESNext',
      minify: true,
      platform: 'browser',
      sourcemap: true,
    });
    console.log('✅ yii2-cms-admin-panel (палитра команд) собран');
  } catch (e) {
    console.error(`💥 Ошибка сборки yii2-cms-admin-panel: ${e.message}`);
    process.exit(1);
  }
}

build();
