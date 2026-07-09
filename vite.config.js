import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { loadEnv } from "vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const devServerHost = env.VITE_DEV_SERVER_HOST || "127.0.0.1";
    const devServerPort = Number(env.VITE_DEV_SERVER_PORT || 5173);
    const devServerOrigin =
        env.VITE_DEV_SERVER_ORIGIN ||
        `http://${devServerHost}:${devServerPort}`;
    const hmrHost =
        env.VITE_HMR_HOST ||
        (devServerHost === "0.0.0.0" ? "127.0.0.1" : devServerHost);

    return {
        plugins: [
            react(),
            laravel({
                input: ["resources/css/app.css", "resources/js/app.jsx"],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: devServerHost,
            port: devServerPort,
            strictPort: true,
            origin: devServerOrigin,
            cors: {
                origin: true,
                credentials: true,
            },
            hmr: {
                host: hmrHost,
                port: Number(env.VITE_HMR_PORT || devServerPort),
                clientPort: Number(
                    env.VITE_HMR_CLIENT_PORT ||
                        env.VITE_HMR_PORT ||
                        devServerPort,
                ),
                protocol: env.VITE_HMR_PROTOCOL || "ws",
            },
            watch: {
                ignored: ["**/storage/framework/views/**"],
            },
        },
    };
});
