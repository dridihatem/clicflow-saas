import { readFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const manifestPath = join(root, 'public/build/manifest.json');
const viteConfigPath = join(root, 'vite.config.js');

if (!existsSync(manifestPath)) {
    console.error('Vite manifest missing:', manifestPath);
    process.exit(1);
}

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
if (!manifest || typeof manifest !== 'object') {
    console.error('Vite manifest is not a JSON object.');
    process.exit(1);
}

const viteSource = readFileSync(viteConfigPath, 'utf8');
const inputMatch = viteSource.match(/input:\s*\[([^\]]+)\]/s);
if (!inputMatch) {
    console.error('Could not read Vite input entries from vite.config.js');
    process.exit(1);
}

const expectedEntries = [...inputMatch[1].matchAll(/['"]([^'"]+)['"]/g)].map((match) => match[1]);
const missingEntries = expectedEntries.filter((entry) => !manifest[entry]?.file);

if (missingEntries.length > 0) {
    console.error('Vite manifest is missing required entries:');
    for (const entry of missingEntries) {
        console.error('  -', entry);
    }
    process.exit(1);
}

const missingFiles = [];
for (const [key, item] of Object.entries(manifest)) {
    const files = [];
    if (typeof item?.file === 'string') {
        files.push(item.file);
    }
    if (Array.isArray(item?.css)) {
        files.push(...item.css);
    }
    for (const file of files) {
        const absolute = join(root, 'public/build', file);
        if (!existsSync(absolute)) {
            missingFiles.push(`${key} → ${file}`);
        }
    }
}

if (missingFiles.length > 0) {
    console.error('Vite manifest references files that are not on disk:');
    for (const file of missingFiles) {
        console.error('  -', file);
    }
    process.exit(1);
}

console.log(`Vite manifest OK (${expectedEntries.length} entries).`);
