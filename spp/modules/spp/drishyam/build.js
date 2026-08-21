/**
 * SPP-UX Optional Build Script
 * 
 * Bundles the modular SPPUX v13 into a single production file.
 * This is OPTIONAL — SPPUX works as zero-build-step ES modules by default.
 * 
 * Usage:
 *   npx esbuild --bundle spp/modules/spp/drishyam/js/sppux.js --outfile=spp/modules/spp/drishyam/js/sppux.bundle.js --format=esm --minify
 * 
 * Or run this script:
 *   node spp/modules/spp/drishyam/build.js
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const rootDir = path.resolve(__dirname);
const entryPoint = path.join(rootDir, 'js', 'sppux.js');
const outFile = path.join(rootDir, 'js', 'sppux.bundle.js');
const outFileMin = path.join(rootDir, 'js', 'sppux.bundle.min.js');

console.log('🔨 SPP-UX Build Script');
console.log(`   Entry: ${entryPoint}`);
console.log(`   Output: ${outFile}`);

try {
    // Check if esbuild is available
    execSync('npx esbuild --version', { stdio: 'pipe' });
} catch (e) {
    console.error('❌ esbuild not found. Install with: npm install -g esbuild');
    console.log('   Or use: npx -y esbuild ...');
    process.exit(1);
}

// Development bundle (readable, with source maps)
console.log('\n📦 Building development bundle...');
execSync(
    `npx esbuild "${entryPoint}" --bundle --format=esm --outfile="${outFile}" --sourcemap --target=es2020`,
    { stdio: 'inherit' }
);

// Production bundle (minified)
console.log('\n📦 Building production bundle...');
execSync(
    `npx esbuild "${entryPoint}" --bundle --format=esm --outfile="${outFileMin}" --minify --target=es2020`,
    { stdio: 'inherit' }
);

// Report sizes
const devSize = fs.statSync(outFile).size;
const prodSize = fs.statSync(outFileMin).size;
console.log(`\n✅ Build complete!`);
console.log(`   Dev:  ${(devSize / 1024).toFixed(1)} KB`);
console.log(`   Prod: ${(prodSize / 1024).toFixed(1)} KB`);
console.log(`\nTo use the bundle, replace:`);
console.log(`   <script type="module" src="js/sppux.js">`);
console.log(`With:`);
console.log(`   <script type="module" src="js/sppux.bundle.min.js">`);
