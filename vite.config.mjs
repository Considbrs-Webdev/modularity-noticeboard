import { createViteConfig } from "vite-config-factory";

const entries = {
        'css/modularity-noticeboard':               './source/sass/modularity-noticeboard.scss',
        'js/modularity-noticeboard':                './source/js/modularity-noticeboard.js',
};

export default createViteConfig(entries, {
	outDir: "assets/dist",
	manifestFile: "manifest.json",
});
