import { createHash } from 'node:crypto';
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';

const publicPath = '/bundles/contaocore/';

const iconsManifest = (hashed) => {
    const write = () => {
        const manifest = {};
        for (const file of readdirSync('core-bundle/assets/icons').filter((f) => f.endsWith('.svg'))) {
            const hash = createHash('sha256').update(readFileSync(`core-bundle/assets/icons/${file}`)).digest('hex').slice(0, 8);
            manifest[file] = `${publicPath}icons/${hashed ? file.replace(/\.svg$/, `.${hash}.svg`) : file}`;
        }
        mkdirSync('core-bundle/public/icons', { recursive: true });
        writeFileSync('core-bundle/public/icons/manifest.json', JSON.stringify(manifest, null, 2));
    };

    return {
        name: 'contao-icons-manifest',
        buildStart: write,
        closeBundle: write,
        configureServer(server) {
            server.httpServer?.once('listening', write);
        },
    };
};

export default defineConfig(({ command }) => ({
    input: {
        backend: './core-bundle/assets/backend.js',
        navigation: './core-bundle/assets/navigation.js',
        passkey_login: './core-bundle/assets/passkey_login.js',
        passkey_create: './core-bundle/assets/passkey_create.js',
        login: './core-bundle/assets/styles/login.pcss',
        tinymce: './core-bundle/assets/styles/vendors/tinymce/theme/light.pcss', 'tinymce-dark': './core-bundle/assets/styles/vendors/tinymce/theme/dark.pcss',
    },
    build: {
        sourcemap: command !== 'build',
        assetsInlineLimit: 0,
        watch: { exclude: ['**/core-bundle/public/**'] }, // icon manifest written by the build itself
        rollupOptions: {
            output: {
                assetFileNames: ({ names: [name] }) => {
                    if (/\.(woff2?|ttf|eot)$/.test(name)) return 'fonts/[name].[hash:8][extname]';
                    if (/\.(svg|png|gif|jpe?g|webp)$/.test(name)) return 'images/[name].[hash:8][extname]';
                    return '[name].[hash:8][extname]';
                },
            },
        },
    },
    server: {
        https: { pfx: readFileSync(`${process.env.HOME}/.symfony5/certs/default.p12`) },
        allowedHosts: true,
    },
    plugins: [
        Symfony({
            outputPath: 'core-bundle/public',
            publicPath,
            manifestKeyPrefix: '',
            copy: [{ from: 'core-bundle/assets/icons', to: 'icons', pattern: /\.svg$/ }],
        }),
        iconsManifest(command === 'build'),
    ],
}));
