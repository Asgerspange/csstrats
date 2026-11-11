import laravel from "laravel-vite-plugin";
import { defineConfig, loadEnv } from "vite";

function getWorkerFiles() {
    return ["resources/js/Workers/RoundLoader.worker.js"].reduce(
        (entries, file) => {
            const name = file.replace(/^.*[\\/]/, "").replace(".js", "");
            entries[name] = file;
            return entries;
        },
        {}
    );
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const watchWorkers = env.WATCH_WORKER === "true" || false;

    return {
        plugins: [
            laravel({
                input: [],
                buildDirectory: "build/workers",
            }),
        ],
        build: {
            manifest: false,
            rollupOptions: {
                input: getWorkerFiles(),
                output: {
                    entryFileNames: "[name].js",
                },
            },
            watch: watchWorkers
                ? {
                      include: "resources/js/Workers/*.worker.js",
                  }
                : undefined,
        },
    };
});
